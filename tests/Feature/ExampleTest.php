<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_the_health_endpoint_returns_a_successful_response(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
    }

    public function test_the_api_v1_index_returns_metadata(): void
    {
        $response = $this->getJson('/api/v1');

        $response
            ->assertStatus(200)
            ->assertJson([
                'name' => 'MaintOps Laravel API',
                'version' => 'v1',
            ]);
    }
}
