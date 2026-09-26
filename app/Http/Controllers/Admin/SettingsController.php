<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\CommissionRule;
use App\Models\Setting;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin-editable business settings: commission rules (rule #6/#7) and the
 * minimum withdrawal (rule #9). Stored in commission_rules / settings; every
 * change is logged with old and new values.
 */
class SettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.index', [
            'rules' => CommissionRule::query()->pluck('value', 'key'),
            'minWithdrawal' => Setting::int(Setting::MIN_WITHDRAWAL, 100_000),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'binary_rate' => ['required', 'decimal:0,2', 'min:0', 'max:100'],
            'referral_rate' => ['required', 'decimal:0,2', 'min:0', 'max:100'],
            'daily_cap' => ['required', 'decimal:0,2', 'min:0'],
            'weekly_cap' => ['required', 'decimal:0,2', 'min:0'],
            'monthly_cap' => ['required', 'decimal:0,2', 'min:0'],
            'carry_forward_enabled' => ['required', 'boolean'],
            'cap_overflow_behavior' => ['required', Rule::in(['void', 'carry_forward'])],
            'min_withdrawal' => ['required', 'decimal:0,2', 'min:1'],
        ]);

        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        // Percent → basis points, taka → poysha: string parsing, integers only from here on.
        $bps = fn (string $percent) => Money::bpsFromPercent($percent);
        $poysha = fn (string $taka) => Money::fromTaka($taka);

        $newRules = [
            CommissionRule::BINARY_RATE_BPS => (string) $bps($data['binary_rate']),
            CommissionRule::REFERRAL_RATE_BPS => (string) $bps($data['referral_rate']),
            CommissionRule::DAILY_CAP => (string) $poysha($data['daily_cap']),
            CommissionRule::WEEKLY_CAP => (string) $poysha($data['weekly_cap']),
            CommissionRule::MONTHLY_CAP => (string) $poysha($data['monthly_cap']),
            CommissionRule::CARRY_FORWARD_ENABLED => $request->boolean('carry_forward_enabled') ? '1' : '0',
            CommissionRule::CAP_OVERFLOW_BEHAVIOR => $data['cap_overflow_behavior'],
        ];
        $newMin = (string) $poysha($data['min_withdrawal']);

        DB::transaction(function () use ($newRules, $newMin, $admin) {
            $old = CommissionRule::query()->pluck('value', 'key')->all();
            $old[Setting::MIN_WITHDRAWAL] = Setting::get(Setting::MIN_WITHDRAWAL);

            foreach ($newRules as $key => $value) {
                CommissionRule::query()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
            Setting::query()->updateOrCreate(['key' => Setting::MIN_WITHDRAWAL], ['value' => $newMin]);

            $new = [...$newRules, Setting::MIN_WITHDRAWAL => $newMin];
            $changed = array_keys(array_filter($new, fn ($value, $key) => ($old[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH));

            if ($changed !== []) {
                activity('settings')
                    ->causedBy($admin)
                    ->withProperties([
                        'old' => array_intersect_key($old, array_flip($changed)),
                        'attributes' => array_intersect_key($new, array_flip($changed)),
                    ])
                    ->log('Business settings changed');
            }
        });

        return redirect()->route('admin.settings.index')->with('success', 'Settings saved. They apply from the next commission cycle and new withdrawal requests.');
    }
}
