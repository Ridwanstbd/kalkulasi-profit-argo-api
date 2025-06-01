<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $token;
    protected string $apiBaseUrl = '/api/services';

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        $this->token = JWTAuth::fromUser($this->user);
    }

    protected function getAuthHeaders()
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];
    }

    public function test_can_get_all_services_with_stats()
    {
        Service::factory()->create([
            'name' => 'Service 1',
            'selling_price' => 100000,
            'hpp' => 80000
        ]);
        
        Service::factory()->create([
            'name' => 'Service 2',
            'selling_price' => 200000,
            'hpp' => 150000
        ]);

        $response = $this->withHeaders($this->getAuthHeaders())
            ->getJson($this->apiBaseUrl);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'stats' => [
                    'total_services',
                    'avg_selling_price',
                    'avg_hpp',
                    'total_selling_value',
                    'total_hpp_value',
                    'profit_margin'
                ],
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'sku',
                        'description',
                        'hpp',
                        'selling_price',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Layanan ditemukan'
            ]);

        $responseData = $response->json();
        $this->assertEquals(2, $responseData['stats']['total_services']);
        $this->assertEquals(300000, $responseData['stats']['total_selling_value']);
        $this->assertEquals(230000, $responseData['stats']['total_hpp_value']);
        $this->assertEquals(70000, $responseData['stats']['profit_margin']);
    }

    public function test_returns_message_when_no_services_found()
    {
        $response = $this->withHeaders($this->getAuthHeaders())
            ->getJson($this->apiBaseUrl);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'message' => 'Layanan tidak ditemukan.'
            ]);
    }

    public function test_can_create_a_new_service()
    {
        $serviceData = [
            'name' => 'Test Service',
            'sku' => 'TS001',
            'description' => 'This is a test service',
            'hpp' => 50000,
            'selling_price' => 75000
        ];

        $response = $this->withHeaders($this->getAuthHeaders())
            ->postJson($this->apiBaseUrl, $serviceData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Layanan berhasil dibuat'
            ])
            ->assertJsonFragment($serviceData);

        $this->assertDatabaseHas('services', $serviceData);
    }

    public function test_validates_required_fields_when_creating_service()
    {
        $invalidData = [
            'description' => 'Service without required fields'
        ];

        $response = $this->withHeaders($this->getAuthHeaders())
            ->postJson($this->apiBaseUrl, $invalidData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validasi gagal'
            ])
            ->assertJsonValidationErrors(['name', 'sku']);
    }

    public function test_validates_field_length_when_creating_service()
    {
        $invalidData = [
            'name' => str_repeat('a', 101), 
            'sku' => str_repeat('b', 51),  
            'hpp' => 'not_numeric',
            'selling_price' => 'not_numeric'
        ];

        $response = $this->withHeaders($this->getAuthHeaders())
            ->postJson($this->apiBaseUrl, $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'sku', 'hpp', 'selling_price']);
    }

    public function test_can_show_specific_service()
    {
        $service = Service::factory()->create([
            'name' => 'Test Service',
            'sku' => 'TS001'
        ]);

        $response = $this->withHeaders($this->getAuthHeaders())
            ->getJson($this->apiBaseUrl . "/{$service->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Layanan ditemukan',
                'data' => [
                    'id' => $service->id,
                    'name' => 'Test Service',
                    'sku' => 'TS001'
                ]
            ]);
    }

    public function test_returns_404_when_service_not_found()
    {
        $response = $this->withHeaders($this->getAuthHeaders())
            ->getJson($this->apiBaseUrl . '/999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'layanan tidak ditemukan'
            ]);
    }

    public function test_can_update_existing_service()
    {
        $service = Service::factory()->create([
            'name' => 'Original Service',
            'sku' => 'OS001'
        ]);

        $updateData = [
            'name' => 'Updated Service',
            'sku' => 'US001',
            'selling_price' => 100000
        ];

        $response = $this->withHeaders($this->getAuthHeaders())
            ->putJson($this->apiBaseUrl . "/{$service->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Layanan berhasil diperbarui'
            ]);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'name' => 'Updated Service',
            'sku' => 'US001',
            'selling_price' => 100000
        ]);
    }

    public function test_validates_fields_when_updating_service()
    {
        $service = Service::factory()->create();

        $invalidData = [
            'name' => str_repeat('a', 101),
            'sku' => str_repeat('b', 51),
            'hpp' => 'not_numeric'
        ];

        $response = $this->withHeaders($this->getAuthHeaders())
            ->putJson($this->apiBaseUrl . "/{$service->id}", $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'sku', 'hpp']);
    }

    public function test_can_delete_existing_service()
    {
        $service = Service::factory()->create();

        $response = $this->withHeaders($this->getAuthHeaders())
            ->deleteJson($this->apiBaseUrl . "/{$service->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Layanan berhasil dihapus'
            ]);

        $this->assertDatabaseMissing('services', [
            'id' => $service->id
        ]);
    }

    public function test_returns_404_when_deleting_non_existent_service()
    {
        $response = $this->withHeaders($this->getAuthHeaders())
            ->deleteJson($this->apiBaseUrl . '/999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Layanan tidak ditemukan'
            ]);
    }

    public function test_requires_authentication_for_all_endpoints()
    {
        $service = Service::factory()->create();

        $endpoints = [
            ['method' => 'get', 'url' => $this->apiBaseUrl],
            ['method' => 'post', 'url' => $this->apiBaseUrl],
            ['method' => 'get', 'url' => $this->apiBaseUrl . "/{$service->id}"],
            ['method' => 'put', 'url' => $this->apiBaseUrl . "/{$service->id}"],
            ['method' => 'delete', 'url' => $this->apiBaseUrl . "/{$service->id}"]
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->{$endpoint['method'] . 'Json'}($endpoint['url']);
            $response->assertStatus(401); 
        }
    }

    public function test_can_create_service_with_minimal_required_data()
    {
        $minimalData = [
            'name' => 'Minimal Service',
            'sku' => 'MIN001'
        ];

        $response = $this->withHeaders($this->getAuthHeaders())
            ->postJson($this->apiBaseUrl, $minimalData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Layanan berhasil dibuat'
            ]);

        $this->assertDatabaseHas('services', $minimalData);
    }

    public function test_can_update_service_with_partial_data()
    {
        $service = Service::factory()->create([
            'name' => 'Original Service',
            'sku' => 'OS001',
            'selling_price' => 50000
        ]);

        $partialUpdate = [
            'name' => 'Partially Updated Service'
        ];

        $response = $this->withHeaders($this->getAuthHeaders())
            ->putJson($this->apiBaseUrl . "/{$service->id}", $partialUpdate);

        $response->assertStatus(200);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'name' => 'Partially Updated Service',
            'sku' => 'OS001', 
            'selling_price' => 50000
        ]);
    }
}