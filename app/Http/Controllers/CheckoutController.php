<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Enums\PaymentGateway;
use App\Exceptions\PaymentException;
use App\Http\Requests\CheckoutRequest;
use App\Models\Package;
use App\Payments\PaymentGatewayManager;
use App\Services\CheckoutService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CheckoutController extends Controller
{
    public function index(Request $request, PaymentGatewayManager $gateways): Response
    {
        $member = $request->user('web')?->member;

        abort_if($member === null || $member->status === MemberStatus::Suspended, 403);

        return Inertia::render('checkout/Index', [
            'memberStatus' => $member->status->value,
            'selectedPackageId' => $member->package_id,
            'packages' => Package::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Package $package) => [
                    'id' => $package->id,
                    'name' => $package->name,
                    'description' => $package->description,
                    'price' => Money::format($package->price),
                    'bv' => number_format(intdiv($package->bv_value, Money::SCALE)),
                ]),
            'gateways' => array_map(fn (PaymentGateway $g) => [
                'value' => $g->value,
                'label' => $g->label(),
            ], $gateways->available()),
        ]);
    }

    public function store(CheckoutRequest $request, CheckoutService $checkout): HttpResponse|RedirectResponse
    {
        $member = $request->user('web')->member;
        $package = Package::query()->findOrFail($request->integer('package_id'));

        try {
            [, $initiation] = $checkout->checkout($member, $package, PaymentGateway::from($request->string('gateway')->toString()));
        } catch (PaymentException $e) {
            return back()->withErrors(['gateway' => $e->getMessage()]);
        }

        // External redirect to the gateway's payment page.
        return Inertia::location($initiation->redirectUrl);
    }
}
