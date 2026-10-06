<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\Rapira\Tests\Feature\Support\RequestParity;

use HttpSoft\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function rawurlencode;

/**
 * Sets the {@see self::NAME} cookie to {@see self::VALUE}, a value that only survives the round trip
 * when the server URL-decodes the `Cookie` header it gets back.
 */
final class SetCookieAction implements MiddlewareInterface
{
    public const NAME = 'session';
    public const VALUE = 'a+b/c==:d e é';

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        // Encoded as PHP's `setcookie()` does it.
        return (new Response())->withHeader(
            'Set-Cookie',
            self::NAME . '=' . rawurlencode(self::VALUE) . '; Path=/',
        );
    }
}
