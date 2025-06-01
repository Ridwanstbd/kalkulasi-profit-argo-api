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
        Schema::create('service_costs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('cost_component_id');
            $table->string('unit',10);                    
            $table->decimal('unit_price', 12, 2);    
            $table->decimal('quantity',8,2);        
            $table->decimal('conversion_qty',8,2); 
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            $table->foreign('cost_component_id')->references('id')->on('cost_components');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_costs');
    }
};
