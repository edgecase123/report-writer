<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use ReportWriter\App\Container;

final class ContainerTest extends TestCase
{
    public function testResolvesRegisteredFactoryOnce(): void
    {
        $container = new Container();
        $calls     = 0;
        $container->set('svc', function () use (&$calls) {
            $calls++;
            return new \stdClass();
        });

        $a = $container->get('svc');
        $b = $container->get('svc');

        $this->assertSame($a, $b, 'container must cache resolved services');
        $this->assertSame(1, $calls, 'factory must be invoked only once');
    }

    public function testHasReturnsFalseForUnknownId(): void
    {
        $container = new Container();
        $this->assertFalse($container->has('nope'));
    }

    public function testGetThrowsPsr11NotFoundForUnknownId(): void
    {
        $container = new Container();

        $this->expectException(NotFoundExceptionInterface::class);
        $container->get('nope');
    }

    public function testFactoryReceivesContainerForDependencyLookup(): void
    {
        $container = new Container();
        $container->set('dep', static fn () => 'dep-value');
        $container->set('svc', static fn (Container $c) => $c->get('dep'));

        $this->assertSame('dep-value', $container->get('svc'));
    }
}
