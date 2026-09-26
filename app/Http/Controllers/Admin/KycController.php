<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KycStatus;
use App\Exceptions\MemberAdminException;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\KycDocument;
use App\Services\KycReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KycController extends Controller
{
    public function index(Request $request): View
    {
        $status = KycStatus::tryFrom((string) $request->query('status', 'pending')) ?? KycStatus::Pending;

        return view('admin.kyc.index', [
            'status' => $status,
            'statuses' => KycStatus::cases(),
            'counts' => KycDocument::query()->toBase()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status'),
            'documents' => KycDocument::query()
                ->with(['member:id,member_code,user_id,nid', 'member.user:id,name', 'reviewer:id,name'])
                ->where('status', $status)
                ->oldest('id')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function show(KycDocument $document): View
    {
        return view('admin.kyc.show', [
            'document' => $document->load(['member.user', 'reviewer:id,name']),
            'files' => $document->getMedia('documents')->merge($document->getMedia('photo')),
        ]);
    }

    /**
     * Streams a KYC file from the private disk. Never linked publicly.
     */
    public function media(KycDocument $document, Media $media): StreamedResponse
    {
        abort_unless($media->model_type === $document->getMorphClass() && (int) $media->model_id === $document->id, 404);

        return response()->stream(function () use ($media) {
            $stream = $media->stream();
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $media->mime_type ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($media->file_name).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function approve(Request $request, KycDocument $document, KycReviewService $reviews): RedirectResponse
    {
        return $this->attempt(fn (Admin $admin) => $reviews->approve($document, $admin), $request, 'KYC approved.');
    }

    public function reject(Request $request, KycDocument $document, KycReviewService $reviews): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn (Admin $admin) => $reviews->reject($document, $admin, $data['reason']), $request, 'KYC rejected; the member can resubmit.');
    }

    /**
     * @param  callable(Admin): KycDocument  $action
     */
    private function attempt(callable $action, Request $request, string $done): RedirectResponse
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        try {
            $action($admin);
        } catch (MemberAdminException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.kyc.index')->with('success', $done);
    }
}
