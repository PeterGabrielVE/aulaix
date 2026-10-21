<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Institution files (SPEC-006 INS-R04, R07.2)
    |--------------------------------------------------------------------------
    |
    | The disk that keeps each institution's logo and signatures. It must be
    | private: the files are served only through the application, which
    | checks who asks. Every server must see the same files, so production
    | uses a shared disk (S3).
    |
    */

    'files_disk' => (string) env('INSTITUTION_FILES_DISK', 'institution_files'),

];
