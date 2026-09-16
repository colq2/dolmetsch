<?php

namespace colq2\Dolmetsch\Tests;

use colq2\Dolmetsch\DolmetschServiceProvider;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            McpServiceProvider::class,
            DolmetschServiceProvider::class,
        ];
    }
}
