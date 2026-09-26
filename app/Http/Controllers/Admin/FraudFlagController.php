<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\FraudFlag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FraudFlagController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['open', 'reviewed', 'dismissed'], true) ? (string) $request->query('status') : 'open';

        return view('admin.fraud.index', [
            'status' => $status,
            'counts' => FraudFlag::query()->toBase()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status'),
            'flags' => FraudFlag::query()
                ->with(['member:id,member_code,user_id,activated_at', 'member.user:id,name', 'subject', 'reviewer:id,name'])
                ->where('status', $status)
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function review(Request $request, FraudFlag $flag): RedirectResponse
    {
        $data = $request->validate([
            'outcome' => ['required', Rule::in(['reviewed', 'dismissed'])],
            'note' => ['required', 'string', 'max:1000'],
        ]);

        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        DB::transaction(function () use ($flag, $data, $admin) {
            $flag = FraudFlag::query()->lockForUpdate()->findOrFail($flag->id);
            abort_if($flag->status !== 'open', 409, 'This flag was already handled.');

            $flag->forceFill([
                'status' => $data['outcome'],
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_note' => $data['note'],
            ])->save();

            activity('fraud')
                ->performedOn($flag->member()->firstOrFail())
                ->causedBy($admin)
                ->withProperties(['flag_id' => $flag->id, 'type' => $flag->type, 'outcome' => $data['outcome'], 'note' => $data['note']])
                ->log('Fraud flag '.$data['outcome']);
        });

        return redirect()->route('admin.fraud.index')->with('success', 'Flag marked '.$data['outcome'].'.');
    }
}
