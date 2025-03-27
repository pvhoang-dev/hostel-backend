<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->enum('source_type', ['recurring_invoice', 'manual', 'service_usage'])->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('item_type', 20);
            $table->integer('amount');
            $table->text('description')->nullable();
            $table->date('period');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('invoice_id')->references('id')->on('invoices');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
