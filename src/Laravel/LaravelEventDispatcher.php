<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Laravel;

use Illuminate\Contracts\Events\Dispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;

/** @internal */
final readonly class LaravelEventDispatcher implements EventDispatcherInterface
{
    public function __construct(private Dispatcher $dispatcher)
    {
    }

    public function dispatch(object $event): object
    {
        $this->dispatcher->dispatch($event);

        return $event;
    }
}
