<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class PingTest extends TestCase
{
    public function test_ping_responds_with_json_under_the_v1_prefix(): void
    {
        $this->getJson('/api/v1/ping')
            ->assertOk()
            ->assertJsonStructure(['status', 'app', 'timestamp'])
            ->assertJsonPath('status', 'ok');
    }

    public function test_api_is_not_exposed_without_the_version_prefix(): void
    {
        $this->getJson('/api/ping')->assertNotFound();
    }

    public function test_cors_allows_the_mobile_and_web_clients(): void
    {
        $this->call('OPTIONS', '/api/v1/ping', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:8080',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization',
        ])->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin');
    }
}
