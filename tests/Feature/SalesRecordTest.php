<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Service;
use App\Models\SalesRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;

class SalesRecordTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $apiBaseUrl = '/api/sales';
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

        $this->service = Service::factory()->create();
    }

    public function test_index_returns_sales_records_successfully()
    {
        $year = Carbon::now()->year;
        $month = Carbon::now()->month;

        SalesRecord::factory()->count(3)->create([
            'service_id' => $this->service->id,
            'year' => $year,
            'month' => $month,
        ]);

        SalesRecord::factory()->create([
            'service_id' => $this->service->id,
            'year' => $year - 1,
            'month' => $month,
        ]);

        $response = $this->getJson($this->apiBaseUrl . "?year={$year}&month={$month}", $this->headers);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'month',
                        'year',
                        'date',
                        'profit_unit',
                        'profit_percentage',
                        'sub_total',
                        'profit',
                        'service_name',
                        'service_sku',
                        'profit_contribution_percentage',
                    ]
                ],
                'summary' => [
                    'total_sales',
                    'total_profit',
                    'total_profit_percentage',
                ],
                'filters' => [
                    'available_years',
                    'available_months',
                    'current_year',
                    'current_month',
                ]
            ])
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('success', true)
            ->assertJsonPath('filters.current_year', $year)
            ->assertJsonPath('filters.current_month', $month);
    }

    public function test_index_returns_validation_error_for_invalid_year()
    {
        $response = $this->getJson($this->apiBaseUrl . '?year=invalid', $this->headers);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['year']);
    }

    public function test_index_returns_validation_error_for_invalid_month()
    {
        $response = $this->getJson($this->apiBaseUrl . '?month=13', $this->headers);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['month']);
    }

    public function test_store_creates_new_sales_record_successfully()
    {
        $payload = [
            'service_id' => $this->service->id,
            'name' => $this->faker->sentence,
            'month' => Carbon::now()->month,
            'year' => Carbon::now()->year,
            'date' => Carbon::now()->day,
            'hpp' => 100000,
            'selling_price' => 150000,
        ];

        $response = $this->postJson($this->apiBaseUrl, $payload, $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Penjualan berhasil disimpan')
            ->assertJsonPath('data.name', $payload['name']);

        $this->assertDatabaseHas('sales_records', [
            'name' => $payload['name'],
            'service_id' => $payload['service_id'],
        ]);
    }

    public function test_store_returns_validation_error_for_missing_required_fields()
    {
        $response = $this->postJson($this->apiBaseUrl, [], $this->headers);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['service_id', 'name', 'month', 'year', 'date', 'hpp', 'selling_price']);
    }

    public function test_store_returns_error_if_service_not_found()
    {
         $payload = [
            'service_id' => 999, // Non-existent service
            'name' => $this->faker->sentence,
            'month' => Carbon::now()->month,
            'year' => Carbon::now()->year,
            'date' => Carbon::now()->day,
            'hpp' => 100000,
            'selling_price' => 150000,
        ];
        $response = $this->postJson($this->apiBaseUrl, $payload, $this->headers);
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['service_id']);
    }


    public function test_show_returns_sales_record_successfully()
    {
        $salesRecord = SalesRecord::factory()->create(['service_id' => $this->service->id]);

        $response = $this->getJson("{$this->apiBaseUrl}/{$salesRecord->id}", $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $salesRecord->id)
            ->assertJsonPath('data.name', $salesRecord->name)
            ->assertJsonPath('data.service_name', $this->service->name)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'service_id',
                    'month',
                    'year',
                    'date',
                    'hpp',
                    'selling_price',
                    'created_at',
                    'updated_at',
                    'service_name',
                    'profit_unit',
                    'profit_percentage',
                    'profit_contribution_percentage'
                ]
            ]);
    }

    public function test_show_returns_not_found_for_invalid_id()
    {
        $response = $this->getJson("{$this->apiBaseUrl}/99999", $this->headers);
        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Penjualan Tidak ditemukan!');
    }

    public function test_update_modifies_sales_record_successfully()
    {
        $salesRecord = SalesRecord::factory()->create(['service_id' => $this->service->id,'hpp' => 100000]);
        $newService = Service::factory()->create();

        $payload = [
            'name' => 'Updated Sales Record Name',
            'hpp' => '110000.00', 
            'selling_price' => '150000.00', 
            'service_id' => $newService->id,
        ];

        $response = $this->putJson("{$this->apiBaseUrl}/{$salesRecord->id}", $payload, $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Penjualan berhasil diperbarui')
            ->assertJsonPath('data.name', $payload['name'])
            ->assertJsonPath('data.selling_price', $payload['selling_price'])
            ->assertJsonPath('data.service_id', $newService->id);

        $this->assertDatabaseHas('sales_records', [
            'id' => $salesRecord->id,
            'name' => $payload['name'],
            'selling_price' => $payload['selling_price'],
            'service_id' => $newService->id,
        ]);
    }

    public function test_update_returns_not_found_for_invalid_id()
    {
        $payload = ['name' => 'Test Update'];
        $response = $this->putJson("{$this->apiBaseUrl}/99999", $payload, $this->headers);
        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Penjualan tidak ditemukan');
    }

    public function test_update_returns_validation_error_for_invalid_data()
    {
        $salesRecord = SalesRecord::factory()->create(['service_id' => $this->service->id]);
        $payload = ['month' => 13, 'year' => 'invalid'];

        $response = $this->putJson("{$this->apiBaseUrl}/{$salesRecord->id}", $payload, $this->headers);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['month', 'year']);
    }
    
    public function test_update_returns_error_if_updated_service_not_found()
    {
        $salesRecord = SalesRecord::factory()->create(['service_id' => $this->service->id]);
        $payload = [
            'service_id' => 999, // Non-existent service
        ];

        $response = $this->putJson("{$this->apiBaseUrl}/{$salesRecord->id}", $payload, $this->headers);
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['service_id']);
    }

    public function test_destroy_deletes_sales_record_successfully()
    {
        $salesRecord = SalesRecord::factory()->create(['service_id' => $this->service->id]);

        $response = $this->deleteJson("{$this->apiBaseUrl}/{$salesRecord->id}", [], $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Penjualan berhasil dihapus');

        $this->assertDatabaseMissing('sales_records', ['id' => $salesRecord->id]);
    }

    public function test_destroy_returns_not_found_for_invalid_id()
    {
        $response = $this->deleteJson("{$this->apiBaseUrl}/99999", [], $this->headers);
        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Penjualan tidak ditemukan');
    }
}