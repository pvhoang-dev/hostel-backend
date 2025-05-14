<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;
class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $paymentMethods = [
            [
                'name' => 'Chuyển khoản',
                'status' => 'active',
            ],
            [
                'name' => 'Tiền mặt',
                'status' => 'active',
            ],
        ];

        foreach ($paymentMethods as $paymentMethod) {
            PaymentMethod::create($paymentMethod);
        }
    }
}
