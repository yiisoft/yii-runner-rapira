<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Yiisoft\Definitions\DynamicReference;
use Yiisoft\Definitions\Reference;
use Yiisoft\Injector\Injector;
use Yiisoft\Middleware\Dispatcher\MiddlewareDispatcher;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\FastRoute\UrlGenerator;
use Yiisoft\Router\FastRoute\UrlMatcher;
use Yiisoft\Router\Middleware\Router;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\RouteCollectionInterface;
use Yiisoft\Router\RouteCollector;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Router\UrlMatcherInterface;
use Yiisoft\Yii\Http\Application;
use Yiisoft\Yii\Http\Handler\NotFoundHandler;
use Yiisoft\Yii\Runner\Rapira\Tests\Feature\Support\RequestParity\ProbeAction;
use Yiisoft\Yii\Runner\Rapira\Tests\Feature\Support\RequestParity\SetCookieAction;
use Yiisoft\Yii\Runner\Rapira\Tests\Feature\Support\RequestParity\UrlAction;

return [
    RouteCollectionInterface::class => [
        'class' => RouteCollection::class,
        '__construct()' => [
            'collector' => DynamicReference::to(
                static fn() => (new RouteCollector())->addRoute(
                    Route::methods(['GET', 'HEAD', 'QUERY', 'POST', 'PUT', 'PATCH', 'DELETE'], '/probe')
                        ->action(ProbeAction::class),
                    Route::get('/cookie')->action(SetCookieAction::class),
                    Route::get('/urls')->action(UrlAction::class)->name('urls'),
                    Route::get('/items/{id}')->action(ProbeAction::class)->name('item'),
                ),
            ),
        ],
    ],

    UrlMatcherInterface::class => static fn(Injector $injector) => $injector->make(UrlMatcher::class, [
        // Route caching would otherwise require a bound Psr\SimpleCache\CacheInterface.
        'cache' => null,
    ]),

    UrlGeneratorInterface::class => UrlGenerator::class,

    CurrentRoute::class => [
        'reset' => function (): void {
            $this->route = null;
            $this->uri = null;
            $this->arguments = [];
        },
    ],

    Application::class => [
        '__construct()' => [
            'dispatcher' => DynamicReference::to(
                static fn(ContainerInterface $container) => $container
                    ->get(MiddlewareDispatcher::class)
                    ->withMiddlewares([Router::class]),
            ),
            'fallbackHandler' => Reference::to(NotFoundHandler::class),
        ],
    ],
];
