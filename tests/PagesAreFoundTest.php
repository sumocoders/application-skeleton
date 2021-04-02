<?php

namespace App\Tests;

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
        $this->loggin($client);
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

    private function loggin(Client $client): void
    {
        // $userRepository = static::$container->get(UserRepository::class);
        // $user = $userRepository->findOneByEmail($this:LOGGED_IN_EMAIL);
        // $client->loginUser($user);
    }
}
