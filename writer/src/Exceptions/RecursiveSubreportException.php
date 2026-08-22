<?php

declare(strict_types=1);

namespace foreup\Reporting\Exceptions;

class RecursiveSubreportException extends \RuntimeException
{
    public static function forId(string $subreportInstanceId): self
    {
        return new self("Recursive subreport detected: '{$subreportInstanceId}' has already been visited.");
    }
}
