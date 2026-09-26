<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One authentication event (rule #12: IP/device logging on registration
 * and login). Append-only.
 */
#[Fillable(['guard', 'authenticatable_id', 'email', 'event', 'ip', 'user_agent', 'device_hash', 'new_device', 'new_ip'])]
class LoginHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'login_history';

    protected function casts(): array
    {
        return [
            'new_device' => 'boolean',
            'new_ip' => 'boolean',
        ];
    }
}
