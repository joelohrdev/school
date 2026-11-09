<?php

namespace App\Services\PowerSchool\Exceptions;

use RuntimeException;

/**
 * Base for every PowerSchool failure. Messages never carry credentials,
 * service tickets, or response bodies.
 */
abstract class PowerSchoolException extends RuntimeException {}
