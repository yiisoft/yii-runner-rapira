<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\Rapira\Tests\Unit\Support;

/**
 * Holds state for one request: the container's `reset` callback clears it between requests.
 */
final class RequestScopedService
{
    public ?string $owner = null;
}
