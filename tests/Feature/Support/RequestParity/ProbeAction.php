<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\Rapira\Tests\Feature\Support\RequestParity;

use HttpSoft\Message\Response;
use HttpSoft\Message\StreamFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Answers with a JSON dump of the request as the application sees it, so a test can compare it with
 * what PHP-FPM would have handed the same action.
 */
final class ProbeAction implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $files = [];
        foreach ($request->getUploadedFiles() as $name => $file) {
            if ($file instanceof UploadedFileInterface) {
                $files[$name] = [
                    'name' => $file->getClientFilename(),
                    'type' => $file->getClientMediaType(),
                    'size' => $file->getSize(),
                    'error' => $file->getError(),
                    'contents' => (string) $file->getStream(),
                ];
            }
        }

        $probe = [
            'parsedBody' => $request->getParsedBody(),
            'cookies' => $request->getCookieParams(),
            'query' => $request->getQueryParams(),
            'server' => $request->getServerParams(),
            'files' => $files,
        ];

        return (new Response())
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode($probe, JSON_THROW_ON_ERROR)));
    }
}
