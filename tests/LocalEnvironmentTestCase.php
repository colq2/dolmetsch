<?php

namespace colq2\Dolmetsch\Tests;

/**
 * Boots the package as if the host application were running locally, so the
 * default `register_local_server` environment gate is satisfied.
 */
class LocalEnvironmentTestCase extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $app['env'] = 'local';
    }
}
