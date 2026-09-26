<?php

namespace App\Services;

use App\Models\LoginHistory;
use App\Models\User;
use App\Notifications\NewDeviceLogin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Rule #12: log IP and device on registration and every login (both
 * guards, including failed attempts). A successful member login from a
 * device or IP not seen before is flagged and the member is told — it is
 * never blocked.
 */
class LoginAuditService
{
    public function record(string $guard, string $event, ?Authenticatable $user, ?string $email, Request $request): LoginHistory
    {
        $userAgent = mb_substr((string) $request->userAgent(), 0, 512);
        $deviceHash = hash('sha256', $userAgent);
        $id = $user?->getAuthIdentifier();

        $newDevice = false;
        $newIp = false;

        if ($event === 'login' && $id !== null) {
            $previous = LoginHistory::query()
                ->where('guard', $guard)
                ->where('authenticatable_id', $id)
                ->whereIn('event', ['login', 'registered']);

            if ((clone $previous)->exists()) {
                $newDevice = (clone $previous)->where('device_hash', $deviceHash)->doesntExist();
                $newIp = (clone $previous)->where('ip', $request->ip())->doesntExist();
            }
        }

        $entry = LoginHistory::query()->create([
            'guard' => $guard,
            'authenticatable_id' => $id,
            'email' => $email,
            'event' => $event,
            'ip' => $request->ip(),
            'user_agent' => $userAgent,
            'device_hash' => $deviceHash,
            'new_device' => $newDevice,
            'new_ip' => $newIp,
        ]);

        if (($newDevice || $newIp) && $user instanceof User) {
            $user->notify(new NewDeviceLogin($entry));
        }

        return $entry;
    }
}
