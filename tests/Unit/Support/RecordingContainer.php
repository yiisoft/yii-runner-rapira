<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\Rapira\Tests\Unit\Support;

use Psr\Container\ContainerInterface;
use Yiisoft\Test\Support\Container\Exception\NotFoundException;

/**
 * Serves a fixed set of services and counts how many times each one was asked for, so a test can tell
 * a memoized lookup from one repeated on every call.
 */
final class RecordingContainer implements ContainerInterface
{
    /** @var array<string, int> Number of {@see get()} calls per service id. */
    public array $calls = [];

    /**
     * @param array<string, object> $services
     */
    public function __construct(
        private readonly array $services = [],
    ) {}

    public function get(string $id): object
    {
        $this->calls[$id] = ($this->calls[$id] ?? 0) + 1;

        return $this->services[$id] ?? throw new NotFoundException($id);
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
