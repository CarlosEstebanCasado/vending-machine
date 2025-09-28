<?php

namespace App\Tests\Smoke;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthcheckControllerTest extends WebTestCase
{
    public function testHealthEndpointRespondsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['status' => 'ok']);
    }
}
