<?php

namespace App\Observers;
use App\Models\User;
use Database\Seeders\OperationalExpenseSeeder;

class UserObserver
{
    public function created(User $user)
    {
        $seeder = new OperationalExpenseSeeder();
        $seeder->createExpenseCategoryForUser($user);
    }
}
