<?php

return [
    'pin'           => env('CHECKIN_PIN', '2026'),
    'event_name'    => env('EVENT_NAME', 'GGIT Youth Conference 2026'),
    'event_theme'   => env('EVENT_THEME', 'A Seat at the Table of Innovation with GGIT'),
    'sync_interval' => (int) env('CHECKIN_SYNC_MS', 3000), // how often devices pull each other's check-ins
];