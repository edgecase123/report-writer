<?php

declare(strict_types=1);

namespace ReportWriter\Instance\Content;

abstract class ElementContent
{
    abstract public function getType(): string;
    abstract public function isSplittable(): bool;
    abstract public function toArray(): array;
}
