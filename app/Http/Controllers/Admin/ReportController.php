<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommissionType;
use App\Enums\MemberStatus;
use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Commission;
use App\Models\Member;
use App\Models\Rank;
use App\Services\FinancialReportService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, FinancialReportService $reports): View
    {
        [$report, $granularity, $from, $to] = $this->params($request);

        return view('admin.reports.index', [
            'report' => $report,
            'granularity' => $granularity,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'reports' => FinancialReportService::REPORTS,
            'granularities' => FinancialReportService::GRANULARITIES,
            'data' => $reports->build($report, $granularity, $from, $to),
        ]);
    }

    public function export(Request $request, FinancialReportService $reports): StreamedResponse
    {
        [$report, $granularity, $from, $to] = $this->params($request);
        $data = $reports->build($report, $granularity, $from, $to);
        $filename = "{$report}-{$granularity}-{$from->toDateString()}-to-{$to->toDateString()}.csv";

        $admin = $request->user('admin');
        activity('reports')->causedBy($admin instanceof Admin ? $admin : null)
            ->withProperties(compact('report', 'granularity') + ['from' => $from->toDateString(), 'to' => $to->toDateString()])
            ->log('Report exported');

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows ৳ and Bengali correctly
            fputcsv($out, ['Period', ...array_map(fn ($c) => $c['label'].($c['money'] ? ' (BDT)' : ''), $data['columns'])]);

            $cell = fn (int $value, int $i) => $data['columns'][$i]['money'] ? Money::toDecimalString($value) : (string) $value;

            foreach ($data['rows'] as $row) {
                fputcsv($out, [$row['period'], ...array_map($cell, $row['values'], array_keys($row['values']))]);
            }

            fputcsv($out, ['Total', ...array_map($cell, $data['totals'], array_keys($data['totals']))]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * How active members are spread across ranks, and what each rank's
     * bonus has cost so far.
     */
    public function ranks(): View
    {
        $holders = Member::query()
            ->where('status', MemberStatus::Active)
            ->selectRaw('current_rank_id, COUNT(*) AS n')
            ->groupBy('current_rank_id')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row) => [(int) ($row->current_rank_id ?? 0) => (int) $row->n]);

        $bonusPaid = Commission::query()
            ->where('type', CommissionType::Rank)
            ->where('status', PayoutStatus::Paid)
            ->selectRaw('description, SUM(amount) AS total')
            ->groupBy('description')
            ->toBase()
            ->pluck('total', 'description');

        $ranks = Rank::query()->withCount('achievements')->orderBy('sort_order')->get();
        $lowest = $ranks->first();
        $activeTotal = max(1, $holders->sum());

        $rows = $ranks->map(fn (Rank $rank) => [
            'name' => $rank->name,
            // Members with no rank yet count toward the base rank.
            'members' => ($holders[$rank->id] ?? 0) + ($rank->is($lowest) ? ($holders[0] ?? 0) : 0),
            'achieved' => $rank->achievements_count,
            'bonus_each' => $rank->bonus_amount,
            'bonus_paid' => (int) ($bonusPaid["Rank bonus: {$rank->name}"] ?? 0),
        ])->map(fn (array $row) => [...$row, 'share' => intdiv($row['members'] * 1000, $activeTotal) / 10]);

        return view('admin.reports.ranks', ['rows' => $rows, 'activeTotal' => $holders->sum()]);
    }

    /**
     * @return array{0: string, 1: string, 2: CarbonInterface, 3: CarbonInterface}
     */
    private function params(Request $request): array
    {
        $data = $request->validate([
            'report' => ['nullable', Rule::in(array_keys(FinancialReportService::REPORTS))],
            'granularity' => ['nullable', Rule::in(array_keys(FinancialReportService::GRANULARITIES))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();
        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : $to->copy()->subDays(29)->startOfDay();

        return [$data['report'] ?? 'pnl', $data['granularity'] ?? 'daily', $from, $to];
    }
}
