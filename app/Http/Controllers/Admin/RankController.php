<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ConfigurationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveBonusRuleRequest;
use App\Http\Requests\Admin\UpdateRanksRequest;
use App\Models\Admin;
use App\Models\BonusRule;
use App\Models\Rank;
use App\Services\RankRulesService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RankController extends Controller
{
    public function index(): View
    {
        return view('admin.ranks.index', [
            'ranks' => Rank::query()->withCount('achievements')->orderBy('sort_order')->get(),
            'rules' => BonusRule::query()->withCount('bonuses')->orderBy('type')->orderBy('threshold')->get(),
        ]);
    }

    public function updateRanks(UpdateRanksRequest $request, RankRulesService $service): RedirectResponse
    {
        try {
            $service->updateRanks($request->ranks(), $this->admin($request));
        } catch (ConfigurationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.ranks.index')->with('success', 'Rank thresholds saved. They apply from the next nightly evaluation; ranks already reached are kept.');
    }

    public function storeBonusRule(SaveBonusRuleRequest $request, RankRulesService $service): RedirectResponse
    {
        $type = $request->string('type')->toString();
        $rule = $service->createBonusRule(['type' => $type, ...$request->ruleData($type)], $this->admin($request));

        return redirect()->route('admin.ranks.index')->with('success', "Bonus rule “{$rule->name}” created.");
    }

    public function updateBonusRule(SaveBonusRuleRequest $request, BonusRule $rule, RankRulesService $service): RedirectResponse
    {
        $service->updateBonusRule($rule, $request->ruleData($rule->type), $this->admin($request));

        return redirect()->route('admin.ranks.index')->with('success', "Bonus rule “{$rule->name}” saved.");
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        return $admin;
    }
}
