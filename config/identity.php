<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Consolidation operators (SPEC-004 IDG-R11.3)
    |--------------------------------------------------------------------------
    |
    | Who may resolve the doubtful consolidation cases from the console
    | (identity:consolidation:resolve). Comma-separated identifiers of the
    | support staff, set per environment. Empty means nobody.
    |
    */

    'consolidation_operators' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('IDENTITY_CONSOLIDATION_OPERATORS', '')),
    ))),

];
