<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_price_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_service_id');
            $table->integer('price');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('room_service_id')->references('id')->on('room_services');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('service_price_history');
    }
};
