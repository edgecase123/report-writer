<?php

declare(strict_types=1);

namespace ReportWriter\Fill;

final class ResolvedContent
{
    private string $contentType;
    private string $value;

    public function __construct(
        string $contentType,
        string $value
    ) {
        $this->value = $value;
        $this->contentType = $contentType;
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