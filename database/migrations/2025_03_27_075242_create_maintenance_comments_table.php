<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('maintenance_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('maintenance_request_id');
            $table->unsignedBigInteger('user_id');
            $table->text('content');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('maintenance_request_id')->references('id')->on('maintenance_requests');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('maintenance_comments');
    }
};
