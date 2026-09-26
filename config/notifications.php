<?php

/*
| Member notification channels (Phase 13). In-app (database) notifications
| are always on; the rest are switched here. Channel classes live in
| App\Notifications\Channels — to plug in a real SMS/WhatsApp gateway,
| bind a new implementation of that channel in AppServiceProvider; no
| notification or call site changes.
*/

return [

    'channels' => [
        'mail' => (bool) env('NOTIFY_MAIL', true),
        'sms' => (bool) env('NOTIFY_SMS', false),
        'whatsapp' => (bool) env('NOTIFY_WHATSAPP', false),
    ],

    // Log channel the SMS/WhatsApp stubs write to until a gateway is wired up.
    'stub_log_channel' => env('NOTIFY_STUB_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),

    // Announcement broadcasts notify members in chunks of this size.
    'broadcast_chunk' => 500,

];
