<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Monnify
    |--------------------------------------------------------------------------
    */
    'monnify' => [
        'api_key'       => env('MONNIFY_API_KEY'),
        'secret_key'    => env('MONNIFY_SECRET_KEY'),
        'contract_code' => env('MONNIFY_CONTRACT_CODE'),
        'base_url'      => env('MONNIFY_BASE_URL', 'https://api.monnify.com'),
        'webhook_secret'=> env('MONNIFY_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Paystack
    |--------------------------------------------------------------------------
    */
    'paystack' => [
        'public_key'     => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key'     => env('PAYSTACK_SECRET_KEY'),
        'base_url'       => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Flutterwave
    |--------------------------------------------------------------------------
    */
    'flutterwave' => [
        'public_key'      => env('FLUTTERWAVE_PUBLIC_KEY'),
        'secret_key'      => env('FLUTTERWAVE_SECRET_KEY'),
        'encryption_key'  => env('FLUTTERWAVE_ENCRYPTION_KEY'),
        'base_url'        => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
        'webhook_secret'  => env('FLUTTERWAVE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Paga
    |--------------------------------------------------------------------------
    */
    'paga' => [
        'api_key'    => env('PAGA_API_KEY'),
        'secret_key' => env('PAGA_SECRET_KEY'),
        'base_url'   => env('PAGA_BASE_URL', 'https://www.mypaga.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Termii (SMS)
    |--------------------------------------------------------------------------
    */
    'termii' => [
        'api_key'  => env('TERMII_API_KEY'),
        'base_url' => env('TERMII_BASE_URL', 'https://api.ng.termii.com'),
        'sender'   => env('TERMII_SENDER_ID', 'VTU Pro'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Africa's Talking (SMS fallback)
    |--------------------------------------------------------------------------
    */
    'africastalking' => [
        'username' => env('AFRICAS_TALKING_USERNAME'),
        'api_key'  => env('AFRICAS_TALKING_API_KEY'),
    ],

];
