<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('configs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->text('description')->nullable();
            $table->string('group')->default('general');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        DB::table('configs')->insert([
            [
                'key' => 'payos_client_id',
                'value' => env('PAYOS_CLIENT_ID', ''),
                'description' => 'Client ID của cổng thanh toán PayOS',
                'group' => 'payos',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'payos_api_key',
                'value' => env('PAYOS_API_KEY', ''),
                'description' => 'API Key của cổng thanh toán PayOS',
                'group' => 'payos',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'payos_checksum_key',
                'value' => env('PAYOS_CHECKSUM_KEY', ''),
                'description' => 'Checksum Key của cổng thanh toán PayOS',
                'group' => 'payos',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configs');
    }
};
