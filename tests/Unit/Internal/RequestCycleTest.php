<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\Rapira\Tests\Unit\Internal;

use HttpSoft\Message\Response;
use HttpSoft\Message\ServerRequest;
use Psr\Container\ContainerInterface;
use Testo\Assert;
use Testo\Test;
use Yiisoft\Di\StateResetter;
use Yiisoft\Middleware\Dispatcher\MiddlewareDispatcher;
use Yiisoft\Middleware\Dispatcher\MiddlewareFactory;
use Yiisoft\Test\Support\Container\SimpleContainer;
use Yiisoft\Yii\Http\Application;
use Yiisoft\Yii\Runner\Rapira\Internal\RequestCycle;
use Yiisoft\Yii\Runner\Rapira\Tests\Unit\Support\RecordingContainer;

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
    public function theStateResetterIsResolvedOnceAndReusedByEveryRequest(): void
    {
        $container = new RecordingContainer([
            StateResetter::class => new StateResetter(new SimpleContainer()),
        ]);
        $cycle = $this->cycle($container);

        $cycle->finish(new Response());
        $cycle->finish(new Response());

        Assert::same($container->calls[StateResetter::class], 1);
    }

    private function cycle(ContainerInterface $container): RequestCycle
    {
        return new RequestCycle(
            $container,
            new Application(new MiddlewareDispatcher(new MiddlewareFactory($container))),
        );
    }
}
