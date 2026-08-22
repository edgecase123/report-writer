<?php

declare(strict_types=1);

namespace ReportWriter\Exceptions;

class ElementExceedsPageException extends \RuntimeException
{
    public static function forElement(string $instanceId, float $elementHeight, float $pageHeight): self
    {
        return new self(
            "Element '{$instanceId}' (height={$elementHeight}) exceeds full page height ({$pageHeight}) and is not splittable."
        );
    }
}
