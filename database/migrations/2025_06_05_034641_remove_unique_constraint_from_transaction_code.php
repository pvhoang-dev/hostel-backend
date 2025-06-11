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
        Schema::table('invoices', function (Blueprint $table) {
            // Get the actual constraint name from database
            $constraintName = DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'invoices'
                AND CONSTRAINT_TYPE = 'UNIQUE'
                AND CONSTRAINT_NAME LIKE '%transaction_code%'
            ");

            // If constraint exists, drop it
            if (!empty($constraintName)) {
                $name = $constraintName[0]->CONSTRAINT_NAME;
                $table->dropUnique($name);
            } else {
                // Try with the most common naming convention
                try {
                    $table->dropUnique('invoices_transaction_code_unique');
                } catch (\Exception $e) {
                    // If that fails too, try dropping directly by column name
                    try {
                        $table->dropUnique(['transaction_code']);
                    } catch (\Exception $e) {
                        // Log the error but continue with migration
                        error_log('Could not drop unique constraint on transaction_code: ' . $e->getMessage());
                    }
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unique('transaction_code');
        });
    }
};
