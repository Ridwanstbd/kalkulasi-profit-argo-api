<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\OperationalExpense;
use App\Models\User;
use Illuminate\Database\Seeder;

class OperationalExpenseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();
        
        $userId = $user->id;

        ExpenseCategory::create([
            'user_id' => $userId,
            'name' => 'Biaya Gaji',
            'description' => 'Biaya gaji karyawan',
            'is_salary' => true,
            'order' => 1,
        ]);

        $operationalCategories = [
            [
                'id' => 2,
                'name' => 'Depresiasi Sewa Kantor & Gudang',
            ],
            [
                'id' => 3,
                'name' => 'Biaya Air, Listrik, Telepon, & Internet',
            ],
            [
                'id' => 4,
                'name' => 'Biaya Iklan',
                'item' => [
                    'name' => 'Biaya Iklan',
                    'quantity' => 1,
                    'unit' => 'Minggu',
                    'amount' => 2000000,
                    'conversion_factor' => 4,
                    'conversion_unit' => 'perbulan',
                ]
            ],
            [
                'id' => 5,
                'name' => 'Biaya Transportasi & Pengiriman',
            ],
            [
                'id' => 6,
                'name' => 'Biaya Perawatan (Gedung, Kendaraan, Mesin)'
            ],
            [
                'id' => 7,
                'name' => 'Lain-Lain'
            ],
        ];

        foreach ($operationalCategories as $catData) {
            ExpenseCategory::create([
                'user_id' => $userId,
                'name' => $catData['name'],
                'is_salary' => false,
                'order' => $catData['id'],
            ]);
        }
    }
}
