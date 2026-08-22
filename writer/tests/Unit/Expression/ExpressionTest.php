<?php

declare(strict_types=1);

namespace ReportWriter\Tests\Unit\Expression;

use ReportWriter\Expression\AggregateExpression;
use ReportWriter\Expression\ComputedExpression;
use ReportWriter\Expression\EvalContext;
use ReportWriter\Expression\FieldExpression;
use ReportWriter\Expression\StaticExpression;
use PHPUnit\Framework\TestCase;

class ExpressionTest extends TestCase
{
    // ── StaticExpression ──────────────────────────────────────────────────────

    public function testStaticReturnsLiteral(): void
    {
        $expr = new StaticExpression('Grand Total');
        $this->assertSame('Grand Total', $expr->evaluate(new EvalContext()));
    }

    public function testStaticIgnoresContext(): void
    {
        $expr = new StaticExpression('X');
        $ctx  = new EvalContext(['field' => 'ignored'], [['field' => 'also ignored']], ['param' => 'irrelevant']);
        $this->assertSame('X', $expr->evaluate($ctx));
    }

    // ── FieldExpression ───────────────────────────────────────────────────────

    public function testFieldResolvesFromRow(): void
    {
        $expr = new FieldExpression('price');
        $ctx  = new EvalContext(['price' => '12.50']);
        $this->assertSame('12.50', $expr->evaluate($ctx));
    }

    public function testFieldMissingKeyReturnsEmpty(): void
    {
        $expr = new FieldExpression('missing');
        $this->assertSame('', $expr->evaluate(new EvalContext()));
    }

    public function testFieldAppliesFormatter(): void
    {
        $expr = new FieldExpression('amount', fn($v) => '$' . number_format((float) $v, 2));
        $ctx  = new EvalContext(['amount' => 15.5]);
        $this->assertSame('$15.50', $expr->evaluate($ctx));
    }

    public function testFieldWithFormatterIsImmutable(): void
    {
        $base    = new FieldExpression('v');
        $derived = $base->withFormatter(fn($v) => strtoupper((string) $v));
        $ctx     = new EvalContext(['v' => 'hello']);
        $this->assertSame('hello', $base->evaluate($ctx));
        $this->assertSame('HELLO', $derived->evaluate($ctx));
    }

    // ── AggregateExpression ───────────────────────────────────────────────────

    private function rows(): array
    {
        return [
            ['price' => 10.0],
            ['price' => 20.0],
            ['price' => 30.0],
        ];
    }

    public function testAggregateSum(): void
    {
        $expr = new AggregateExpression('sum', 'price');
        $this->assertSame('60', $expr->evaluate(new EvalContext([], $this->rows())));
    }

    public function testAggregateAvg(): void
    {
        $expr = new AggregateExpression('avg', 'price');
        $this->assertSame('20', $expr->evaluate(new EvalContext([], $this->rows())));
    }

    public function testAggregateMin(): void
    {
        $expr = new AggregateExpression('min', 'price');
        $this->assertSame('10', $expr->evaluate(new EvalContext([], $this->rows())));
    }

    public function testAggregateMax(): void
    {
        $expr = new AggregateExpression('max', 'price');
        $this->assertSame('30', $expr->evaluate(new EvalContext([], $this->rows())));
    }

    public function testAggregateCount(): void
    {
        $expr = new AggregateExpression('count', 'price');
        $this->assertSame('3', $expr->evaluate(new EvalContext([], $this->rows())));
    }

    public function testAggregateEmptyRowsReturnsZero(): void
    {
        foreach (['sum', 'avg', 'min', 'max', 'count'] as $fn) {
            $expr = new AggregateExpression($fn, 'price');
            $this->assertSame('0', $expr->evaluate(new EvalContext()), "fn={$fn}");
        }
    }

    public function testAggregateAppliesFormatter(): void
    {
        $fmt  = fn($v) => '$' . number_format((float) $v, 2);
        $expr = new AggregateExpression('sum', 'price', $fmt);
        $this->assertSame('$60.00', $expr->evaluate(new EvalContext([], $this->rows())));
    }

    public function testAggregateWithFormatterIsImmutable(): void
    {
        $base    = new AggregateExpression('sum', 'price');
        $derived = $base->withFormatter(fn($v) => '$' . number_format((float) $v, 2));
        $ctx     = new EvalContext([], $this->rows());
        $this->assertSame('60', $base->evaluate($ctx));
        $this->assertSame('$60.00', $derived->evaluate($ctx));
    }

    // ── ComputedExpression ────────────────────────────────────────────────────

    public function testComputedReceivesContext(): void
    {
        $expr = new ComputedExpression(fn(EvalContext $ctx) => count($ctx->aggregateRows));
        $ctx  = new EvalContext([], $this->rows());
        $this->assertSame('3', $expr->evaluate($ctx));
    }

    public function testComputedAppliesFormatter(): void
    {
        $expr = new ComputedExpression(
            fn(EvalContext $ctx) => 42,
            fn($v) => "value={$v}"
        );
        $this->assertSame('value=42', $expr->evaluate(new EvalContext()));
    }
}
