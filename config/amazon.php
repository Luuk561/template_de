<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Amazon PartnerNet Configuration (Germany)
    |--------------------------------------------------------------------------
    |
    | Configuration for Amazon.de affiliate program (PartnerNet)
    | Will be implemented after Amazon approval
    |
    */

    'de' => [
        // Amazon PartnerNet Associate Tag (e.g., 'yoursite-21')
        'associate_tag' => env('AMAZON_DE_ASSOCIATE_TAG', 'your-tag-21'),

        // Amazon Product Advertising API credentials (to be added after approval)
        'access_key' => env('AMAZON_DE_ACCESS_KEY'),
        'secret_key' => env('AMAZON_DE_SECRET_KEY'),
        'region' => 'eu-west-1', // Europe (Ireland) region for Amazon.de
        'marketplace' => 'www.amazon.de',
    ],

    /*
    |--------------------------------------------------------------------------
    | Future: Additional Amazon Markets
    |--------------------------------------------------------------------------
    |
    | Austria (amazon.at) and Switzerland (amazon.de with shipping)
    | Can use same PartnerNet account
    |
    */
];
