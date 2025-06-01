<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ExpenseCategory;
use App\Models\OperationalExpense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;

class OperationalExpenseTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $apiBaseUrl = '/api/operational-expenses';
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

    public function test_index_returns_all_expenses_for_current_month_and_year()
    {
        $salaryCategory = ExpenseCategory::factory()->create(['is_salary' => true, 'name' => 'Gaji']);
        $operationalCategory = ExpenseCategory::factory()->create(['is_salary' => false, 'name' => 'Operasional']);
        
        $currentYear = Carbon::now()->year;
        $currentMonth = Carbon::now()->month;
        
        $salaryExpense = OperationalExpense::factory()->create([
            'expense_category_id' => $salaryCategory->id,
            'year' => $currentYear,
            'month' => $currentMonth,
            'quantity' => 5,
            'amount' => 5000000,
        ]);
        
        $operationalExpense = OperationalExpense::factory()->create([
            'expense_category_id' => $operationalCategory->id,
            'year' => $currentYear,
            'month' => $currentMonth,
            'quantity' => 1,
            'amount' => 2000000,
        ]);

        $response = $this->withHeaders($this->headers)->get($this->apiBaseUrl);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'expense_category_id',
                        'quantity',
                        'unit',
                        'amount',
                        'total_amount',
                        'year',
                        'month',
                        'category'
                    ]
                ],
                'summary' => [
                    'details',
                    'total_salary',
                    'total_operational',
                    'grand_total',
                    'total_employees',
                    'year',
                    'month'
                ],
                'filters' => [
                    'available_years',
                    'available_months',
                    'current_year',
                    'current_month'
                ]
            ])
            ->assertJson([
                'success' => true,
                'summary' => [
                    'total_salary' => 25000000,
                    'total_operational' => 2000000,
                    'grand_total' => 27000000,
                    'total_employees' => 5,
                    'year' => $currentYear,
                    'month' => $currentMonth
                ]
            ]);
    }

    public function test_index_with_year_filter()
    {
        $category = ExpenseCategory::factory()->create();
        $currentMonth = Carbon::now()->month;
        
        OperationalExpense::factory()->create([
            'expense_category_id' => $category->id,
            'year' => 2023,
            'month' => $currentMonth
        ]);
        
        OperationalExpense::factory()->create([
            'expense_category_id' => $category->id,
            'year' => 2024,
            'month' => $currentMonth
        ]);

        $response = $this->withHeaders($this->headers)->get($this->apiBaseUrl . '?year=2023');

        $response->assertStatus(200);
        
        $responseData = $response->json();
        $this->assertEquals(1, count($responseData['data']));
        $this->assertEquals(2023, $responseData['data'][0]['year']);
    }

    public function test_index_with_month_filter()
    {
        $category = ExpenseCategory::factory()->create();
        $currentYear = Carbon::now()->year;
        
        OperationalExpense::factory()->create([
            'expense_category_id' => $category->id,
            'year' => $currentYear,
            'month' => 1
        ]);
        
        OperationalExpense::factory()->create([
            'expense_category_id' => $category->id,
            'year' => $currentYear,
            'month' => 2
        ]);

        $response = $this->withHeaders($this->headers)->get($this->apiBaseUrl . '?month=1');

        $response->assertStatus(200);
        
        $responseData = $response->json();
        $this->assertEquals(1, count($responseData['data']));
        $this->assertEquals(1, $responseData['data'][0]['month']);
    }

    public function test_index_with_year_and_month_filter()
    {
        $category = ExpenseCategory::factory()->create();
        
        OperationalExpense::factory()->create([
            'expense_category_id' => $category->id,
            'year' => 2023,
            'month' => 1
        ]);
        
        OperationalExpense::factory()->create([
            'expense_category_id' => $category->id,
            'year' => 2023,
            'month' => 2
        ]);
        
        OperationalExpense::factory()->create([
            'expense_category_id' => $category->id,
            'year' => 2024,
            'month' => 1
        ]);

        $response = $this->withHeaders($this->headers)->get($this->apiBaseUrl . '?year=2023&month=1');

        $response->assertStatus(200);
        
        $responseData = $response->json();
        $this->assertEquals(1, count($responseData['data']));
        $this->assertEquals(2023, $responseData['data'][0]['year']);
        $this->assertEquals(1, $responseData['data'][0]['month']);
    }

    public function test_store_creates_new_expense_successfully()
    {
        $category = ExpenseCategory::factory()->create();
        
        $expenseData = [
            'expense_category_id' => $category->id,
            'quantity' => 10,
            'unit' => 'orang',
            'amount' => 3000000,
            'year' => 2024,
            'month' => 6
        ];

        $response = $this->withHeaders($this->headers)->post($this->apiBaseUrl, $expenseData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Item biaya operasional berhasil dibuat'
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'expense_category_id',
                    'quantity',
                    'unit',
                    'amount',
                    'total_amount',
                    'year',
                    'month'
                ]
            ]);

        $this->assertDatabaseHas('operational_expenses', [
            'expense_category_id' => $category->id,
            'quantity' => 10,
            'unit' => 'orang',
            'amount' => 3000000,
            'year' => 2024,
            'month' => 6
        ]);
    }

    public function test_store_uses_current_year_and_month_when_not_provided()
    {
        $category = ExpenseCategory::factory()->create();
        
        $expenseData = [
            'expense_category_id' => $category->id,
            'quantity' => 5,
            'unit' => 'unit',
            'amount' => 1000000
        ];

        $response = $this->withHeaders($this->headers)->post($this->apiBaseUrl, $expenseData);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('operational_expenses', [
            'expense_category_id' => $category->id,
            'year' => Carbon::now()->year,
            'month' => Carbon::now()->month
        ]);
    }

    public function test_store_fails_with_invalid_data()
    {
        $expenseData = [
            'expense_category_id' => 999,
            'quantity' => -1,
            'unit' => '',
            'amount' => -100,
        ];

        $response = $this->withHeaders($this->headers)->post($this->apiBaseUrl, $expenseData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false
            ])
            ->assertJsonStructure([
                'success',
                'errors'
            ]);
    }

    public function test_store_fails_with_duplicate_expense()
    {
        $category = ExpenseCategory::factory()->create();
        
        OperationalExpense::factory()->create([
            'expense_category_id' => $category->id,
            'year' => 2024,
            'month' => 6
        ]);
        
        $expenseData = [
            'expense_category_id' => $category->id,
            'quantity' => 5,
            'unit' => 'unit',
            'amount' => 1000000,
            'year' => 2024,
            'month' => 6
        ];

        $response = $this->withHeaders($this->headers)->post($this->apiBaseUrl, $expenseData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Biaya operasional untuk kategori, tahun, dan bulan yang sama sudah ada'
            ]);
    }

    public function test_show_returns_expense_successfully()
    {
        $category = ExpenseCategory::factory()->create();
        $expense = OperationalExpense::factory()->create([
            'expense_category_id' => $category->id
        ]);

        $response = $this->withHeaders($this->headers)->get($this->apiBaseUrl . '/' . $expense->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'expense_category_id',
                    'quantity',
                    'unit',
                    'amount',
                    'total_amount',
                    'year',
                    'month',
                    'category'
                ]
            ]);
    }

    public function test_show_returns_404_for_non_existent_expense()
    {
        $response = $this->withHeaders($this->headers)->get($this->apiBaseUrl . '/999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Item biaya operasional tidak ditemukan'
            ]);
    }

    public function test_update_modifies_expense_successfully()
    {
        $category = ExpenseCategory::factory()->create();
        $expense = OperationalExpense::factory()->create([
            'expense_category_id' => $category->id,
            'quantity' => 5,
            'amount' => 1000000
        ]);

        $updateData = [
            'quantity' => 10,
            'amount' => 2000000
        ];

        $response = $this->withHeaders($this->headers)->put($this->apiBaseUrl . '/' . $expense->id, $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Item biaya operasional berhasil diperbarui'
            ]);

        $this->assertDatabaseHas('operational_expenses', [
            'id' => $expense->id,
            'quantity' => 10,
            'amount' => 2000000
        ]);
    }

    public function test_update_returns_404_for_non_existent_expense()
    {
        $updateData = ['quantity' => 10];

        $response = $this->withHeaders($this->headers)->put($this->apiBaseUrl . '/999', $updateData);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Item biaya operasional tidak ditemukan'
            ]);
    }

    public function test_update_fails_with_invalid_data()
    {
        $category = ExpenseCategory::factory()->create();
        $expense = OperationalExpense::factory()->create([
            'expense_category_id' => $category->id
        ]);

        $updateData = [
            'quantity' => -1,
            'amount' => -1000
        ];

        $response = $this->withHeaders($this->headers)->put($this->apiBaseUrl . '/' . $expense->id, $updateData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false
            ])
            ->assertJsonStructure([
                'success',
                'errors'
            ]);
    }

    public function test_update_fails_with_duplicate_constraint()
    {
        $category = ExpenseCategory::factory()->create();
        
        $expense1 = OperationalExpense::factory()->create([
            'expense_category_id' => $category->id,
            'year' => 2024,
            'month' => 1
        ]);
        
        $expense2 = OperationalExpense::factory()->create([
            'expense_category_id' => $category->id,
            'year' => 2024,
            'month' => 2
        ]);

        $updateData = [
            'month' => 1
        ];

        $response = $this->withHeaders($this->headers)->put($this->apiBaseUrl . '/' . $expense2->id, $updateData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Biaya operasional untuk kategori, tahun, dan bulan yang sama sudah ada'
            ]);
    }

    public function test_destroy_deletes_expense_successfully()
    {
        $category = ExpenseCategory::factory()->create();
        $expense = OperationalExpense::factory()->create([
            'expense_category_id' => $category->id
        ]);

        $response = $this->withHeaders($this->headers)->delete($this->apiBaseUrl . '/' . $expense->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Item biaya operasional berhasil dihapus'
            ]);

        $this->assertDatabaseMissing('operational_expenses', [
            'id' => $expense->id
        ]);
    }

    public function test_destroy_returns_404_for_non_existent_expense()
    {
        $response = $this->withHeaders($this->headers)->delete($this->apiBaseUrl . '/999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Item biaya operasional tidak ditemukan'
            ]);
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