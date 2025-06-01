<?php

namespace Tests\Feature\Http\Controllers\Feature;

use App\Models\ExpenseCategory;
use App\Models\OperationalExpense;
use App\Models\SalesRecord;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Carbon;

class StatsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $token;
    protected $headers;
    protected $apiBaseUrl = '/api/stats';
    protected Service $userService1;
    protected ExpenseCategory $salaryCategory;
    protected ExpenseCategory $operationalCategory;


    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = JWTAuth::fromUser($this->user);

        $this->headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];

        
        $this->userService1 = Service::factory()->create(['name' => 'Test Product', 'hpp' => 100, 'selling_price' => 150]);

        $this->salaryCategory = ExpenseCategory::factory()->create(['name' => 'Gaji', 'is_salary' => true]);
        $this->operationalCategory = ExpenseCategory::factory()->create(['name' => 'Operasional Lain', 'is_salary' => false]);
    }

    private function createSale(array $attributes = []): SalesRecord
    {
        $defaults = [
            'name' => $this->faker->words(3, true),
            'service_id' => $this->userService1->id,
            'month' => Carbon::now()->month,
            'year' => Carbon::now()->year,
            'date' => 10,
            'hpp' => $this->userService1->hpp,
            'selling_price' => $this->userService1->selling_price,
        ];
        return SalesRecord::factory()->create(array_merge($defaults, $attributes));
    }

    private function createOperationalExpense(ExpenseCategory $category, array $attributes = [])
    {
        $defaults = [
            'expense_category_id' => $category->id,
            'amount' => 1000,
            'total_amount' => $attributes['amount'] ?? 1000, 
            'quantity' => 1,
            'unit' => 'unit',
            'year' => Carbon::now()->year,
            'month' => Carbon::now()->month,
        ];
        return OperationalExpense::factory()->create(array_merge($defaults, $attributes));
    }

    public function test_can_get_stats_for_current_month_and_year_with_data()
    {
        $now = Carbon::now();
        $year = $now->year;
        $month = $now->month;

        // Create sales records - controller sums selling_price directly (not multiplied by date)
        $sale1 = $this->createSale(['date' => 10, 'selling_price' => 150, 'hpp' => 100, 'year' => $year, 'month' => $month]); 
        $sale2 = $this->createSale(['service_id' => Service::factory()->create(['hpp' => 50, 'selling_price' => 80])->id, 'date' => 20, 'selling_price' => 80, 'hpp' => 50, 'year' => $year, 'month' => $month]);
        
        $opExSalary = $this->createOperationalExpense($this->salaryCategory, ['amount' => 200, 'total_amount' => 200, 'year' => $year, 'month' => $month]);
        $opExOperational = $this->createOperationalExpense($this->operationalCategory, ['amount' => 150, 'total_amount' => 150, 'year' => $year, 'month' => $month]);

        // Expected values based on controller logic: sum of selling_price and hpp directly
        $expectedTotalSales = 150 + 80; // sum of selling_price
        $expectedTotalVariableCost = 100 + 50; // sum of hpp
        $expectedGrossProfit = $expectedTotalSales - $expectedTotalVariableCost; 
        $expectedTotalSalary = 200;
        $expectedTotalOperational = 150;
        $expectedTotalCost = $expectedTotalVariableCost + $expectedTotalOperational + $expectedTotalSalary;
        $expectedNetProfit = $expectedGrossProfit - $expectedTotalOperational - $expectedTotalSalary;

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                         ->getJson($this->apiBaseUrl . "?year={$year}&month={$month}"); 

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.total_sales', $expectedTotalSales)
            ->assertJsonPath('data.total_variable_cost', $expectedTotalVariableCost)
            ->assertJsonPath('data.total_operational_cost', $expectedTotalOperational)
            ->assertJsonPath('data.total_salary_expenses', $expectedTotalSalary)
            ->assertJsonPath('data.total_cost', $expectedTotalCost)
            ->assertJsonPath('data.gross_profit', $expectedGrossProfit)
            ->assertJsonPath('data.net_profit', $expectedNetProfit)
            ->assertJsonPath('data.year', $year)
            ->assertJsonPath('data.month', $month)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_sales', 'total_cost', 'total_variable_cost', 'total_operational_cost',
                    'total_salary_expenses', 'gross_profit', 'net_profit',
                    'year', 'month', 'availableYears', 'availableMonths'
                ]
            ]);
    }

    public function test_get_stats_with_no_sales_or_expenses_returns_zero_values()
    {
        $year = Carbon::now()->year;
        $month = Carbon::now()->month;

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                         ->getJson("{$this->apiBaseUrl}?year={$year}&month={$month}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.total_sales', 0)
            ->assertJsonPath('data.total_variable_cost', 0)
            ->assertJsonPath('data.total_operational_cost', 0)
            ->assertJsonPath('data.total_salary_expenses', 0)
            ->assertJsonPath('data.total_cost', 0)
            ->assertJsonPath('data.gross_profit', 0)
            ->assertJsonPath('data.net_profit', 0)
            ->assertJsonPath('data.year', $year)
            ->assertJsonPath('data.month', $month);
    }

    public function test_get_stats_for_specific_past_year_and_month()
    {
        $targetYear = Carbon::now()->subYears(2)->year;
        $targetMonth = 3;

        // Create sale with selling_price 200 (controller sums this directly, not multiplied by date)
        $this->createSale(['year' => $targetYear, 'month' => $targetMonth, 'date' => 5, 'selling_price' => 200, 'hpp' => 120]);
        $this->createOperationalExpense($this->operationalCategory, ['amount' => 50, 'total_amount' => 50, 'year' => $targetYear, 'month' => $targetMonth]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                         ->getJson("{$this->apiBaseUrl}?year={$targetYear}&month={$targetMonth}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.total_sales', 200) // sum of selling_price (200)
            ->assertJsonPath('data.total_variable_cost', 120) // sum of hpp (120)
            ->assertJsonPath('data.total_operational_cost', 50)
            ->assertJsonPath('data.total_salary_expenses', 0)
            ->assertJsonPath('data.gross_profit', 80) // 200 - 120
            ->assertJsonPath('data.net_profit', 30) // 80 - 50
            ->assertJsonPath('data.year', $targetYear)
            ->assertJsonPath('data.month', $targetMonth);
    }

    public function test_stats_validation_fails_for_invalid_year()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                         ->getJson($this->apiBaseUrl . '?year=1990');

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['year']);
    }

    public function test_stats_validation_fails_for_invalid_month()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                         ->getJson($this->apiBaseUrl . '?month=13'); 

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['month']);
    }
    
    public function test_stats_validation_fails_for_non_integer_year()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                         ->getJson($this->apiBaseUrl . '?year=abc');

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['year']);
    }

    public function test_stats_unauthenticated_user_cannot_access_stats()
    {
        $this->getJson($this->apiBaseUrl)->assertStatus(401);
    }

    public function test_available_filters_are_correct()
    {
        $year1 = Carbon::now()->year;
        $year2 = Carbon::now()->subYear()->year;
        $month1 = Carbon::now()->month;
        $month2 = Carbon::now()->subMonth()->month;
        if ($month2 <= 0) { 
            $month2 = 12 + $month2;
        }


        $this->createSale(['year' => $year1, 'month' => $month1]);
        $this->createSale(['year' => $year1, 'month' => $month2]);
        $this->createSale(['year' => $year2, 'month' => $month1]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                         ->getJson("{$this->apiBaseUrl}?year={$year1}&month={$month1}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.availableYears', fn ($years) => in_array($year1, $years) && in_array($year2, $years) && count($years) >= 2)
                 ->assertJsonPath('data.availableMonths', fn ($months) => in_array($month1, $months) && in_array($month2, $months) && count($months) >= 2);
    }


}