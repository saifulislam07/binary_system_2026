<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\Bonus;
use App\Models\BonusRule;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Commission;
use App\Models\CommissionCycle;
use App\Models\KycDocument;
use App\Models\Member;
use App\Models\MembershipSection;
use App\Models\Order;
use App\Models\Package;
use App\Models\Product;
use App\Models\Rank;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Notifications\Channels\BulkSmsBdChannel;
use App\Notifications\Channels\MetaWhatsAppChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
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
        // Notifications name SmsChannel; the configured gateway stands in for it
        // (the plain classes only write to the log).
        $this->app->bind(SmsChannel::class, fn ($app) => match (config('services.sms.driver')) {
            'bulksmsbd' => $app->make(BulkSmsBdChannel::class),
            default => new SmsChannel,
        });
        $this->app->bind(WhatsAppChannel::class, fn ($app) => match (config('services.whatsapp.driver')) {
            'meta' => $app->make(MetaWhatsAppChannel::class),
            default => new WhatsAppChannel,
        });
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
            'package' => Package::class,
            'rank' => Rank::class,
            'bonus_rule' => BonusRule::class,
            'category' => Category::class,
            'product' => Product::class,
            'brand' => Brand::class,
            'membership_section' => MembershipSection::class,
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

        // N+1 guard: outside production, lazy-loading a relation on a model
        // that came from a collection throws, so tests and local use catch it.
        Model::preventLazyLoading(! app()->isProduction());

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
