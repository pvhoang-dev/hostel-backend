<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();

            $table->foreign('contract_id')->references('id')->on('contracts');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('contract_users');
    }
};
