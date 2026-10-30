<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Patient self-booking
    |--------------------------------------------------------------------------
    |
    | These values are the default scheduling policy used by the shared web and
    | API booking services. Times use the application's configured timezone;
    | weekdays follow Carbon's numbering where Sunday is zero.
    |
    */
    'booking' => [
        'start' => '08:00',
        'end' => '17:00',
        'interval' => 30,
        'days' => [1, 2, 3, 4, 5, 6],
        'lead_minutes' => 60,
        'max_days_ahead' => 60,
        'lab_capacity' => 3,
        'max_upcoming' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pharmacy stock reservation windows
    |--------------------------------------------------------------------------
    |
    | Inventory is held temporarily while an invoice remains unpaid. Online
    | checkout uses a shorter window so abandoned gateway sessions do not keep
    | scarce stock unavailable to clinical staff.
    |
    */
    'pharmacy' => [
        'unpaid_reservation_minutes' => 120,
        'online_payment_reservation_minutes' => 30,
    ],
];
