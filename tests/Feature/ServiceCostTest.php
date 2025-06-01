<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;
use App\Models\Service;
use App\Models\ServiceCost;
use App\Models\CostComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tymon\JWTAuth\Facades\JWTAuth;

class ServiceCostTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $token;
    protected $apiBaseUrl = '/api/service-cost';
    protected $headers;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->token = JWTAuth::fromUser($this->user);
        
        $this->headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_can_display_service_costs_index()
    {
        $service = Service::factory()->create();
        $costComponent = CostComponent::factory()->create();
        
        ServiceCost::factory()->create([
            'service_id' => $service->id,
            'cost_component_id' => $costComponent->id,
            'unit' => 'kg',
            'unit_price' => 10000,
            'quantity' => 2,
            'conversion_qty' => 1,
            'amount' => 20000
        ]);

        $response = $this->getJson($this->apiBaseUrl, $this->headers);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Komponen biaya Layanan berhasil ditampilkan!'
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'service',
                'all_services',
                'data' => [
                    '*' => [
                        'id',
                        'service_id',
                        'cost_component_id',
                        'unit',
                        'unit_price',
                        'quantity',
                        'conversion_qty',
                        'amount',
                        'service',
                        'cost_component'
                    ]
                ]
            ]);
    }

    public function test_returns_empty_message_when_no_services_exist()
    {
        $response = $this->getJson($this->apiBaseUrl, $this->headers);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'message' => 'Layanan yang terhubung masih kosong'
            ]);
    }

    public function test_returns_empty_message_when_service_has_no_costs()
    {
        Service::factory()->create();

        $response = $this->getJson($this->apiBaseUrl, $this->headers);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'message' => 'Komponen biaya untuk Layanan ini masih kosong'
            ]);
    }

    public function test_can_filter_service_costs_by_service_id()
    {
        $service1 = Service::factory()->create();
        $service2 = Service::factory()->create();
        $costComponent = CostComponent::factory()->create();
        
        ServiceCost::factory()->create([
            'service_id' => $service1->id,
            'cost_component_id' => $costComponent->id
        ]);
        
        ServiceCost::factory()->create([
            'service_id' => $service2->id,
            'cost_component_id' => $costComponent->id
        ]);

        $response = $this->getJson($this->apiBaseUrl . '?service_id=' . $service2->id, $this->headers);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'service' => [
                    'id' => $service2->id
                ]
            ]);
    }

    public function test_can_store_service_costs_successfully()
    {
        $service = Service::factory()->create();
        $costComponent1 = CostComponent::factory()->create();
        $costComponent2 = CostComponent::factory()->create();

        $data = [
            'service_id' => $service->id,
            'costs' => [
                [
                    'cost_component_id' => $costComponent1->id,
                    'unit' => 'kg',
                    'unit_price' => 15000,
                    'quantity' => 2,
                    'conversion_qty' => 1
                ],
                [
                    'cost_component_id' => $costComponent2->id,
                    'unit' => 'liter',
                    'unit_price' => 8000,
                    'quantity' => 3,
                    'conversion_qty' => 1
                ]
            ]
        ];

        $response = $this->postJson($this->apiBaseUrl, $data, $this->headers);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'HPP berhasil disimpan'
            ]);

        $this->assertDatabaseHas('service_costs', [
            'service_id' => $service->id,
            'cost_component_id' => $costComponent1->id,
            'unit' => 'kg',
            'unit_price' => 15000,
            'quantity' => 2,
            'amount' => 30000
        ]);

        $service->refresh();
        $this->assertEquals(54000, $service->hpp); 
    }

    public function test_validates_required_fields_when_storing()
    {
        $data = [
            'service_id' => '',
            'costs' => []
        ];

        $response = $this->postJson($this->apiBaseUrl, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validasi gagal'
            ])
            ->assertJsonValidationErrors(['service_id', 'costs']);
    }

    public function test_rejects_non_existent_service_when_storing()
    {
        $costComponent = CostComponent::factory()->create();
        
        $data = [
            'service_id' => 99999,
            'costs' => [
                [
                    'cost_component_id' => $costComponent->id,
                    'unit' => 'kg',
                    'unit_price' => 15000,
                    'quantity' => 2,
                    'conversion_qty' => 1
                ]
            ]
        ];

        $response = $this->postJson($this->apiBaseUrl, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['service_id']);
    }

    public function test_rejects_duplicate_cost_components_in_same_request()
    {
        $service = Service::factory()->create();
        $costComponent = CostComponent::factory()->create();

        $data = [
            'service_id' => $service->id,
            'costs' => [
                [
                    'cost_component_id' => $costComponent->id,
                    'unit' => 'kg',
                    'unit_price' => 15000,
                    'quantity' => 2,
                    'conversion_qty' => 1
                ],
                [
                    'cost_component_id' => $costComponent->id,
                    'unit' => 'liter',
                    'unit_price' => 8000,
                    'quantity' => 3,
                    'conversion_qty' => 1
                ]
            ]
        ];

        $response = $this->postJson($this->apiBaseUrl, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Terdapat komponen biaya yang diinput lebih dari satu kali'
            ]);
    }

    public function test_rejects_existing_cost_components_for_service()
    {
        $service = Service::factory()->create();
        $costComponent = CostComponent::factory()->create();
        
        ServiceCost::factory()->create([
            'service_id' => $service->id,
            'cost_component_id' => $costComponent->id
        ]);

        $data = [
            'service_id' => $service->id,
            'costs' => [
                [
                    'cost_component_id' => $costComponent->id,
                    'unit' => 'kg',
                    'unit_price' => 15000,
                    'quantity' => 2,
                    'conversion_qty' => 1
                ]
            ]
        ];

        $response = $this->postJson($this->apiBaseUrl, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Komponen biaya sudah ada dalam Layanan ini'
            ]);
    }

    public function test_can_show_specific_service_cost()
    {
        $service = Service::factory()->create();
        $costComponent = CostComponent::factory()->create();
        
        $serviceCost = ServiceCost::factory()->create([
            'service_id' => $service->id,
            'cost_component_id' => $costComponent->id
        ]);

        $response = $this->getJson($this->apiBaseUrl . '/' . $serviceCost->id, $this->headers);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Detail komponen biaya produk'
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'service_id',
                    'cost_component_id',
                    'unit',
                    'unit_price',
                    'quantity',
                    'conversion_qty',
                    'amount',
                    'service',
                    'cost_component'
                ]
            ]);
    }

    public function test_returns_empty_object_when_service_cost_not_found()
    {
        $response = $this->getJson($this->apiBaseUrl . '/99999', $this->headers);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Layanan belum memiliki komponen biaya',
                'data' => [] 
            ]);
    }

    public function test_can_update_service_cost_successfully()
    {
        $service = Service::factory()->create();
        $costComponent1 = CostComponent::factory()->create();
        $costComponent2 = CostComponent::factory()->create();
        
        $serviceCost = ServiceCost::factory()->create([
            'service_id' => $service->id,
            'cost_component_id' => $costComponent1->id,
            'unit' => 'kg',
            'unit_price' => 10000,
            'quantity' => 2,
            'conversion_qty' => 1,
            'amount' => 20000
        ]);

        $data = [
            'cost_component_id' => $costComponent2->id,
            'unit' => 'liter',
            'unit_price' => 15000,
            'quantity' => 3,
            'conversion_qty' => 2
        ];

        $response = $this->putJson($this->apiBaseUrl . '/' . $serviceCost->id, $data, $this->headers);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Komponen biaya berhasil diperbarui'
            ]);

        $this->assertDatabaseHas('service_costs', [
            'id' => $serviceCost->id,
            'cost_component_id' => $costComponent2->id,
            'unit' => 'liter',
            'unit_price' => 15000,
            'quantity' => 3,
            'conversion_qty' => 2,
            'amount' => 22500 
        ]);
    }

    public function test_validates_required_fields_when_updating()
    {
        $serviceCost = ServiceCost::factory()->create();

        $data = [
            'cost_component_id' => '',
            'unit' => '',
            'unit_price' => '',
            'quantity' => '',
            'conversion_qty' => ''
        ];

        $response = $this->putJson($this->apiBaseUrl . '/' . $serviceCost->id, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validasi gagal'
            ])
            ->assertJsonValidationErrors([
                'cost_component_id',
                'unit',
                'unit_price',
                'quantity',
                'conversion_qty'
            ]);
    }

    public function test_rejects_updating_to_existing_cost_component()
    {
        $service = Service::factory()->create();
        $costComponent1 = CostComponent::factory()->create();
        $costComponent2 = CostComponent::factory()->create();
        
        $serviceCost1 = ServiceCost::factory()->create([
            'service_id' => $service->id,
            'cost_component_id' => $costComponent1->id
        ]);
        
        $serviceCost2 = ServiceCost::factory()->create([
            'service_id' => $service->id,
            'cost_component_id' => $costComponent2->id
        ]);

        $data = [
            'cost_component_id' => $costComponent2->id,
            'unit' => 'kg',
            'unit_price' => 15000,
            'quantity' => 2,
            'conversion_qty' => 1
        ];

        $response = $this->putJson($this->apiBaseUrl . '/' . $serviceCost1->id, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Komponen biaya ini sudah ada dalam produk'
            ]);
    }

    public function test_can_delete_service_cost_successfully()
    {
        $service = Service::factory()->create();
        $costComponent = CostComponent::factory()->create();
        
        $serviceCost = ServiceCost::factory()->create([
            'service_id' => $service->id,
            'cost_component_id' => $costComponent->id,
            'amount' => 25000
        ]);

        $response = $this->deleteJson($this->apiBaseUrl . '/' . $serviceCost->id, [], $this->headers);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Komponen biaya berhasil dihapus'
            ]);

        $this->assertDatabaseMissing('service_costs', [
            'id' => $serviceCost->id
        ]);

        $service->refresh();
        $this->assertEquals(0, $service->hpp);
    }

    public function test_throws_exception_when_deleting_non_existent_service_cost()
    {
        $response = $this->deleteJson($this->apiBaseUrl . '/99999', [], $this->headers);
        
        $response->assertStatus(404);
    }

    public function test_calculates_hpp_correctly_with_conversion_quantity()
    {
        $service = Service::factory()->create();
        $costComponent = CostComponent::factory()->create();

        $data = [
            'service_id' => $service->id,
            'costs' => [
                [
                    'cost_component_id' => $costComponent->id,
                    'unit' => 'gram',
                    'unit_price' => 20000, 
                    'quantity' => 500, 
                    'conversion_qty' => 1000 
                ]
            ]
        ];

        $response = $this->postJson($this->apiBaseUrl, $data, $this->headers);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('service_costs', [
            'service_id' => $service->id,
            'amount' => 10000 
        ]);

        $service->refresh();
        $this->assertEquals(10000, $service->hpp);
    }

    public function test_handles_zero_conversion_quantity_correctly()
    {
        $service = Service::factory()->create();
        $costComponent = CostComponent::factory()->create();

        $data = [
            'service_id' => $service->id,
            'costs' => [
                [
                    'cost_component_id' => $costComponent->id,
                    'unit' => 'pcs',
                    'unit_price' => 5000,
                    'quantity' => 3,
                    'conversion_qty' => 0
                ]
            ]
        ];

        $response = $this->postJson($this->apiBaseUrl, $data, $this->headers);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('service_costs', [
            'service_id' => $service->id,
            'amount' => 15000 
        ]);
    }

    public function test_recalculates_hpp_after_operations()
    {
        $service = Service::factory()->create();
        $costComponent1 = CostComponent::factory()->create();
        $costComponent2 = CostComponent::factory()->create();
        
        $serviceCost1 = ServiceCost::factory()->create([
            'service_id' => $service->id,
            'cost_component_id' => $costComponent1->id,
            'amount' => 15000
        ]);

        $data = [
            'service_id' => $service->id,
            'costs' => [
                [
                    'cost_component_id' => $costComponent2->id,
                    'unit' => 'liter',
                    'unit_price' => 8000,
                    'quantity' => 2,
                    'conversion_qty' => 1
                ]
            ]
        ];

        $this->postJson($this->apiBaseUrl, $data, $this->headers)->assertStatus(201);
        $service->refresh();
        $this->assertEquals(31000, $service->hpp);

        $this->deleteJson($this->apiBaseUrl . '/' . $serviceCost1->id, [], $this->headers)->assertStatus(200);
        $service->refresh();
        $this->assertEquals(16000, $service->hpp); 
    }
}