<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");
        if (! ($connection === 'sqlite' && $database === ':memory:') && ! ($connection === 'mysql' && $database === 'gps_tracking_test')) {
            throw new RuntimeException('Las pruebas solo pueden usar SQLite en memoria o gps_tracking_test.');
        }

        return $app;
    }
}
