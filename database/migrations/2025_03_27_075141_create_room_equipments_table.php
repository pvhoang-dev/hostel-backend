<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('room_equipments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipment_id')->nullable();
            $table->unsignedBigInteger('room_id');
            $table->enum('source', ['storage', 'custom']);
            $table->integer('quantity');
            $table->integer('price');
            $table->string('custom_name', 100)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('room_id')->references('id')->on('rooms');
            $table->foreign('equipment_id')->references('id')->on('equipments');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('room_equipments');
    }
};
