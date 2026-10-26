<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Organisation
    |--------------------------------------------------------------------------
    |
    | Name des Vereins bzw. der Organisation. Er erscheint auf der öffentlichen
    | Statusseite (R12), damit Finder wissen, wem ein Gegenstand gehört.
    |
    */

    'organisation' => env('CICO_ORGANISATION', env('APP_NAME', 'CICO')),

    /*
    |--------------------------------------------------------------------------
    | Fundmeldungen
    |--------------------------------------------------------------------------
    |
    | Kontaktdaten der Finder werden nach dieser Frist automatisch gelöscht
    | (R23). Das Formular auf der Statusseite nennt dieselbe Frist.
    |
    */

    'found_reports' => [
        'contact_retention_days' => (int) env('CICO_FOUND_REPORT_RETENTION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Demodaten
    |--------------------------------------------------------------------------
    |
    | Anzahl der Gegenstände, die der DemoSeeder anlegt (R38).
    |
    */

    'demo' => [
        'items' => (int) env('CICO_DEMO_ITEMS', 3000),
    ],

];
