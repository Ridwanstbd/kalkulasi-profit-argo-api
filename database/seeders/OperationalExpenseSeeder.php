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
        $users = User::all();
        
        if ($users->isEmpty()) {
            $this->command->info('Tidak ada pengguna yang ditemukan dalam database. Silakan jalankan UserSeeder terlebih dahulu.');
            return;
        }

        $count = 0;

        foreach ($users as $user) {
            // Periksa apakah pengguna sudah memiliki komponen biaya
            $existingComponents = ExpenseCategory::where('user_id', $user->id)->count();
            
            if ($existingComponents == 0) {
                $this->createExpenseCategoryForUser($user);
                $count++;
            }
        }

        $this->command->info("Berhasil membuat komponen biaya untuk {$count} pengguna baru.");

    }

    public function createExpenseCategoryForUser(User $user)
    {
        ExpenseCategory::create([
            'user_id' => $user->id,
            'name' => 'Gaji Karyawan',
            'description' => 'Biaya gaji Karyawan',
            'is_salary' => true,
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
                'user_id' => $user->id,
                'name' => $catData['name'],
                'is_salary' => false,
            ]);
        }

    }
}
