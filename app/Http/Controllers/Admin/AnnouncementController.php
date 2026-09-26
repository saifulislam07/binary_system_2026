<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAnnouncementRequest;
use App\Models\Admin;
use App\Models\Announcement;
use App\Models\Package;
use App\Models\Rank;
use App\Services\AnnouncementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('admin.announcements.index', [
            'announcements' => Announcement::query()->with(['admin:id,name', 'minRank:id,name', 'package:id,name'])->latest('id')->paginate(20),
            'ranks' => Rank::query()->orderBy('sort_order')->pluck('name', 'id'),
            'packages' => Package::query()->orderBy('sort_order')->pluck('name', 'id'),
            'enabledChannels' => array_keys(array_filter((array) config('notifications.channels'))),
        ]);
    }

    /**
     * How many members the chosen segment reaches (for the compose form).
     */
    public function preview(Request $request, AnnouncementService $announcements): JsonResponse
    {
        $segment = $request->validate(StoreAnnouncementRequest::segmentRules());

        return response()->json([
            'recipients' => $announcements->recipients(new Announcement($segment))->count(),
        ]);
    }

    public function store(StoreAnnouncementRequest $request, AnnouncementService $announcements): RedirectResponse
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        $announcement = $announcements->publish($admin, $request->validated());

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', "Announcement queued for {$announcement->recipients} member(s).");
    }
}
