<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7\Integration;

use PHPUnit\Framework\TestCase;

class ServerRequestFromGlobalsTest extends TestCase
{
    protected function setUp(): void
    {
        if (false === $this->getServerUri()) {
            self::markTestSkipped();
        }

        parent::setUp();
    }

    public function testBodyExists(): void
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $this->getServerUri());
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, 'foobar');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);

        if (PHP_VERSION_ID < 80000) {
            curl_close($curl);
        }

        self::assertNotFalse($response);
        $data = json_decode($response, true);
        self::assertIsArray($data);
        self::assertArrayHasKey('method', $data);
        self::assertArrayHasKey('uri', $data);
        self::assertArrayHasKey('body', $data);

        self::assertEquals('foobar', $data['body']);
    }

    private function getServerUri()
    {
        return $_SERVER['TEST_SERVER'] ?? false;
    }
}
