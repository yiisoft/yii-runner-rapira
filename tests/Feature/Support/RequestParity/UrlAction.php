<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\Rapira\Tests\Feature\Support\RequestParity;

use HttpSoft\Message\Response;
use HttpSoft\Message\StreamFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Router\UrlGeneratorInterface;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Answers with the URLs the router generates while handling the current request.
 */
final class UrlAction implements MiddlewareInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $urls = [
            'relative' => $this->urlGenerator->generate('item', ['id' => '42'], ['page' => '2']),
            'absolute' => $this->urlGenerator->generateAbsolute('item', ['id' => '42'], ['page' => '2']),
            'fromCurrent' => $this->urlGenerator->generateFromCurrent([], ['sort' => 'name']),
        ];

        return (new Response())
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode($urls, JSON_THROW_ON_ERROR)));
    }
}
