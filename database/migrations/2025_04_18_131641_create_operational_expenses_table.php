<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('operational_expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreignId('expense_category_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->integer('quantity');
            $table->string('unit');
            $table->decimal('amount', 15, 2);
            $table->decimal('conversion_factor', 10, 5)->default(1);
            $table->string('conversion_unit')->default('perbulan');
            $table->decimal('total_amount', 15, 2);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operational_expenses');
    }
};
