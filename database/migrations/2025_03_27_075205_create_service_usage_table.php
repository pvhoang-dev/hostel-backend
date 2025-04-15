<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_service_id');
            $table->decimal('start_meter', 10, 2)->nullable();
            $table->decimal('end_meter', 10, 2)->nullable();
            $table->decimal('usage_value', 10, 2);
            $table->integer('price_used');
            $table->integer('month');
            $table->integer('year');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('room_service_id')->references('id')->on('room_services');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_usage');
    }
};
