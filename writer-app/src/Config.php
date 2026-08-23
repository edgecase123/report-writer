<?php

declare(strict_types=1);

namespace ReportWriter\App;

/**
 * Immutable configuration read once at the composition root.
 *
 * Two paths in: {@see Config::fromEnv()} for production/CLI entry points,
 * or direct construction for tests. Consumers pull values through
 * {@see sqlitePath()} / {@see appDebug()} rather than calling getenv() themselves.
 */
final class Config
{
    private ?string $sqlitePath;
    private bool $appDebug;

    public function __construct(?string $sqlitePath, bool $appDebug)
    {
        $this->sqlitePath = $sqlitePath;
        $this->appDebug   = $appDebug;
    }

    public static function fromEnv(): self
    {
        $sqlite = getenv('SQLITE_PATH');
        $debug  = getenv('APP_DEBUG');

        return new self(
            $sqlite !== false && $sqlite !== '' ? $sqlite : null,
            $debug !== false && $debug !== '' && $debug !== '0'
        );
    }

    public function sqlitePath(): ?string
    {
        return $this->sqlitePath;
    }

    public function appDebug(): bool
    {
        return $this->appDebug;
    }
}
