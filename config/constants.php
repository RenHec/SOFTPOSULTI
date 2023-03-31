<?php

return [

    /*
    |--------------------------------------------------------------------------
    | App Constants
    |--------------------------------------------------------------------------
    |List of all constants for the app
    */

    'langs' => [
        'es' => ['full_name' => 'Spanish - Español', 'short_name' => 'Spanish'],
    ],
    'langs_rtl' => ['ar'],
    'non_utf8_languages' => ['ar', 'hi', 'ps'],

    'document_size_limit' => '5000000', //in Bytes,
    'image_size_limit' => '5000000', //in Bytes

    'asset_version' => 42,

    'disable_purchase_in_other_currency' => true,

    'iraqi_selling_price_adjustment' => false,

    'currency_precision' => 2, //Maximum 4
    'quantity_precision' => 2,  //Maximum 4

    'product_img_path' => 'img',

    'enable_sell_in_diff_currency' => false,
    'currency_exchange_rate' => 1,
    'orders_refresh_interval' => 600, //Auto refresh interval on Kitchen and Orders page in seconds,

    'default_date_format' => 'm/d/Y', //Default date format to be used if session is not set. All valid formats can be found on https://www.php.net/manual/en/function.date.php

    'new_notification_count_interval' => 60, //Interval to check for new notifications in seconds;Default is 60sec

    'administrator_usernames' => env('ADMINISTRATOR_USERNAMES'),
    'allow_registration' => env('ALLOW_REGISTRATION', false),
    'app_title' => env('APP_TITLE'),
    'mpdf_temp_path' => storage_path('app/pdf'), //Temporary path used by mpdf

    'document_upload_mimes_types' => [
        'application/pdf' => '.pdf',
        'text/csv' => '.csv',
        'application/zip' => '.zip',
        'application/msword' => '.doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
        'image/jpeg' => '.jpeg',
        'image/jpg' => '.jpg',
        'image/png' => '.png'

    ], //List of MIME type: https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Common_types
    'show_report_606' => true,
    'show_report_607' => true,
    'whatsapp_base_url' => 'https://wa.me',
    'enable_crm_call_log' => true,
    'enable_product_bulk_edit' => true,  //Will be depreciated in future
    'enable_convert_draft_to_invoice' => true, //Experimental beta feature.
    'enable_download_pdf' => false,         //Experimental feature

    'payment_types' => [
        ['mostrar' => true, 'lang' => 'cash'],
        ['mostrar' => true, 'lang' => 'card'],
        ['mostrar' => false, 'lang' => 'cheque'],
        ['mostrar' => false, 'lang' => 'bank_transfer'],
        ['mostrar' => false, 'lang' => 'other'],
        ['mostrar' => false, 'lang' => 'advance']
    ]
];
