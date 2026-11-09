<?php

return [

    /*
    |--------------------------------------------------------------------------
    | District instance
    |--------------------------------------------------------------------------
    |
    | Base URL of the district's PowerSchool server, without a trailing slash.
    | The legacy mobile-app SOAP/JSON service lives under this host.
    |
    */

    'base_url' => rtrim((string) env('POWERSCHOOL_BASE_URL', ''), '/'),

    'endpoint' => '/pearson-rest/services/PublicPortalServiceJSON?response=application/json',

    /*
    |--------------------------------------------------------------------------
    | Parent portal login
    |--------------------------------------------------------------------------
    */

    'username' => env('POWERSCHOOL_USERNAME'),

    'password' => env('POWERSCHOOL_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Mobile-app HTTP Digest credentials
    |--------------------------------------------------------------------------
    |
    | Static, publicly known credentials baked into the old PowerSchool mobile
    | app. They gate the endpoint itself; the parent login travels in the body.
    |
    */

    'digest' => [
        'username' => env('POWERSCHOOL_DIGEST_USERNAME', 'pearson'),
        'password' => env('POWERSCHOOL_DIGEST_PASSWORD', 'm0bApP5'),
    ],

    'timezone' => env('POWERSCHOOL_TIMEZONE', 'America/Chicago'),

    'timeout' => (int) env('POWERSCHOOL_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Alerts and digest
    |--------------------------------------------------------------------------
    |
    | A class grade falling by at least this many percentage points since the
    | last snapshot raises an alert. The digest goes to MAIL_TO_PARENT.
    |
    */

    'grade_drop_threshold' => (float) env('POWERSCHOOL_GRADE_DROP_THRESHOLD', 3),

    'digest_to' => env('MAIL_TO_PARENT'),

];
