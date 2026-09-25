<?php

namespace App\Http\Controllers;

use App\Enums\KycDocumentType;
use App\Enums\KycStatus;
use App\Http\Requests\KycSubmissionRequest;
use App\Models\KycDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KycController extends Controller
{
    public function index(Request $request): Response
    {
        $member = $request->user('web')?->member;

        abort_if($member === null, 403);

        $documents = $member->kycDocuments()->latest('id')->get();
        $current = $documents->first();

        return Inertia::render('kyc/Index', [
            'status' => $current?->status->value ?? 'not_submitted',
            'canSubmit' => $current === null || $current->status === KycStatus::Rejected,
            'documents' => $documents->map(fn (KycDocument $d) => [
                'id' => $d->id,
                'type' => $d->type === KycDocumentType::Nid ? 'NID' : 'Passport',
                // Never echo the full number back.
                'number' => str_repeat('•', max(0, strlen($d->document_number) - 4)).substr($d->document_number, -4),
                'status' => $d->status->value,
                'submitted' => $d->created_at?->format('d M Y'),
                'rejectionReason' => $d->rejection_reason,
            ]),
            'profile' => [
                'name' => $member->user->name,
                'email' => $member->user->email,
                'phone' => $member->user->phone,
                'address' => $member->address,
            ],
        ]);
    }

    public function store(KycSubmissionRequest $request): RedirectResponse
    {
        $member = $request->member();

        DB::transaction(function () use ($request, $member) {
            $document = new KycDocument([
                'member_id' => $member->id,
                'type' => KycDocumentType::from($request->string('type')->toString()),
                'document_number' => $request->string('document_number')->toString(),
            ]);
            $document->forceFill(['status' => KycStatus::Pending])->save();

            $document->addMediaFromRequest('document')->toMediaCollection('documents');
            $document->addMediaFromRequest('photo')->toMediaCollection('photo');

            activity('kyc')
                ->performedOn($document)
                ->causedBy($member->user)
                ->withProperties(['type' => $document->type->value, 'ip' => $request->ip()])
                ->log('KYC submitted');
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('KYC submitted. We will review it shortly.')]);

        return to_route('kyc.index');
    }

    public function updateAddress(Request $request): RedirectResponse
    {
        $member = $request->user('web')?->member;

        abort_if($member === null, 403);

        $validated = $request->validate(['address' => ['required', 'string', 'max:500']]);

        $member->update(['address' => $validated['address']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address updated.')]);

        return to_route('kyc.index');
    }
}
