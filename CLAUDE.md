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
- **Local dev:** Laragon, MySQL 8.4 at 127.0.0.1:3306 (`root`, no password),
  DB `binary_system`. Run `npm run build` before `php artisan test` (Inertia
  pages need the Vite manifest).
