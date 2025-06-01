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
        Schema::create('price_schemas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->string('level_name',100);
            $table->integer('level_order');
            $table->decimal('discount_percentage',5,2);
            $table->decimal('purchase_price',12,2);
            $table->decimal('selling_price',12,2);
            $table->decimal('profit_amount',12,2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            $table->unique(['service_id', 'level_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_schemas');
    }
};
