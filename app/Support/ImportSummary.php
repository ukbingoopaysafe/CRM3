<?php

namespace App\Support;

/**
 * What an import actually did, in the terms D-024 asks for: a success count,
 * a failure count, and the rows that failed with their reasons.
 */
final class ImportSummary
{
    /**
     * @param  array<int, array{row: int, reason: string}>  $failures
     */
    public function __construct(
        public readonly int $created,
        public readonly int $total,
        public readonly array $failures = [],
        public readonly ?string $reportPath = null,
    ) {}

    public function failed(): int
    {
        return count($this->failures);
    }

    public function hasFailures(): bool
    {
        return $this->failures !== [];
    }
}
