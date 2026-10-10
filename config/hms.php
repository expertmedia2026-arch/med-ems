<?php

return [

    // Patient self-booking
    'booking' => [
        'start' => '08:00',
        'end' => '17:00',
        'interval' => 30,                  // minutes per slot
        'days' => [1, 2, 3, 4, 5, 6],  // Mon-Sat (0 = Sunday)
        'lead_minutes' => 60,                  // minimum notice
        'max_days_ahead' => 60,
        'lab_capacity' => 3,                   // lab bookings allowed per slot
        'max_upcoming' => 5,                   // upcoming appointments per patient
    ],
];
