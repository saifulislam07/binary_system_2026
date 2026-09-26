# Binary Business Management System — Project Context (Phase 0)

Source of truth for every phase. Full phase plan: `binary-business-prompt-series.md`.

```
You are building a "Binary Business Management System" — a Laravel-based
platform for managing members, sponsors, a binary (left/right) tree,
product sales, binary commission, member wallets, and withdrawals.

CORE BUSINESS RULES (do not deviate without asking):

1. MEMBER: every member has a Sponsor (who referred them — a relationship,
   not a tree position) and a Placement (their actual Left/Right position
   in the binary tree). These can differ — a member may be sponsored by A
   but placed under B due to spillover (vacant-position placement).

2. UNIQUE ID: every member gets an auto-generated ID on activation, format
   MBR-100001, MBR-100002, ... (zero-padded, sequential, never reused).

3. BINARY TREE: every member has exactly one Left child slot and one Right
   child slot. Placement search for a new member: BFS from the intended
   parent/position downward to find the first vacant slot on the chosen
   side (Left or Right) — this is the spillover algorithm.

4. SALES: real products/packages only (Basic ৳1,000 / Standard ৳5,000 /
   Premium ৳10,000 / Business ৳25,000 — configurable, not hardcoded).
   Every completed order creates a Sale record with a BV (Business Volume)
   value, which is what flows into team-volume calculations — NOT the
   cash amount directly.

5. TEAM VOLUME & MATCHING: each sale's BV accrues to the Left or Right
   volume of every upline member on that side (up the placement tree,
   not the sponsor chain). Matching = min(left_volume, right_volume) per
   member per commission cycle. Matched volume is consumed from both
   sides; the unmatched remainder carries forward only if
   `commission_rules.carry_forward_enabled` is true.

6. BINARY COMMISSION = matched_volume × commission_rate (rate configurable
   in `commission_rules`, default 10%). Subject to daily/weekly/monthly
   caps in `commission_rules` (configurable, not hardcoded — defaults
   ৳5,000 / ৳20,000 / ৳50,000). Any amount above the cap is either voided
   or carried forward per a `cap_overflow_behavior` setting — implement
   both, admin picks one.

7. REFERRAL BONUS = qualifying sale amount × referral_rate (default 5%),
   paid to the Sponsor (not the placement upline), on genuine qualifying
   sales only (define "qualifying" via a `is_qualifying` flag on Package).

8. WALLET: every member has one wallet. Ledger entries only, never a
   single mutable balance column that gets overwritten — balance is
   always derivable as SUM(credits) - SUM(debits) from
   wallet_transactions, and also cached on wallets.balance for fast reads,
   updated inside the same DB transaction as each ledger entry.

9. WITHDRAWAL: member requests ≥ minimum (configurable, default ৳1,000).
   State machine: pending → approved → processing → paid (or → rejected
   from pending/approved). Only an admin transitions states. A rejected
   withdrawal reverses the wallet hold.

10. REFUND: refunding a sale must reverse its BV contribution and any
    commission/referral bonus already generated from it — write this as
    an explicit reversal transaction, never delete/mutate the original.

11. RANKS: Member → Bronze → Silver → Gold → Platinum → Diamond, computed
    by a scheduled job from rules in a `ranks` table (thresholds on
    personal sales, team sales, active team size — configurable per
    rank, not hardcoded).

12. FRAUD PREVENTION: duplicate mobile/NID detection at registration,
    IP/device logging on registration and login, an audit_logs entry for
    every admin action that changes money, tree structure, or member
    status.

CONVENTIONS:
- Laravel 13, PHP 8.3+, MySQL 8, Vue 3 + Inertia.js for the member-facing
  app, AdminLTE + Blade for the admin panel (do NOT mix Vue into the
  admin panel unless a phase explicitly says to add a Livewire/Vue
  island for one component).
- Money fields: integer (poysha/paisa, i.e. smallest currency unit) or
  decimal(12,2) — pick one and use it everywhere; do not mix.
- All binary-tree and wallet mutations happen inside DB transactions
  (`DB::transaction`) with row locking (`lockForUpdate`) on the
  member/wallet rows involved.
- Every money-moving or structure-changing action is logged via
  Spatie ActivityLog.
- Use Form Requests for validation, Policies for authorization, Actions/
  Services for business logic (keep controllers thin).
- Write a Feature test for every business rule above before moving to
  the next phase touching that rule.
- Bangladeshi context: phone numbers (+880), bKash/Nagad/SSLCommerz
  payment gateways, BDT currency, Bengali+English bilingual labels where
  the UI is member-facing.
```

## Project decisions (made in Phase 1 — follow these everywhere)

- **Stack deviation:** the repo was created from the official Laravel **13**
  Vue starter kit (Fortify + Inertia v3 + Wayfinder + Tailwind 4, Vite+),
  which supersedes Breeze. Member auth uses Fortify; do not install Breeze.
  The starter kit's "Teams" feature was removed in Phase 1 — "team" in this
  codebase always means the MLM binary/sponsor team.
- **Money = integer poysha** (`bigInteger`, 1 BDT = 100 poysha) everywhere:
  prices, wallet amounts, commissions, caps, thresholds. Never float/decimal.
  Formatting/parsing helper: `App\Support\Money`.
- **BV = integer centi-BV** (same ×100 scale as money) in `bigInteger`
  columns, so `matched_bv × rate` yields poysha directly.
- **Rates = integer basis points** (1000 = 10.00%). Compute with
  `App\Support\Money::percentOf()` — integer math, rounds down.
- **Guards:** `web` → `App\Models\User` (members; Inertia/Vue app).
  `admin` → `App\Models\Admin` (`admins` table; AdminLTE + Blade under
  `/admin`, via `jeroennoten/laravel-adminlte`). Admin roles/permissions
  live on the `admin` guard; the `member` role lives on the `web` guard.
- **Tests:** PHPUnit (not Pest), against MySQL database `binary_system_test`
  — row-locking and concurrency tests need real InnoDB.
- **Enums:** status/type columns are MySQL `enum`s with literal values in the
  migration, cast to PHP enums in `app/Enums` (add an `@property` line on the
  model for each enum cast so PHPStan sees the enum type). `PayoutStatus` is
  shared by `commissions` and `bonuses`.
- **Mass assignment:** columns only services may change (wallet `balance`,
  withdrawal `status`/`admin_id`, KYC review fields) are deliberately NOT in
  `#[Fillable]` — set them with `forceFill()` inside the owning service.
- **Seeders:** `ReferenceDataSeeder` (roles, packages, commission_rules,
  ranks, settings, admin) is idempotent and production-safe — it never
  overwrites admin-edited values. `DemoNetworkSeeder` (20-member tree
  MBR-100001…MBR-100020, root = test@example.com) runs outside production
  only. Phase 3's member-code sequence must start after the highest existing
  code.
- **Tree integrity:** `members.placement_parent_id/placement_side` and the
  parent's `binary_nodes.{left,right}_child_id` are the same fact stored
  twice — always update both in one transaction.
  `SeededNetworkTest` checks they agree in both directions.
- **Activation (Phase 3):** `PlacementService::activateMember()` is the ONLY
  way a member becomes active: it assigns the code (`MemberCodeGenerator` →
  `sequences` row lock), places the member (BFS spillover under the sponsor
  on `preferred_side`), creates the binary node + wallet, logs activity, and
  is idempotent (safe for repeated payment callbacks). Seeders use it too.
- **Tree lock order:** lock `binary_nodes` rows top-down (ancestors before
  descendants = ascending `binary_nodes.id`), always with `lockForUpdate()`
  reads inside the transaction. Volume accrual (Phase 5) must follow the
  same order or it can deadlock with placement.
- **Duplicates (rule #12):** mobile/NID may belong to only one _active_
  member; pending sign-ups don't block. Phones are stored normalized
  (`App\Support\PhoneNumber`, +8801XXXXXXXXX), NIDs as digits only.
- **Payments (Phase 4):** gateways implement `App\Payments\Contracts\PaymentGateway`
  and MUST verify callbacks server-to-server (bKash execute/status,
  SSLCommerz validation API, Nagad verify) — never trust query/POST data.
  `OrderPaymentService::handle()` is the only place an order becomes paid:
  it locks the order, is idempotent, rejects amount mismatches (flags them in
  the activity log), activates pending members, creates the Sale, and fires
  `SaleCompleted` _inside_ the transaction (Phase 5 listeners hook there).
  Never hold DB locks across gateway HTTP calls. `simulator` gateway is
  dev-only. Refunds go through `RefundService` → `ReverseCommissionForSale`.
- **Commission engine (Phase 5):** BV lives in `volume_lots` (one per sale ×
  upline ancestor); `binary_nodes.{side}_volume` MUST always equal the sum of
  that member's open lots (`remaining`) — every change goes through
  `TeamVolumeService` (accrual), `MatchingService` (FIFO consumption,
  flush) or `CommissionReversalService` (refunds), each writing
  `volume_consumptions` rows. Tests assert this invariant via
  `Tests\Support\BuildsNetwork::assertLedgersConsistent()`.
  Binary commission: `MatchingService::runCycle()` (`php artisan
commission:run {date}`), one transaction per member, idempotent per
  (member, cycle). Caps sum _paid_ binary commission rows in the
  day/week/month window; cap value ≤ 0 = no cap. Overflow: `void` → voided
  row; `carry_forward` → `binary_nodes.deferred_commission`, released first
  in later cycles. Weeks start on `config('business.week_starts_on')`
  (Saturday).
  Reversals are negative commission rows (status `reversed`,
  `reverses_commission_id`) + wallet `reversal` debits, which may push a
  wallet negative. Lock order everywhere: binary_nodes (ascending id) →
  lots → wallets.
- **Wallet:** all credits/debits go through `WalletService` (row lock,
  ledger row + cached balance in one transaction, activity log). Balance =
  Σcredits − Σdebits over non-voided rows.
- **Wallet writes are guarded by a test:** `WalletLedgerTest::test_only_wallet_service_writes_to_wallets`
  fails if anything outside `WalletService` creates ledger rows or writes a
  wallet balance. Don't weaken it — route the new code through the service.
- **Withdrawals (Phase 7):** `WithdrawalService` only. `request()` writes the
  withdrawal + a _pending_ wallet debit (the hold, `withdrawals.wallet_transaction_id`)
  in one transaction. Transitions follow `WithdrawalStatus::allowedTransitions()`
  and require an `Admin` with `manage-withdrawals` (checked in the service).
  `markPaid()` → hold `completed`; `reject()` (reason required) → hold
  `voided` via `WalletService::void()`, which restores the balance. We void
  instead of writing a credit-back — doing both would double-refund.
  Lock order: withdrawal row → wallet.
- **Member dashboard (Phase 8):** pages that need a placed member (team,
  income, referral) sit behind the `member.active` middleware (pending →
  checkout, suspended → 403). Tree data comes from `TeamService`
  (`legCounts()` recursive CTE, `subtree()` level-by-level, max depth 4);
  `team.tree` JSON only serves nodes in the viewer's own downline
  (`isInDownline()`). Charts follow the dataviz skill: slot-1 blue
  `#2a78d6` / dark `#3987e5`, tokens in an _unscoped_ `<style>` block so
  the app's `.dark` class can override them (scoped `:global(.dark)` does
  not work), drawn at measured pixel width (never a scaled viewBox).
- **Admin panel (Phase 9):** every admin route group is gated with
  `can:<permission>` in `routes/admin.php`, and the matching menu item in
  `config/adminlte.php` carries the same `'can'` — keep them in sync
  (`AdminNavigationTest` checks both per role). Company metrics (revenue, cost of goods, commission, expenses, profit)
  are defined once in `AdminDashboardService` — reuse it for reports.
  Seeded limited roles: `support` (members, KYC), `finance` (sales,
  withdrawals, reports).
- **Admin management (Phase 10):** member changes go through
  `MemberAdminService` (reason required for status/package changes; logs
  old/new values). Tree moves go through `PlacementAdjustmentService`:
  moves a member + downline to a vacant slot and transfers the subtree's
  volume lots to the new upline (consumption kind `transferred`); refused
  once any of that volume was matched/flushed, into own downline, or into a
  taken slot. Reports come from `FinancialReportService` (same shape for
  every report + CSV export), whose P&L reuses
  `AdminDashboardService::metricsForRange()`. Business settings (rates,
  caps, overflow behavior, carry-forward, min withdrawal) are edited on
  `/admin/settings` (`manage-settings`); UI takes % and taka, stores bps
  and poysha. Blade admin pages must not use Vue — plain JS with
  `textContent` only (see the tree browser).
- **Ranks & bonuses (Phase 11):** `RankService::evaluate()` promotes to the
  highest rank whose personal sales, team sales (both legs' lifetime BV) and
  active team thresholds are all met; never demotes; ranks passed on the way
  are each recorded and paid once (unique member+rank); bonus = commission
  row type `rank` + wallet `rank_bonus`; `members.current_rank_id` holds the
  highest. Leadership/sales bonuses come from `bonus_rules` (paid once per
  rule — unique member+rule on `bonuses`); performance bonuses are
  admin-awarded (`manage-settings`). All bonuses = `bonuses` row + wallet
  credit via `BonusService`, and count as payouts in the P&L. The nightly
  `ranks:evaluate` (00:45, after `commission:run`) uses
  `MemberStatsService::forAll()` bulk queries; `forMember()` must stay in
  agreement (`MemberStatsTest`).
- **Fraud & audit (Phase 12):** duplicate mobile/NID is enforced by the DB:
  stored generated columns `members.active_phone` / `active_nid` (value only
  while `status = active`) carry unique indexes. `members.phone` mirrors
  `users.phone` — set it wherever a member's phone is written. A violation
  at activation becomes `DuplicateMemberException` (a `PlacementException`);
  `OrderPaymentService` then keeps the order paid, creates no sale, and
  raises a `FraudFlag` (`activation_blocked_duplicate`) for an admin.
  `login_history` records registration, logins and failed attempts on
  both guards (`RecordAuthenticationActivity` → `LoginAuditService`); a
  member login from an unseen device/IP is flagged and the member notified
  (`NewDeviceLogin`), never blocked. `FraudScanService` flags rapid
  withdrawals and withdrawals exceeding legitimate earnings (paid commission
  net of reversals + paid bonuses; deferred/voided/adjustments excluded), at
  request time and hourly via `fraud:scan`. `FraudFlag::raise()` is
  idempotent per (member, type, subject). Flags only inform — nothing
  auto-blocks. Admin KYC review goes through `KycReviewService`; files are
  streamed from the private disk by `KycController::media()` only. The
  audit viewer (`/admin/audit`, `manage-settings`) reads Spatie's
  `activity_log`; every money/tree/status change must log there.
- **Dates are immutable:** the starter kit calls `Date::use(CarbonImmutable::class)`,
  so `now()` and model date casts return `CarbonImmutable`. Type-hint
  `Carbon\CarbonInterface`, never `Illuminate\Support\Carbon`.
- **Morph map:** polymorphic columns store short names (`commission`,
  `withdrawal`, `order`, …) — see `AppServiceProvider::boot()`; add new
  models there when they become a morph target.
- **Local dev:** Laragon, MySQL 8.4 at 127.0.0.1:3306 (`root`, no password),
  DB `binary_system`. Run `npm run build` before `php artisan test` (Inertia
  pages need the Vite manifest).
