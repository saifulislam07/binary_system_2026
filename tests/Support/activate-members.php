<?php

/*
| Child-process helper for MemberActivationConcurrencyTest.
| Usage: php tests/Support/activate-members.php <member-id>[,<member-id>…]
| Boots the app against the DB given in the environment, activates each
| member through PlacementService and prints "<member-id> <member-code>" lines.
*/

use App\Models\Member;
use App\Services\PlacementService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$placement = $app->make(PlacementService::class);

foreach (explode(',', $argv[1] ?? '') as $id) {
    $member = $placement->activateMember(Member::query()->findOrFail((int) $id));
    echo $member->id, ' ', $member->member_code, PHP_EOL;
}
