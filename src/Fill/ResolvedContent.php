<?php

declare(strict_types=1);

namespace ReportWriter\Fill;

final class ResolvedContent
{
    public function __construct(
        private readonly string $contentType,
        private readonly string $value
    ) {
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}