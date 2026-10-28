<?php

/*
|--------------------------------------------------------------------------
| Enrollment (SPEC-010)
|--------------------------------------------------------------------------
|
| The ages of each grade (MAT-R03): the regular one and the range a student
| enrolls in without a directive's exception, at the cutoff date: this month
| and day of the calendar year the school year starts in. Grades without an
| entry (Educación Inicial for now) are not checked (MAT-R03.7). Changing
| them needs no change of code (MAT-R03.6); a wrong entry stops the app at
| boot, not an enrollment.
|
*/

return [

    'age_cutoff' => '12-31',

    // grade => [regular, min, max]
    'ages' => [
        'primaria_1' => [6, 5, 7],
        'primaria_2' => [7, 6, 8],
        'primaria_3' => [8, 7, 9],
        'primaria_4' => [9, 8, 10],
        'primaria_5' => [10, 9, 11],
        'primaria_6' => [11, 10, 12],
        'media_1' => [12, 11, 13],
        'media_2' => [13, 12, 14],
        'media_3' => [14, 13, 15],
        'media_4' => [15, 14, 16],
        'media_5' => [16, 15, 18],
    ],

];
