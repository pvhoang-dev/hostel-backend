<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('equipment_storage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('house_id');
            $table->unsignedBigInteger('equipment_id');
            $table->integer('quantity');
            $table->integer('price')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('house_id')->references('id')->on('houses');
            $table->foreign('equipment_id')->references('id')->on('equipments');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('equipment_storage');
    }
};
