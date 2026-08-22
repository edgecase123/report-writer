<?php

declare(strict_types=1);

namespace ReportWriter\Builder;

use ReportWriter\Expression\AggregateExpression;
use ReportWriter\Expression\ContentExpression;
use ReportWriter\Expression\FieldExpression;
use ReportWriter\Expression\StaticExpression;

class Column
{
    private string $id;
    private string $header;
    private float $x;
    private float $width;
    private string $textAlign  = '';
    private float $marginLeft  = 0.0;
    private float $marginRight = 0.0;

    private ContentExpression $detailExpr;
    private ?ContentExpression $footerExpr  = null;
    private ?ContentExpression $summaryExpr = null;

    private function __construct(string $id, string $header, float $x, float $width)
    {
        $this->id         = $id;
        $this->header     = $header;
        $this->x          = $x;
        $this->width      = $width;
        $this->detailExpr = new FieldExpression($id);
    }

    public static function make(string $id, string $header, float $x, float $width): self
    {
        return new self($id, $header, $x, $width);
    }

    public function sum(): self   { return $this->withAggregate('sum'); }
    public function avg(): self   { return $this->withAggregate('avg'); }
    public function min(): self   { return $this->withAggregate('min'); }
    public function max(): self   { return $this->withAggregate('max'); }
    public function count(): self { return $this->withAggregate('count'); }

    public function format(callable $fn): self
    {
        $clone = clone $this;
        if ($clone->detailExpr instanceof FieldExpression) {
            $clone->detailExpr = $clone->detailExpr->withFormatter($fn);
        }
        if ($clone->footerExpr instanceof AggregateExpression) {
            $clone->footerExpr = $clone->footerExpr->withFormatter($fn);
        }
        if ($clone->summaryExpr instanceof AggregateExpression) {
            $clone->summaryExpr = $clone->summaryExpr->withFormatter($fn);
        }
        return $clone;
    }

    public function footerContent(ContentExpression $expr): self
    {
        $clone             = clone $this;
        $clone->footerExpr = $expr;
        return $clone;
    }

    public function summaryContent(ContentExpression $expr): self
    {
        $clone              = clone $this;
        $clone->summaryExpr = $expr;
        return $clone;
    }

    public function align(string $textAlign): self
    {
        $clone            = clone $this;
        $clone->textAlign = $textAlign;
        return $clone;
    }

    public function alignLeft(): self   { return $this->align('left'); }
    public function alignCenter(): self { return $this->align('center'); }
    public function alignRight(): self  { return $this->align('right'); }

    public function margin(float $left, float $right = 0.0): self
    {
        $clone              = clone $this;
        $clone->marginLeft  = $left;
        $clone->marginRight = $right;
        return $clone;
    }

    public function getId(): string              { return $this->id; }
    public function getHeader(): string          { return $this->header; }
    public function getX(): float                { return $this->x; }
    public function getWidth(): float            { return $this->width; }
    public function getTextAlign(): string       { return $this->textAlign; }
    public function getMarginLeft(): float       { return $this->marginLeft; }
    public function getMarginRight(): float      { return $this->marginRight; }
    public function getDetailExpr(): ContentExpression   { return $this->detailExpr; }
    public function getFooterExpr(): ?ContentExpression  { return $this->footerExpr; }
    public function getSummaryExpr(): ?ContentExpression { return $this->summaryExpr; }

    private function withAggregate(string $fn): self
    {
        $expr               = new AggregateExpression($fn, $this->id);
        $clone              = clone $this;
        $clone->footerExpr  = $expr;
        $clone->summaryExpr = $expr;
        return $clone;
    }
}
