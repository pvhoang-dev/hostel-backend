<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('house_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('house_id');
            $table->string('key', 50);
            $table->text('value');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Sử dụng unique composite [house_id, key] theo migration 2025_05_04_214608
            $table->unique(['house_id', 'key'], 'house_settings_house_id_key_unique');
            
            $table->foreign('house_id')->references('id')->on('houses');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('house_settings');
    }
};
