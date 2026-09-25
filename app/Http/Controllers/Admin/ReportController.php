<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
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
