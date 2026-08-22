<?php

declare(strict_types=1);

namespace foreup\Reporting\Exceptions;

class MissingSubreportException extends \RuntimeException
{
    public static function forId(string $subreportInstanceId): self
    {
        return new self("Subreport instance '{$subreportInstanceId}' not found in report instance.");
    }
}
