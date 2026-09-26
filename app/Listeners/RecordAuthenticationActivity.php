<?php

namespace App\Listeners;

use App\Services\LoginAuditService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;

/**
 * Feeds every auth event (member and admin guards) into login_history.
 */
class RecordAuthenticationActivity
{
    public function __construct(
        private LoginAuditService $audit,
        private Request $request,
    ) {}

    public function handleLogin(Login $event): void
    {
        $this->audit->record($event->guard, 'login', $event->user, $this->emailOf($event->user), $this->request);
    }

    public function handleFailed(Failed $event): void
    {
        $email = is_string($event->credentials['email'] ?? null) ? $event->credentials['email'] : null;

        $this->audit->record($event->guard, 'failed', $event->user, $email, $this->request);
    }

    public function handleRegistered(Registered $event): void
    {
        $this->audit->record('web', 'registered', $event->user, $this->emailOf($event->user), $this->request);
    }

    private function emailOf(mixed $user): ?string
    {
        return is_object($user) && isset($user->email) ? (string) $user->email : null;
    }
}
