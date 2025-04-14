<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('room_equipments', function (Blueprint $table) {
            // Drop nullable constraint from equipment_id
            $table->unsignedBigInteger('equipment_id')->nullable(false)->change();
            // Drop custom_name column
            $table->dropColumn('custom_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_equipments', function (Blueprint $table) {
            // Add back nullable constraint to equipment_id
            $table->unsignedBigInteger('equipment_id')->nullable()->change();
            // Add back custom_name column
            $table->string('custom_name', 100)->nullable();
        });
    }
};
