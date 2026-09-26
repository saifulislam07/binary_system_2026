<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\Bonus;
use App\Models\Commission;
use App\Models\CommissionCycle;
use App\Models\KycDocument;
use App\Models\Member;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Short, stable names in polymorphic columns (wallet_transactions.reference_type, activity_log, media).
        Relation::morphMap([
            'member' => Member::class,
            'order' => Order::class,
            'sale' => Sale::class,
            'commission' => Commission::class,
            'bonus' => Bonus::class,
            'withdrawal' => Withdrawal::class,
            'refund' => Refund::class,
            'wallet' => Wallet::class,
            'commission_cycle' => CommissionCycle::class,
            'kyc_document' => KycDocument::class,
            'announcement' => Announcement::class,
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Blade pagination links (admin panel) use AdminLTE 4's Bootstrap 5 markup.
        Paginator::useBootstrapFive();

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
