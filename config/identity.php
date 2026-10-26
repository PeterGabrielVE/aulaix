<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Support address (SPEC-004 IDG-R08.10)
    |--------------------------------------------------------------------------
    |
    | Where support hears about account deletions that something still
    | blocks at the end of the grace period. Empty disables the copy.
    |
    */

    'support_email' => (string) env('IDENTITY_SUPPORT_EMAIL', ''),

];
