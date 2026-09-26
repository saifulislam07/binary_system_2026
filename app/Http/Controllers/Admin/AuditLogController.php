<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-only view over Spatie's activity_log (the system's audit log).
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'actor' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'max:50'],
            'member' => ['nullable', 'string', 'max:30'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'admin_only' => ['nullable', 'boolean'],
        ]);

        $member = isset($filters['member'])
            ? Member::query()->where('member_code', strtoupper(trim($filters['member'])))->first()
            : null;

        $entries = Activity::query()
            ->with(['causer', 'subject'])
            ->when($filters['actor'] ?? null, fn ($q, $id) => $q->where('causer_type', (new Admin)->getMorphClass())->where('causer_id', $id))
            ->when($request->boolean('admin_only'), fn ($q) => $q->where('causer_type', (new Admin)->getMorphClass()))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('log_name', $type))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->when(isset($filters['member']), fn ($q) => $member === null
                ? $q->whereRaw('1 = 0')
                : $this->affecting($q, $member))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit.index', [
            'entries' => $entries,
            'filters' => $filters,
            'admins' => Admin::query()->orderBy('name')->pluck('name', 'id'),
            'types' => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name')->filter()->values(),
            'memberNotFound' => isset($filters['member']) && $member === null,
        ]);
    }

    /**
     * Entries about the member themselves, or about their wallet,
     * withdrawals, orders, sales or KYC documents.
     *
     * @param  Builder<Activity>  $query
     * @return Builder<Activity>
     */
    private function affecting(Builder $query, Member $member): Builder
    {
        $related = [
            'member' => [$member->id],
            'wallet' => $member->wallet()->pluck('id')->all(),
            'withdrawal' => $member->withdrawals()->pluck('id')->all(),
            'order' => $member->orders()->pluck('id')->all(),
            'sale' => $member->sales()->pluck('id')->all(),
            'kyc_document' => $member->kycDocuments()->pluck('id')->all(),
        ];

        return $query->where(function (Builder $q) use ($related) {
            foreach ($related as $type => $ids) {
                if ($ids !== []) {
                    $q->orWhere(fn (Builder $w) => $w->where('subject_type', $type)->whereIn('subject_id', $ids));
                }
            }
        });
    }
}
