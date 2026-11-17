<?php

namespace Webtack\Ecms\Tests\Fixtures;

/**
 * A controller of a pretend host application. An invokable class (not a closure), so the route can be cached.
 */
class HostController
{
    public function __invoke(): string
    {
        return 'host-page';
    }
}
