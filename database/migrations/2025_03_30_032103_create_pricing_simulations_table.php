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
        Schema::create('pricing_simulations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('product_id');
            $table->string('name',100);
            $table->decimal('base_hpp',12,2);
            $table->string('margin_type')->default('percentage');
            $table->decimal('margin_value',12,2)->default(0);
            $table->string('discount_type')->nullable();
            $table->decimal('discount_value',12,2)->nullable();
            $table->decimal('price_before_discount',12,2)->default(0);
            $table->decimal('retail_price',12,2)->default(0);
            $table->decimal('profit',12,2)->nullable();
            $table->decimal('profit_percentage',12,2)->nullable();
            $table->enum('market_position', ['premium', 'standard', 'economy'])->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_applied')->default(false);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_simulations');
    }
};
