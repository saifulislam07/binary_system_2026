<?php

namespace App\Payments;

use App\Enums\PaymentGateway as GatewayName;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Gateways\BkashGateway;
use App\Payments\Gateways\NagadGateway;
use App\Payments\Gateways\SimulatorGateway;
use App\Payments\Gateways\SslcommerzGateway;

/**
 * Resolves gateway implementations through the container, so tests can swap
 * one with `$this->instance(BkashGateway::class, $fake)`.
 */
class PaymentGatewayManager
{
    public function gateway(GatewayName $name): PaymentGateway
    {
        return app(match ($name) {
            GatewayName::Bkash => BkashGateway::class,
            GatewayName::Sslcommerz => SslcommerzGateway::class,
            GatewayName::Nagad => NagadGateway::class,
            GatewayName::Simulator => SimulatorGateway::class,
        });
    }

    /**
     * Gateways offered at checkout.
     *
     * @return list<GatewayName>
     */
    public function available(): array
    {
        $enabled = array_values(array_filter(array_map(
            fn (string $name) => GatewayName::tryFrom(trim($name)),
            (array) config('payments.enabled'),
        )));

        $enabled = array_values(array_filter($enabled, fn (GatewayName $g) => $g !== GatewayName::Simulator));

        if (config('payments.simulator') && ! app()->isProduction()) {
            $enabled[] = GatewayName::Simulator;
        }

        return $enabled;
    }

    public function isAvailable(GatewayName $name): bool
    {
        return in_array($name, $this->available(), true);
    }
}
