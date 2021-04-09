<?php

namespace App\Tests;

/**
 * Remove all commented code if you want to test pages after a login screen.
 */
//use App\Repository\UserRepository;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;

class PagesAreFoundTest extends PantherTestCase
{
    private const LOGGED_IN_EMAIL = 'accounts@sumocoders.be';

    /**
     * @dataProvider providePublicUrls
     */
    public function testPublicPagesAreFound($route): void
    {
        $client = static::createPantherClient();
        $client->request('GET', $route);
        self::assertEquals(200, $client->getInternalResponse()->getStatusCode());
    }

    /**
     * @dataProvider provideLoggedInUrls
     */
    public function testLoggedInPagesAreFound($route): void
    {
        $client = static::createPantherClient();
        $this->login($client);
        $client->request('GET', $route);
        self::assertEquals(200, $client->getInternalResponse()->getStatusCode());
    }

    public function providePublicUrls()
    {
        return [
            ['/']
        ];
    }

    public function provideLoggedInUrls()
    {
        return [
            ['/']
        ];
    }

    private function login(Client $client): void
    {
        // $userRepository = static::$container->get(UserRepository::class);
        // $user = $userRepository->findOneByEmail($this:LOGGED_IN_EMAIL);
        // $client->loginUser($user);
    }
}
