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
        Schema::create('product_costs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('cost_component_id');
            $table->string('unit',10);                    
            $table->decimal('unit_price', 12, 2);    
            $table->integer('quantity');        
            $table->integer('conversion_qty'); 
            $table->decimal('amount', 12, 2);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('cost_component_id')->references('id')->on('cost_components');
            $table->unique(['cost_component_id']);        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_costs');
    }
};
