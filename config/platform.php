<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Recovery Code Key (SPEC-017 RSP-NF02)
    |--------------------------------------------------------------------------
    |
    | Secret used to compute HMAC-SHA256 fingerprints of operator recovery
    | codes. Kept separate from APP_KEY so rotating APP_KEY does not invalidate
    | recovery codes. Mandatory outside local and testing environments.
    |
    | WARNING: losing or rotating this key invalidates every stored recovery
    | code (TOTP keeps working; operators can regenerate codes or have their
    | second factor reset by a SuperAdmin).
    |
    */

    'recovery_code_key' => env('PLATFORM_RECOVERY_CODE_KEY'),

];
