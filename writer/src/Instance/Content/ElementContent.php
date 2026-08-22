<?php

declare(strict_types=1);

namespace foreup\Reporting\Instance\Content;

abstract class ElementContent
{
    abstract public function getType(): string;
    abstract public function isSplittable(): bool;
    abstract public function toArray(): array;
}
