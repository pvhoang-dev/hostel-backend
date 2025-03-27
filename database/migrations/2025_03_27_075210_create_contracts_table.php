<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('monthly_price');
            $table->integer('deposit_amount');
            $table->integer('notice_period')->default(30);
            $table->string('deposit_status', 20)->default('held');
            $table->text('termination_reason')->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('auto_renew')->default(false);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('room_id')->references('id')->on('rooms');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
