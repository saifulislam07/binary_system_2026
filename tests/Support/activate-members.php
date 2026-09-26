<?php

/*
| Child-process helper for MemberActivationConcurrencyTest.
| Usage: php tests/Support/activate-members.php <member-id>[,<member-id>…]
| Boots the app against the DB given in the environment, activates each
| member through PlacementService and prints "<member-id> <member-code>" lines.
| On failure it prints the exception class to stderr and exits 1.
*/

use App\Models\Member;
use App\Services\PlacementService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$placement = $app->make(PlacementService::class);

foreach (explode(',', $argv[1] ?? '') as $id) {
    try {
        $member = $placement->activateMember(Member::query()->findOrFail((int) $id));
    } catch (Throwable $e) {
        // Laravel's handler would swallow the exit code; report the class and fail.
        fwrite(STDERR, $e::class.': '.$e->getMessage().PHP_EOL);
        exit(1);
    }

    echo $member->id, ' ', $member->member_code, PHP_EOL;
}
