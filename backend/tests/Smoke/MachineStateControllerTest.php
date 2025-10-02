<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MachineStateControllerTest extends WebTestCase
{
    public function testMachineStateEndpointRespondsWithStructure(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/machine/state');

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('machine_id', $data);
        self::assertArrayHasKey('timestamp', $data);
        self::assertArrayHasKey('session', $data);
        self::assertArrayHasKey('catalog', $data);
        self::assertArrayHasKey('coins', $data);
        self::assertArrayHasKey('alerts', $data);

        self::assertIsArray($data['catalog']);
        self::assertIsArray($data['coins']);
        self::assertArrayHasKey('available', $data['coins']);
        self::assertArrayHasKey('reserved', $data['coins']);
    }
}
