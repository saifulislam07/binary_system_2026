<?php

namespace App\Exceptions;

use DomainException;

/**
 * An admin configuration change (packages, ranks, bonus rules, admin
 * accounts, roles) that would leave the system in an unusable state.
 */
class ConfigurationException extends DomainException {}
