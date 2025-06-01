<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Service;
use App\Models\PriceSchema;
use App\Observers\PriceSchemaObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class PriceSchemeTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $apiBaseUrl = '/api/price-schemes';
    protected $user;
    protected $token;
    protected $headers;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->token = JWTAuth::fromUser($this->user);
        
        $this->headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
        
        $this->service = Service::factory()->create([
            'hpp' => 100000,
            'selling_price' => 100000
        ]);
        
        PriceSchema::observe(PriceSchemaObserver::class);
    }

    private function calculateSellingPrice($price, $discountValue)
    {
        $discountPercentage = min($discountValue, 99.99);
        $result = $price / ((100 - $discountPercentage) / 100);
        
        return round($result, 2);
    }

    public function test_index_returns_price_schemas_for_default_service()
    {
        $schema1 = PriceSchema::factory()->create([
            'service_id' => $this->service->id,
            'level_order' => 1,
            'level_name' => 'Level 1',
            'purchase_price' => 100000,
            'selling_price' => 120000,
            'discount_percentage' => 16.67
        ]);
        
        $schema2 = PriceSchema::factory()->create([
            'service_id' => $this->service->id,
            'level_order' => 2,
            'level_name' => 'Level 2',
            'purchase_price' => 120000,
            'selling_price' => 150000,
            'discount_percentage' => 20
        ]);

        $response = $this->withHeaders($this->headers)->get($this->apiBaseUrl);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Daftar skema harga berhasil ditampilkan'
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'service_id',
                        'level_name',
                        'level_order',
                        'discount_percentage',
                        'purchase_price',
                        'selling_price',
                        'profit_amount',
                        'notes',
                        'service'
                    ]
                ],
                'service',
                'all_services'
            ]);

        $responseData = $response->json();
        $this->assertEquals(2, count($responseData['data']));
    }

    public function test_index_with_specific_service_id()
    {
        $service2 = Service::factory()->create();
        
        PriceSchema::factory()->create([
            'service_id' => $this->service->id,
            'level_order' => 1
        ]);
        
        PriceSchema::factory()->create([
            'service_id' => $service2->id,
            'level_order' => 1
        ]);

        $response = $this->withHeaders($this->headers)->get($this->apiBaseUrl . '?service_id=' . $service2->id);

        $response->assertStatus(200);
        
        $responseData = $response->json();
        $this->assertEquals(1, count($responseData['data']));
        $this->assertEquals($service2->id, $responseData['data'][0]['service_id']);
    }

    public function test_index_returns_error_when_no_services_available()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Service::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $response = $this->withHeaders($this->headers)->get($this->apiBaseUrl);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'message' => 'Layanan yang terhubung masih kosong'
            ]);
    }

    public function test_index_returns_error_for_invalid_service_id()
    {
        $response = $this->withHeaders($this->headers)->get($this->apiBaseUrl . '?service_id=999');

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'message' => 'Layanan tidak ditemukan'
            ]);
    }

    public function test_store_creates_first_level_schema_with_discount_percentage()
    {
        $schemaData = [
            'service_id' => $this->service->id,
            'level_name' => 'Level 1',
            'discount_percentage' => 20,
            'notes' => 'First level schema'
        ];

        $response = $this->withHeaders($this->headers)->post($this->apiBaseUrl, $schemaData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Skema harga berhasil disimpan'
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'service_id',
                    'level_name',
                    'level_order',
                    'discount_percentage',
                    'purchase_price',
                    'selling_price',
                    'profit_amount',
                    'notes'
                ]
            ]);

        $responseData = $response->json();
        $this->assertEquals(1, $responseData['data']['level_order']);
        $this->assertEquals(100000, $responseData['data']['purchase_price']);
        $this->assertEquals(20, $responseData['data']['discount_percentage']);
        
        $this->service->refresh();
        $this->assertEquals($responseData['data']['selling_price'], $this->service->selling_price);
    }

    public function test_store_creates_first_level_schema_with_selling_price()
    {
        $schemaData = [
            'service_id' => $this->service->id,
            'level_name' => 'Level 1',
            'selling_price' => 150000,
            'notes' => 'First level with selling price'
        ];

        $response = $this->withHeaders($this->headers)->post($this->apiBaseUrl, $schemaData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Skema harga berhasil disimpan'
            ]);

        $responseData = $response->json();
        $this->assertEquals(150000, $responseData['data']['selling_price']);
        $this->assertEquals(100000, $responseData['data']['purchase_price']);
        $this->assertEquals(50000, $responseData['data']['profit_amount']);
    }

    public function test_store_creates_second_level_schema()
    {
        $firstSchema = PriceSchema::factory()->create([
            'service_id' => $this->service->id,
            'level_order' => 1,
            'selling_price' => 120000
        ]);

        $schemaData = [
            'service_id' => $this->service->id,
            'level_name' => 'Level 2',
            'discount_percentage' => 25,
            'notes' => 'Second level schema'
        ];

        $response = $this->withHeaders($this->headers)->post($this->apiBaseUrl, $schemaData);

        $response->assertStatus(201);

        $responseData = $response->json();
        $this->assertEquals(2, $responseData['data']['level_order']);
        $this->assertEquals(120000, $responseData['data']['purchase_price']);
    }

    public function test_store_fails_with_invalid_service_id()
    {
        $schemaData = [
            'service_id' => 999,
            'level_name' => 'Level 1',
            'discount_percentage' => 20
        ];

        $response = $this->withHeaders($this->headers)->post($this->apiBaseUrl, $schemaData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validasi gagal'
            ]);
    }

    public function test_store_fails_with_validation_errors()
    {
        $schemaData = [
            'service_id' => $this->service->id,
            'level_name' => '',
            'purchase_price' => -100
        ];

        $response = $this->withHeaders($this->headers)->post($this->apiBaseUrl, $schemaData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validasi gagal'
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ]);
    }

    public function test_store_fails_without_purchase_price_for_first_level()
    {
        $serviceWithoutHpp = Service::factory()->create(['hpp' => null]);

        $schemaData = [
            'service_id' => $serviceWithoutHpp->id,
            'level_name' => 'Level 1',
            'discount_percentage' => 20
        ];

        $response = $this->withHeaders($this->headers)->post($this->apiBaseUrl, $schemaData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Harga pembelian (purchase price) diperlukan untuk skema harga pertama'
            ]);
    }

    public function test_show_returns_existing_schema()
    {
        $schema = PriceSchema::factory()->create([
            'service_id' => $this->service->id
        ]);

        $response = $this->withHeaders($this->headers)->get($this->apiBaseUrl . '/' . $schema->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Detail skema harga'
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'service_id',
                    'level_name',
                    'level_order',
                    'discount_percentage',
                    'purchase_price',
                    'selling_price',
                    'profit_amount',
                    'notes'
                ]
            ]);
    }

    public function test_show_returns_404_for_non_existent_schema()
    {
        $response = $this->withHeaders($this->headers)->get($this->apiBaseUrl . '/999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Skema harga belum ditambahkan untuk Layanan ini'
            ]);
    }

    public function test_update_changes_level_name_successfully()
    {
        $schema = PriceSchema::factory()->create([
            'service_id' => $this->service->id,
            'level_name' => 'Old Level'
        ]);

        $updateData = [
            'level_name' => 'New Level Name'
        ];

        $response = $this->withHeaders($this->headers)->put($this->apiBaseUrl . '/' . $schema->id, $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Skema harga berhasil diperbarui'
            ]);

        $this->assertDatabaseHas('price_schemas', [
            'id' => $schema->id,
            'level_name' => 'New Level Name'
        ]);
    }

    public function test_update_changes_discount_percentage()
    {
        $schema = PriceSchema::factory()->create([
            'service_id' => $this->service->id,
            'purchase_price' => 100000,
            'discount_percentage' => 20
        ]);

        $updateData = [
            'discount_percentage' => 30
        ];

        $response = $this->withHeaders($this->headers)->put($this->apiBaseUrl . '/' . $schema->id, $updateData);

        $response->assertStatus(200);

        $schema->refresh();
        $this->assertEquals(30, $schema->discount_percentage);
        
        $expectedSellingPrice = $this->calculateSellingPrice(100000, 30);
        $this->assertEquals($expectedSellingPrice, $schema->selling_price);
    }

    public function test_update_changes_selling_price()
    {
        $schema = PriceSchema::factory()->create([
            'service_id' => $this->service->id,
            'purchase_price' => 100000,
            'selling_price' => 120000
        ]);

        $updateData = [
            'selling_price' => 150000
        ];

        $response = $this->withHeaders($this->headers)->put($this->apiBaseUrl . '/' . $schema->id, $updateData);

        $response->assertStatus(200);

        $schema->refresh();
        $this->assertEquals(150000, $schema->selling_price);
        $this->assertEquals(50000, $schema->profit_amount);
    }

    public function test_update_changes_level_order()
    {
        $schema1 = PriceSchema::factory()->create([
            'service_id' => $this->service->id,
            'level_order' => 1,
            'level_name' => 'Level 1',
            'purchase_price' => 100000,
            'selling_price' => 120000
        ]);
        
        $schema2 = PriceSchema::factory()->create([
            'service_id' => $this->service->id,
            'level_order' => 2,
            'level_name' => 'Level 2',
            'purchase_price' => 120000,
            'selling_price' => 150000
        ]);
        
        $schema3 = PriceSchema::factory()->create([
            'service_id' => $this->service->id,
            'level_order' => 3,
            'level_name' => 'Level 3',
            'purchase_price' => 150000,
            'selling_price' => 180000
        ]);

        $updateData = [
            'level_order' => 1
        ];

        $response = $this->withHeaders($this->headers)->put($this->apiBaseUrl . '/' . $schema3->id, $updateData);

        $response->assertStatus(200);

        $schema1->refresh();
        $schema2->refresh();
        $schema3->refresh();
        
        $this->assertEquals(1, $schema3->level_order); 
        $this->assertEquals(2, $schema1->level_order); 
        $this->assertEquals(3, $schema2->level_order); 
    }

    public function test_update_changes_level_order_move_down()
    {
        $schema1 = PriceSchema::factory()->create([
            'service_id' => $this->service->id,
            'level_order' => 1,
            'level_name' => 'Level 1',
            'selling_price' => 120000
        ]);
        
        $schema2 = PriceSchema::factory()->create([
            'service_id' => $this->service->id,
            'level_order' => 2,
            'level_name' => 'Level 2',
            'selling_price' => 150000
        ]);
        
        $schema3 = PriceSchema::factory()->create([
            'service_id' => $this->service->id,
            'level_order' => 3,
            'level_name' => 'Level 3',
            'selling_price' => 180000
        ]);

        $updateData = [
            'level_order' => 3
        ];

        $response = $this->withHeaders($this->headers)->put($this->apiBaseUrl . '/' . $schema1->id, $updateData);

        $response->assertStatus(200);

        $schema1->refresh();
        $schema2->refresh();
        $schema3->refresh();
        
        $this->assertEquals(3, $schema1->level_order);
        $this->assertEquals(1, $schema2->level_order);
        $this->assertEquals(2, $schema3->level_order);
    }

    public function test_update_returns_404_for_non_existent_schema()
    {
        $updateData = [
            'level_name' => 'New Name'
        ];

        $response = $this->withHeaders($this->headers)->put($this->apiBaseUrl . '/999', $updateData);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Skema harga tidak ditemukan'
            ]);
    }

    public function test_update_fails_with_validation_errors()
    {
        $schema = PriceSchema::factory()->create([
            'service_id' => $this->service->id
        ]);

        $updateData = [
            'purchase_price' => -100
        ];

        $response = $this->withHeaders($this->headers)->put($this->apiBaseUrl . '/' . $schema->id, $updateData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validasi gagal'
            ]);
    }

    public function test_destroy_deletes_schema_successfully()
    {
        $schema = PriceSchema::factory()->create([
            'service_id' => $this->service->id
        ]);

        $response = $this->withHeaders($this->headers)->delete($this->apiBaseUrl . '/' . $schema->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Skema harga berhasil dihapus'
            ]);

        $this->assertDatabaseMissing('price_schemas', [
            'id' => $schema->id
        ]);
    }

    public function test_destroy_middle_level_schema_updates_chain()
    {
        $schema1 = PriceSchema::factory()->create([
            'service_id' => $this->service->id,
            'level_order' => 1,
            'selling_price' => 120000
        ]);
        
        $schema2 = PriceSchema::factory()->create([
            'service_id' => $this->service->id,
            'level_order' => 2,
            'purchase_price' => 120000,
            'selling_price' => 150000
        ]);
        
        $schema3 = PriceSchema::factory()->create([
            'service_id' => $this->service->id,
            'level_order' => 3,
            'purchase_price' => 150000,
            'selling_price' => 180000
        ]);

        $response = $this->withHeaders($this->headers)->delete($this->apiBaseUrl . '/' . $schema2->id);

        $response->assertStatus(200);

        $schema3->refresh();
        $this->assertEquals(120000, $schema3->purchase_price);
        $this->assertEquals(2, $schema3->level_order);
    }

    public function test_destroy_first_level_schema_updates_next_schema()
    {
        $schema1 = PriceSchema::factory()->create([
            'service_id' => $this->service->id,
            'level_order' => 1,
            'selling_price' => 120000
        ]);
        
        $schema2 = PriceSchema::factory()->create([
            'service_id' => $this->service->id,
            'level_order' => 2,
            'purchase_price' => 120000,
            'selling_price' => 150000
        ]);

        $response = $this->withHeaders($this->headers)->delete($this->apiBaseUrl . '/' . $schema1->id);

        $response->assertStatus(200);

        $schema2->refresh();
        $this->assertEquals($this->service->hpp, $schema2->purchase_price);
        $this->assertEquals(1, $schema2->level_order);
    }

    public function test_destroy_returns_404_for_non_existent_schema()
    {
        $response = $this->withHeaders($this->headers)->delete($this->apiBaseUrl . '/999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Skema harga tidak ditemukan'
            ]);
    }

    public function test_observer_updates_service_selling_price_on_create()
    {
        $initialSellingPrice = $this->service->selling_price;

        PriceSchema::factory()->create([
            'service_id' => $this->service->id,
            'level_order' => 1,
            'selling_price' => 150000
        ]);

        $this->service->refresh();
        $this->assertEquals(150000, $this->service->selling_price);
        $this->assertNotEquals($initialSellingPrice, $this->service->selling_price);
    }

    public function test_observer_updates_service_selling_price_on_update()
    {
        $schema = PriceSchema::factory()->create([
            'service_id' => $this->service->id,
            'level_order' => 1,
            'selling_price' => 150000
        ]);

        $this->service->refresh();
        $this->assertEquals(150000, $this->service->selling_price);

        $schema->update(['selling_price' => 200000]);

        $this->service->refresh();
        $this->assertEquals(200000, $this->service->selling_price);
    }

    public function test_observer_updates_service_selling_price_on_delete()
    {
        $schema = PriceSchema::factory()->create([
            'service_id' => $this->service->id,
            'level_order' => 1,
            'selling_price' => 150000
        ]);

        $this->service->refresh();
        $this->assertEquals(150000, $this->service->selling_price);

        $schema->delete();

        $this->service->refresh();
        $this->assertEquals($this->service->hpp, $this->service->selling_price);
    }

    public function test_unauthorized_access_returns_401()
    {
        $response = $this->get($this->apiBaseUrl);

        $response->assertStatus(401);
    }

    public function test_invalid_token_returns_401()
    {
        $headers = [
            'Authorization' => 'Bearer invalid-token',
            'Accept' => 'application/json',
        ];

        $response = $this->withHeaders($headers)->get($this->apiBaseUrl);

        $response->assertStatus(401);
    }
}