<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ExpenseCategory;
use App\Models\OperationalExpense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ExpenseCategoryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $apiBaseUrl = '/api/expense-categories';
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


    public function test_can_get_all_expense_categories()
    {
        // Create test categories
        $salaryCategory = ExpenseCategory::factory()->create([
            'name' => 'Gaji Karyawan',
            'is_salary' => true
        ]);
        
        $operationalCategory = ExpenseCategory::factory()->create([
            'name' => 'Biaya Operasional',
            'is_salary' => false
        ]);

        // Create some expenses for testing
        OperationalExpense::factory()->create([
            'expense_category_id' => $salaryCategory->id,
            'amount' => 5000000
        ]);

        OperationalExpense::factory()->create([
            'expense_category_id' => $operationalCategory->id,
            'amount' => 2000000
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson($this->apiBaseUrl);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'is_salary',
                        'total_amount',
                        'operational_expenses'
                    ]
                ],
                'summary' => [
                    'total_salary',
                    'total_operational',
                    'grand_total'
                ]
            ])
            ->assertJson([
                'success' => true
            ]);

        $this->assertEquals(2, count($response->json('data')));
    }


    public function test_can_create_expense_category()
    {
        $categoryData = [
            'name' => 'Test Category',
            'description' => 'Test Description',
            'is_salary' => false
        ];

        $response = $this->withHeaders($this->headers)
            ->postJson($this->apiBaseUrl, $categoryData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'is_salary'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Kategori biaya berhasil dibuat',
                'data' => [
                    'name' => 'Test Category',
                    'description' => 'Test Description',
                    'is_salary' => false
                ]
            ]);

        $this->assertDatabaseHas('expense_categories', [
            'name' => 'Test Category',
            'description' => 'Test Description',
            'is_salary' => false
        ]);
    }


    public function test_can_create_salary_category()
    {
        $categoryData = [
            'name' => 'Gaji Manager',
            'description' => 'Biaya gaji manager',
            'is_salary' => true
        ];

        $response = $this->withHeaders($this->headers)
            ->postJson($this->apiBaseUrl, $categoryData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Kategori biaya berhasil dibuat',
                'data' => [
                    'name' => 'Gaji Manager',
                    'is_salary' => true
                ]
            ]);

        $this->assertDatabaseHas('expense_categories', [
            'name' => 'Gaji Manager',
            'is_salary' => true
        ]);
    }


    public function test_validates_required_fields_when_creating_category()
    {
        $response = $this->withHeaders($this->headers)
            ->postJson($this->apiBaseUrl, []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false
            ])
            ->assertJsonValidationErrors(['name', 'is_salary']);
    }


    public function test_validates_name_max_length_when_creating_category()
    {
        $categoryData = [
            'name' => str_repeat('a', 256), // 256 characters
            'is_salary' => false
        ];

        $response = $this->withHeaders($this->headers)
            ->postJson($this->apiBaseUrl, $categoryData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }


    public function test_can_show_specific_expense_category()
    {
        $category = ExpenseCategory::factory()->create([
            'name' => 'Test Category',
            'is_salary' => false
        ]);

        // Create an expense for this category
        OperationalExpense::factory()->create([
            'expense_category_id' => $category->id,
            'amount' => 1000000
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson($this->apiBaseUrl . '/' . $category->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'is_salary',
                    'total_amount'
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $category->id,
                    'name' => 'Test Category',
                    'is_salary' => false
                ]
            ]);
    }


    public function test_can_show_salary_category_with_employee_count()
    {
        $category = ExpenseCategory::factory()->create([
            'name' => 'Gaji Karyawan',
            'is_salary' => true
        ]);

        // Create salary expenses
        OperationalExpense::factory()->count(3)->create([
            'expense_category_id' => $category->id,
            'amount' => 5000000
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson($this->apiBaseUrl . '/' . $category->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'is_salary',
                    'total_amount',
                    'total_employees'
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_salary' => true
                ]
            ]);
    }


    public function test_returns_404_when_showing_non_existent_category()
    {
        $response = $this->withHeaders($this->headers)
            ->getJson($this->apiBaseUrl . '/999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Kategori biaya tidak ditemukan'
            ]);
    }


    public function test_can_update_expense_category()
    {
        $category = ExpenseCategory::factory()->create([
            'name' => 'Old Name',
            'description' => 'Old Description',
            'is_salary' => false
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'is_salary' => true
        ];

        $response = $this->withHeaders($this->headers)
            ->putJson($this->apiBaseUrl . '/' . $category->id, $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Kategori biaya berhasil diperbarui',
                'data' => [
                    'name' => 'Updated Name',
                    'description' => 'Updated Description',
                    'is_salary' => true
                ]
            ]);

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'is_salary' => true
        ]);
    }


    public function test_can_partially_update_expense_category()
    {
        $category = ExpenseCategory::factory()->create([
            'name' => 'Original Name',
            'description' => 'Original Description',
            'is_salary' => false
        ]);

        $updateData = [
            'name' => 'Updated Name Only'
        ];

        $response = $this->withHeaders($this->headers)
            ->putJson($this->apiBaseUrl . '/' . $category->id, $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Kategori biaya berhasil diperbarui'
            ]);

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'name' => 'Updated Name Only',
            'description' => 'Original Description', // Should remain unchanged
            'is_salary' => false // Should remain unchanged
        ]);
    }


    public function test_validates_fields_when_updating_category()
    {
        $category = ExpenseCategory::factory()->create();

        $updateData = [
            'name' => str_repeat('a', 256) // Too long
        ];

        $response = $this->withHeaders($this->headers)
            ->putJson($this->apiBaseUrl . '/' . $category->id, $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }


    public function test_returns_404_when_updating_non_existent_category()
    {
        $updateData = [
            'name' => 'Updated Name'
        ];

        $response = $this->withHeaders($this->headers)
            ->putJson($this->apiBaseUrl . '/999', $updateData);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Kategori biaya tidak ditemukan'
            ]);
    }


    public function test_can_delete_expense_category_without_expenses()
    {
        $category = ExpenseCategory::factory()->create();

        $response = $this->withHeaders($this->headers)
            ->deleteJson($this->apiBaseUrl . '/' . $category->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Kategori biaya berhasil dihapus'
            ]);

        $this->assertDatabaseMissing('expense_categories', [
            'id' => $category->id
        ]);
    }


    public function test_cannot_delete_category_with_existing_expenses()
    {
        $category = ExpenseCategory::factory()->create();
        
        // Create an expense for this category
        OperationalExpense::factory()->create([
            'expense_category_id' => $category->id
        ]);

        $response = $this->withHeaders($this->headers)
            ->deleteJson($this->apiBaseUrl . '/' . $category->id);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Kategori ini memiliki item biaya. Hapus semua item biaya terlebih dahulu.'
            ]);

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id
        ]);
    }


    public function test_returns_404_when_deleting_non_existent_category()
    {
        $response = $this->withHeaders($this->headers)
            ->deleteJson($this->apiBaseUrl . '/999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Kategori biaya tidak ditemukan'
            ]);
    }


    public function test_requires_authentication_for_all_endpoints()
    {
        // Test without headers (no authentication)
        $response = $this->getJson($this->apiBaseUrl);
        $response->assertStatus(401);

        $response = $this->postJson($this->apiBaseUrl, []);
        $response->assertStatus(401);

        $response = $this->getJson($this->apiBaseUrl . '/1');
        $response->assertStatus(401);

        $response = $this->putJson($this->apiBaseUrl . '/1', []);
        $response->assertStatus(401);

        $response = $this->deleteJson($this->apiBaseUrl . '/1');
        $response->assertStatus(401);
    }

}