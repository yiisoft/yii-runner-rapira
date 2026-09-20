<?php

declare(strict_types=1);

namespace Rapira;

/**
 * Serves one worker-mode request, returning false when the host stops handing out work.
 */
function handle_request(callable $handler): bool {}
