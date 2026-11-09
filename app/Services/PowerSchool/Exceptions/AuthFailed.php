<?php

namespace App\Services\PowerSchool\Exceptions;

/**
 * The parent portal rejected the login (bad credentials, locked account, …).
 */
class AuthFailed extends PowerSchoolException {}
