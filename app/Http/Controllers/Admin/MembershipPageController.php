<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveMembershipSectionRequest;
use App\Models\Admin;
use App\Models\MembershipSection;
use App\Services\MembershipPageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Settings → Membership page: the text sections of the public membership
 * page. Rates, packages and ranks on that page come from the live settings.
 */
class MembershipPageController extends Controller
{
    public function index(): View
    {
        return view('admin.membership.index', [
            'sections' => MembershipSection::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.membership.form', [
            'section' => new MembershipSection(['is_active' => true, 'sort_order' => (int) MembershipSection::query()->max('sort_order') + 1]),
        ]);
    }

    public function store(SaveMembershipSectionRequest $request, MembershipPageService $page): RedirectResponse
    {
        $section = $page->save(null, $request->sectionData(), $this->admin($request));

        return redirect()->route('admin.membership.index')->with('success', "Section “{$section->title_en}” added.");
    }

    public function edit(MembershipSection $section): View
    {
        return view('admin.membership.form', ['section' => $section]);
    }

    public function update(SaveMembershipSectionRequest $request, MembershipSection $section, MembershipPageService $page): RedirectResponse
    {
        $page->save($section, $request->sectionData(), $this->admin($request));

        return redirect()->route('admin.membership.index')->with('success', "Section “{$section->title_en}” saved.");
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        return $admin;
    }
}
