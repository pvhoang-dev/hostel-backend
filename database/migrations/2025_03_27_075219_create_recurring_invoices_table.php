<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('recurring_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->enum('type', ['rent', 'service']);
            $table->unsignedBigInteger('room_service_id')->nullable();
            $table->integer('amount');
            $table->enum('price_source_type', ['contract', 'room_service']);
            $table->unsignedBigInteger('price_source_id');
            $table->enum('frequency', ['monthly', 'yearly'])->default('monthly');
            $table->date('next_run_date');
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('contract_id')->references('id')->on('contracts');
            $table->foreign('room_service_id')->references('id')->on('room_services');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('recurring_invoices');
    }
};
