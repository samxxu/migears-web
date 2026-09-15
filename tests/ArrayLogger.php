<?php
declare(strict_types=1);

namespace MiGears\Web\Tests;

use Psr\Log\AbstractLogger;

class ArrayLogger extends AbstractLogger
{
    public array $logs = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    public function count(): int
    {
        return count($this->logs);
    }

    public function first(): ?array
    {
        return $this->logs[0] ?? null;
    }
}
