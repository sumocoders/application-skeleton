<?php

namespace App\Tests;

use Symfony\Component\Panther\PantherTestCase;

class PagesAreFoundTest extends PantherTestCase
{
    private const ROUTES = [
        '/',
    ];

    public function testPagesAreFound()
    {
        $client = static::createPantherClient();

        foreach ($this::ROUTES as $route) {
            $client->request('GET', $route);
            self::assertEquals(200, $client->getInternalResponse()->getStatusCode());
        }
    }
}
