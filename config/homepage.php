<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hospital Defaults
    |--------------------------------------------------------------------------
    |
    | Fallback hospital code/name used when the HOSxP database is unreachable.
    | Each hospital is isolated based on opdconfig.hospitalcode.
    |
    */

    'default_hcode' => env('HOMEPAGE_DEFAULT_HCODE', null),
    'default_hname' => env('HOMEPAGE_DEFAULT_HNAME', null),

    /*
    |--------------------------------------------------------------------------
    | Homepage Poster Admin
    |--------------------------------------------------------------------------
    |
    | Client IPs allowed to manage posters on the homepage (comma separated in .env).
    | Defaults to the server itself; add IT workstation IPs as needed.
    |
    */

    'admin_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('HOMEPAGE_ADMIN_IPS', '127.0.0.1,::1'))))),

    /*
    |--------------------------------------------------------------------------
    | Homepage Ward Census
    |--------------------------------------------------------------------------
    |
    | Ward codes (ward.ward) shown as admitted-patient cards on the homepage.
    |
    */

    'census_wards' => array_values(array_filter(array_map('trim', explode(',', (string) env('HOMEPAGE_CENSUS_WARDS', '03,01,10'))))),
];
