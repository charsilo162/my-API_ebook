<?php

return [
    'public_key' => env('PAYSTACK_PUBLIC_KEY'),
    'secret_key' => env('PAYSTACK_SECRET_KEY'),
    'payment_url' => env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co'),
    'verify_url' => env('PAYSTACK_VERIFY_URL', 'https://api.paystack.co/transaction/verify'),
    'callback_url' => env('PAYSTACK_CALLBACK_URL'),
    ];
