<?php

namespace App\Exceptions;

/**
 * Activation would give a second active member the same mobile number or
 * NID (rule #12, enforced by unique indexes on members.active_phone/active_nid).
 */
class DuplicateMemberException extends PlacementException {}
