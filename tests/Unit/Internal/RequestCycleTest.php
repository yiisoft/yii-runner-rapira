<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\Rapira\Tests\Unit\Internal;

use HttpSoft\Message\Response;
use HttpSoft\Message\ServerRequest;
use Psr\Container\ContainerInterface;
use Testo\Assert;
use Testo\Test;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Di\StateResetter;
use Yiisoft\Middleware\Dispatcher\MiddlewareDispatcher;
use Yiisoft\Middleware\Dispatcher\MiddlewareFactory;
use Yiisoft\Test\Support\Container\SimpleContainer;
use Yiisoft\Yii\Http\Application;
use Yiisoft\Yii\Runner\Rapira\Internal\RequestCycle;
use Yiisoft\Yii\Runner\Rapira\Tests\Unit\Support\RecordingContainer;
use Yiisoft\Yii\Runner\Rapira\Tests\Unit\Support\RequestScopedService;

use function microtime;

final class RequestCycleTest
{
    #[Test]
    public function beginStampsTheMomentTheApplicationTookOver(): void
    {
        $cycle = $this->cycle(new RecordingContainer());

        $before = microtime(true);
        $request = $cycle->begin(new ServerRequest());
        $after = microtime(true);

        Assert::numeric($request->getAttribute('applicationStartTime'))->between($before, $after);
    }

    #[Test]
    public function theStateResetterIsResolvedForEveryRequest(): void
    {
        $container = new RecordingContainer([
            StateResetter::class => new StateResetter(new SimpleContainer()),
        ]);
        $cycle = $this->cycle($container);

        $cycle->finish(new Response());
        $cycle->finish(new Response());

        Assert::same($container->calls[StateResetter::class], 2);
    }

    /**
     * yiisoft/di builds a StateResetter from the services that exist when it is asked for. A service
     * first built by a later request must still be reset after it, or its state leaks into the next one.
     */
    #[Test]
    public function aServiceFirstBuiltByALaterRequestIsResetAfterIt(): void
    {
        $container = new Container(
            ContainerConfig::create()->withDefinitions([
                RequestScopedService::class => [
                    'class' => RequestScopedService::class,
                    'reset' => function (): void {
                        $this->owner = null;
                    },
                ],
            ]),
        );
        $cycle = $this->cycle($container);

        $cycle->finish(new Response());
        $service = $container->get(RequestScopedService::class);
        $service->owner = 'request 2';
        $cycle->finish(new Response());

        Assert::null($service->owner);
    }

    private function cycle(ContainerInterface $container): RequestCycle
    {
        return new RequestCycle(
            $container,
            new Application(new MiddlewareDispatcher(new MiddlewareFactory($container))),
        );
    }
}
