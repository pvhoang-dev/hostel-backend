<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('payment_method_id');
            $table->integer('amount');
            $table->string('transaction_code', 255)->unique();
            $table->string('status', 20);
            $table->dateTime('payment_date');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('invoice_id')->references('id')->on('invoices');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
