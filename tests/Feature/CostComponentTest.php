<?php

namespace Tests\Feature;

use App\Models\CostComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class CostComponentTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $apiBaseUrl = '/api/cost-components';
    protected $user;
    protected $token;
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

    public function test_can_get_all_cost_components()
    {
        CostComponent::factory()->count(3)->create();

        $response = $this->getJson($this->apiBaseUrl, $this->headers);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         '*' => [
                             'id',
                             'name',
                             'description',
                             'component_type'
                         ]
                     ],
                     'meta' => [
                         'total_count'
                     ]
                 ])
                 ->assertJson([
                     'success' => true,
                     'message' => 'Daftar komponen biaya'
                 ]);

        $this->assertEquals(3, $response->json('meta.total_count'));
    }

    public function test_can_filter_cost_components_by_type()
    {
        CostComponent::factory()->create(['component_type' => 'direct_material']);
        CostComponent::factory()->create(['component_type' => 'indirect_material']);
        CostComponent::factory()->create(['component_type' => 'direct_labor']);

        $response = $this->getJson($this->apiBaseUrl . '?type=direct_material', $this->headers);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Daftar komponen biaya tipe direct_material',
                     'meta' => [
                         'type' => 'direct_material',
                         'total_count' => 1
                     ]
                 ]);

        $this->assertEquals('direct_material', $response->json('data.0.component_type'));
    }

    public function test_returns_error_for_invalid_type_filter()
    {
        $response = $this->getJson($this->apiBaseUrl . '?type=invalid_type', $this->headers);

        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Tipe komponen biaya tidak valid'
                 ]);
    }

    public function test_can_search_cost_components_by_keyword()
    {
        CostComponent::factory()->create([
            'name' => 'Material Besi',
            'description' => 'Besi untuk konstruksi'
        ]);
        CostComponent::factory()->create([
            'name' => 'Material Kayu',
            'description' => 'Kayu untuk furniture'
        ]);

        $response = $this->getJson($this->apiBaseUrl . '?keyword=Besi', $this->headers);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => "Daftar komponen biaya sesuai pencarian 'Besi'",
                     'meta' => [
                         'keyword' => 'Besi',
                         'total_count' => 1
                     ]
                 ]);

        $this->assertStringContainsString('Besi', $response->json('data.0.name'));
    }

    public function test_can_search_cost_components_by_description()
    {
        CostComponent::factory()->create([
            'name' => 'Material A',
            'description' => 'Material untuk konstruksi bangunan'
        ]);
        CostComponent::factory()->create([
            'name' => 'Material B',
            'description' => 'Material untuk furniture'
        ]);

        $response = $this->getJson($this->apiBaseUrl . '?keyword=konstruksi', $this->headers);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'meta' => [
                         'keyword' => 'konstruksi',
                         'total_count' => 1
                     ]
                 ]);
    }

    public function test_returns_error_for_empty_keyword()
    {
        $response = $this->getJson($this->apiBaseUrl . '?keyword=', $this->headers);

        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Parameter pencarian (keyword) tidak boleh kosong'
                 ]);
    }

    public function test_can_combine_type_and_keyword_filters()
    {
        CostComponent::factory()->create([
            'name' => 'Besi Direct',
            'component_type' => 'direct_material'
        ]);
        CostComponent::factory()->create([
            'name' => 'Besi Indirect',
            'component_type' => 'indirect_material'
        ]);

        $response = $this->getJson($this->apiBaseUrl . '?type=direct_material&keyword=Besi', $this->headers);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => "Daftar komponen biaya tipe direct_material sesuai pencarian 'Besi'",
                     'meta' => [
                         'type' => 'direct_material',
                         'keyword' => 'Besi',
                         'total_count' => 1
                     ]
                 ]);
    }

    public function test_can_create_cost_component()
    {
        $data = [
            'name' => 'Material Test',
            'description' => 'Deskripsi material test',
            'component_type' => 'direct_material'
        ];

        $response = $this->postJson($this->apiBaseUrl, $data, $this->headers);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'id',
                         'name',
                         'description',
                         'component_type'
                     ]
                 ])
                 ->assertJson([
                     'success' => true,
                     'message' => 'Komponen Biaya Berhasil dibuat',
                     'data' => $data
                 ]);

        $this->assertDatabaseHas('cost_components', $data);
    }

    public function test_can_create_cost_component_without_description()
    {
        $data = [
            'name' => 'Material Test',
            'component_type' => 'direct_material'
        ];

        $response = $this->postJson($this->apiBaseUrl, $data, $this->headers);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Komponen Biaya Berhasil dibuat'
                 ]);

        $this->assertDatabaseHas('cost_components', $data);
    }

    public function test_validates_required_fields_when_creating()
    {
        $response = $this->postJson($this->apiBaseUrl, [], $this->headers);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name', 'component_type']);
    }

    public function test_validates_component_type_when_creating()
    {
        $data = [
            'name' => 'Material Test',
            'component_type' => 'invalid_type'
        ];

        $response = $this->postJson($this->apiBaseUrl, $data, $this->headers);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['component_type']);
    }

    public function test_validates_name_max_length_when_creating()
    {
        $data = [
            'name' => str_repeat('a', 101),
            'component_type' => 'direct_material'
        ];

        $response = $this->postJson($this->apiBaseUrl, $data, $this->headers);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    }

    public function test_can_show_cost_component()
    {
        $costComponent = CostComponent::factory()->create();

        $response = $this->getJson($this->apiBaseUrl . '/' . $costComponent->id, $this->headers);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'id',
                         'name',
                         'description',
                         'component_type'
                     ]
                 ])
                 ->assertJson([
                     'success' => true
                 ]);
    }

    public function test_returns_404_when_cost_component_not_found_in_show()
    {
        $response = $this->getJson($this->apiBaseUrl . '/999', $this->headers);

        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Komponen Biaya tidak ditemukan'
                 ]);
    }

    public function test_can_update_cost_component()
    {
        $costComponent = CostComponent::factory()->create([
            'name' => 'Original Name',
            'description' => 'Original Description',
            'component_type' => 'direct_material'
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'component_type' => 'indirect_material'
        ];

        $response = $this->putJson($this->apiBaseUrl . '/' . $costComponent->id, $updateData, $this->headers);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Komponen Biaya berhasil diperbarui'
                 ]);

        $this->assertDatabaseHas('cost_components', array_merge(['id' => $costComponent->id], $updateData));
    }

    public function test_can_partially_update_cost_component()
    {
        $costComponent = CostComponent::factory()->create([
            'name' => 'Original Name',
            'component_type' => 'direct_material'
        ]);

        $updateData = [
            'name' => 'Updated Name Only'
        ];

        $response = $this->putJson($this->apiBaseUrl . '/' . $costComponent->id, $updateData, $this->headers);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Komponen Biaya berhasil diperbarui'
                 ]);

        $this->assertDatabaseHas('cost_components', [
            'id' => $costComponent->id,
            'name' => 'Updated Name Only',
            'component_type' => 'direct_material' 
        ]);
    }

    public function test_returns_404_when_cost_component_not_found_in_update()
    {
        $updateData = [
            'name' => 'Updated Name'
        ];

        $response = $this->putJson($this->apiBaseUrl . '/999', $updateData, $this->headers);

        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Komponen Biaya tidak ditemukan'
                 ]);
    }

    public function test_validates_fields_when_updating()
    {
        $costComponent = CostComponent::factory()->create();

        $updateData = [
            'name' => str_repeat('a', 101), 
            'component_type' => 'invalid_type'
        ];

        $response = $this->putJson($this->apiBaseUrl . '/' . $costComponent->id, $updateData, $this->headers);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name', 'component_type']);
    }

    public function test_can_delete_cost_component()
    {
        $costComponent = CostComponent::factory()->create();

        $response = $this->deleteJson($this->apiBaseUrl . '/' . $costComponent->id, [], $this->headers);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Komponen Biaya berhasil dihapus'
                 ]);

        $this->assertDatabaseMissing('cost_components', ['id' => $costComponent->id]);
    }

    public function test_returns_404_when_cost_component_not_found_in_delete()
    {
        $response = $this->deleteJson($this->apiBaseUrl . '/999', [], $this->headers);

        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Komponen Biaya tidak ditemukan'
                 ]);
    }

    public function test_returns_all_valid_component_types()
    {
        $validTypes = ['direct_material', 'indirect_material', 'direct_labor', 'overhead', 'packaging', 'other'];
        
        foreach ($validTypes as $type) {
            CostComponent::factory()->create(['component_type' => $type]);
            
            $response = $this->getJson($this->apiBaseUrl . "?type={$type}", $this->headers);
            
            $response->assertStatus(200)
                     ->assertJson([
                         'success' => true,
                         'meta' => [
                             'type' => $type
                         ]
                     ]);
        }
    }

    public function test_returns_401_without_authentication()
    {
        $response = $this->getJson($this->apiBaseUrl);

        $response->assertStatus(401);
    }

    public function test_returns_401_with_invalid_token()
    {
        $invalidHeaders = [
            'Authorization' => 'Bearer invalid-token',
            'Accept' => 'application/json',
        ];

        $response = $this->getJson($this->apiBaseUrl, $invalidHeaders);

        $response->assertStatus(401);
    }
}