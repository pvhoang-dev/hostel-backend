<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Thêm các trường từ bảng transactions vào bảng invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_method_id')->nullable()->after('updated_by');
            $table->string('transaction_code', 255)->nullable()->unique()->after('payment_method_id');
            $table->string('payment_status', 20)->default('pending')->after('transaction_code');
            $table->dateTime('payment_date')->nullable()->after('payment_status');
            
            $table->foreign('payment_method_id')->references('id')->on('payment_methods');
        });
        
        // Di chuyển dữ liệu từ bảng transactions sang invoices
        $transactions = DB::table('transactions')->get();
        
        foreach ($transactions as $transaction) {
            DB::table('invoices')
                ->where('id', $transaction->invoice_id)
                ->update([
                    'payment_method_id' => $transaction->payment_method_id,
                    'transaction_code' => $transaction->transaction_code,
                    'payment_status' => $transaction->status,
                    'payment_date' => $transaction->payment_date,
                ]);
        }
    }

    public function down(): void
    {
        // Xóa các trường đã thêm vào bảng invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropColumn([
                'payment_method_id',
                'transaction_code', 
                'payment_status',
                'payment_date'
            ]);
        });
    }
}; 