<?php

return [
    'pin'           => env('CHECKIN_PIN', '2026'),
    'event_name'    => env('EVENT_NAME', 'GGIT Youth Conference 2026'),
    'event_theme'   => env('EVENT_THEME', 'A Seat at the Table of Innovation with GGIT'),
    'sync_interval' => (int) env('CHECKIN_SYNC_MS', 3000),

    // ---- Payments on the day ----
    'currency'          => env('CURRENCY', 'FCFA'),
    'ticket_price'      => (int) env('TICKET_PRICE', 2000), // pre-filled for walk-ins added at the door
    'paying_categories' => ['walkin'],                      // other categories start at 0 (staff can still type an amount)
];