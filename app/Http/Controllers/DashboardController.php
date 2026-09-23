<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $member = $request->user('web')?->member()->with('package:id,name')->first();

        return Inertia::render('Dashboard', [
            'member' => $member === null ? null : [
                'code' => $member->member_code,
                'status' => $member->status->value,
                'package' => $member->package?->name,
            ],
        ]);
    }
}
