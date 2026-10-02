<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Sponsor picker on the registration form: active members matching part of
 * a member ID (2+ digits) or a name (3+ letters). Public, so it returns at
 * most a few matches with only the first name in full ("Rahim U.") — enough
 * to recognise the person who invited you, not a member directory. The
 * exact-ID lookup (SponsorLookupController) confirms the chosen sponsor.
 */
class SponsorSearchController extends Controller
{
    private const LIMIT = 8;

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $digits = preg_replace('/\D/', '', $term) ?? '';
        $byCode = preg_match('/^(?:[A-Za-z]{0,3}-?)?\d+$/', $term) === 1;

        if (($byCode && strlen($digits) < 2) || (! $byCode && mb_strlen($term) < 3)) {
            return response()->json(['results' => []]);
        }

        $members = Member::query()
            ->with('user:id,name')
            ->where('status', MemberStatus::Active)
            ->whereNotNull('member_code')
            ->when(
                $byCode,
                fn ($q) => $q->where('member_code', 'like', '%'.$digits.'%'),
                fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%')),
            )
            ->orderBy('member_code')
            ->limit(self::LIMIT)
            ->get();

        return response()->json([
            'results' => $members->map(fn (Member $member) => [
                'code' => $member->member_code,
                'name' => self::shortName($member->user->name),
            ])->values(),
        ]);
    }

    /**
     * "Rahim Uddin Ahmed" → "Rahim U. A."
     */
    public static function shortName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $first = array_shift($parts) ?? '';

        return trim($first.' '.implode(' ', array_map(fn (string $part) => Str::upper(mb_substr($part, 0, 1)).'.', $parts)));
    }
}
