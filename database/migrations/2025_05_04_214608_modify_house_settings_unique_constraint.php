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
        Schema::table('house_settings', function (Blueprint $table) {
            // Drop the old unique constraint on just 'key'
            $table->dropUnique('house_settings_key_unique');

            // Add a new composite unique constraint
            $table->unique(['house_id', 'key'], 'house_settings_house_id_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('house_settings', function (Blueprint $table) {
            // Drop the composite unique constraint
            $table->dropUnique('house_settings_house_id_key_unique');

            // Restore the original constraint
            $table->unique('key', 'house_settings_key_unique');
        });
    }
};
