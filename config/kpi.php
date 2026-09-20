<?php

return [
    'timezone' => 'Asia/Jakarta',
    'debug_clock' => filter_var(env('KPI_DEBUG_CLOCK', false), FILTER_VALIDATE_BOOL),
    'debug_date' => env('KPI_DEBUG_DATE'),
];
