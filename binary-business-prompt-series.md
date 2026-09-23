# Binary Business Management System — Claude Code Prompt Series

**স্ট্যাক:** Laravel 13 · Vue 3 + Inertia.js (Member-facing) · AdminLTE + Blade (Admin Panel) · MySQL · Spatie (Permission, MediaLibrary, ActivityLog, Backup, Sluggable) · bKash / SSLCommerz / Nagad

**নিয়ম:** প্রতিটি Phase নিজে থেকে সম্পূর্ণ (self-contained) — নতুন Claude Code session-এও পেস্ট করা যাবে। প্রতিটি Phase শেষে একটি `git commit` আছে — commit করার পর পরের Phase শুরু করুন। Phase 0 সবার আগে চালাবেন, এটি বাকি সব Phase-এর জন্য প্রজেক্টের "sourceof truth"।

---

## 📌 PHASE 0 — Project Context (প্রতিটি নতুন session-এ প্রথমে পেস্ট করুন, অথবা `CLAUDE.md` হিসেবে সেভ করুন)

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

Acknowledge you understand these rules, then wait for the Phase prompt.
```

---

## PHASE 1 — Project Foundation & Setup

```
Set up the Laravel project foundation for the Binary Business Management
System (rules as given in the project context above).

1. Fresh Laravel 13 project. Install and configure:
   - Laravel Breeze (Inertia + Vue 3 stack) for member-facing auth
   - A separate `admin` guard + AdminLTE (via a Blade-based admin
     starter — do not use Breeze scaffolding for admin)
   - Spatie packages: laravel-permission, laravel-medialibrary,
     laravel-activitylog, laravel-backup, laravel-sluggable
   - Laravel Sanctum (for future mobile API)
2. Roles & permissions (Spatie): `admin`, `member` as base roles, plus
   granular permissions for admin: manage-members, manage-tree,
   manage-sales, manage-withdrawals, manage-kyc, manage-settings,
   view-reports.
3. `.env.example` entries (placeholder values) for: DB, mail, SMS
   gateway (generic driver interface), bKash, SSLCommerz, Nagad API
   credentials, and a `COMMISSION_CYCLE` config value (daily/weekly).
4. Base folder structure: `app/Services`, `app/Actions`,
   `app/DTOs`, `app/Support` for business logic separate from
   controllers.
5. Install and configure Pest (or PHPUnit, your call) for testing, and
   a `.github/workflows/tests.yml` CI stub that runs migrations + tests
   on push.
6. README.md: setup instructions, stack summary, and a "Business Rules"
   section that links back to this document.

Acceptance: `php artisan serve` boots, `/login` (member) and `/admin/login`
(admin) both render with no errors, `php artisan test` passes with the
default scaffold tests.

Commit: git add -A && git commit -m "Phase 1: project foundation, auth scaffolding, roles & permissions"
```

---

## PHASE 2 — Database Schema & Models

```
Build the full database schema as migrations + Eloquent models with
relationships, casts, and factories. Tables (add indexes on every
foreign key and on any column used in a WHERE/ORDER in later phases):

- users (base auth: id, name, email, phone, password, ip_registered,
  device_registered, is_active, timestamps)
- members (user_id FK, member_code [MBR-100001 format, unique,
  sequential], sponsor_id [FK members, nullable], placement_parent_id
  [FK members, nullable], placement_side [enum: left/right, nullable],
  package_id FK, status [enum: pending/active/suspended], activated_at)
- binary_nodes (member_id FK unique, left_child_id FK members nullable,
  right_child_id FK members nullable, left_volume decimal, right_volume
  decimal, left_volume_carry decimal, right_volume_carry decimal) —
  denormalized for fast tree math, kept in sync via events
- packages (name, price, bv_value, is_qualifying bool, is_active, sort_order)
- products (optional, if packages bundle multiple products — name, sku, price)
- orders / order_items (member_id, package_id, amount, status, paid_at)
- sales (order_id FK, member_id FK, bv_value, status
  [completed/refunded], refunded_at)
- wallets (member_id FK unique, balance decimal cached)
- wallet_transactions (wallet_id FK, type [enum: referral_bonus,
  binary_commission, rank_bonus, sales_bonus, withdrawal, adjustment,
  refund, reversal], amount, direction [credit/debit], reference_type,
  reference_id, description, status, timestamps)
- commissions (member_id FK, type [referral/binary/rank/leadership/
  sales/performance], source_sale_id FK nullable, amount, cycle_date,
  status [pending/paid/voided/reversed])
- commission_rules (key, value, description) — key/value config: rates,
  caps, carry_forward_enabled, cap_overflow_behavior
- commission_cycles (cycle_date, status [running/closed], closed_at)
- team_volumes (member_id FK, cycle_id FK, left_volume, right_volume,
  matched_volume, carried_left, carried_right)
- withdrawals (member_id FK, amount, method [bank/mobile_banking],
  account_details json, status [pending/approved/processing/paid/
  rejected], admin_id FK nullable, processed_at)
- withdrawal_methods (member_id FK, type, details json, is_default)
- payments (order_id FK nullable, withdrawal_id FK nullable, gateway
  [bkash/sslcommerz/nagad], gateway_ref, amount, status, raw_response json)
- kyc_documents (member_id FK, type [nid/passport], document_number,
  file_path, status [pending/approved/rejected], reviewed_by FK nullable)
- ranks (name, sort_order, min_personal_sales, min_team_sales,
  min_active_team, bonus_amount)
- rank_achievements (member_id FK, rank_id FK, achieved_at)
- bonuses (member_id FK, type [sponsor/binary/rank/leadership/sales/
  performance], amount, cycle_date, status)
- notifications (use Laravel's built-in notifications table)
- expenses (category, amount, description, date)
- income_transactions (source, amount, description, date)
- refunds (sale_id FK, amount, reason, processed_by FK, processed_at)
- audit_logs (use Spatie ActivityLog's activity_log table — do not
  create a duplicate)
- settings (key, value) — general app settings (min withdrawal, etc.)

Add model relationships (Member hasOne wallet, belongsTo sponsor,
belongsTo placementParent, hasOne binaryNode, hasMany sales, etc.),
factories for every table, and a DatabaseSeeder that seeds: default
packages, default commission_rules, default ranks, an admin user, and
20 sample members already placed in a small binary tree (for dev/testing).

Acceptance: `php artisan migrate:fresh --seed` runs clean, a feature test
asserts the seeded tree has correct parent/child relationships and every
member has a wallet.

Commit: git add -A && git commit -m "Phase 2: full database schema, models, factories, seeder"
```

---

## PHASE 3 — Member Registration & Binary Placement Engine

```
Build member registration with sponsor selection and the binary
placement (spillover) algorithm.

1. Registration form (Vue + Inertia): name, mobile, email, NID, address,
   password, sponsor_id (validated against an existing active member —
   accept sponsor's member_code as input, resolve to ID), preferred
   position (left/right), package selection, payment step.
2. `PlacementService` (app/Services/PlacementService.php):
   - `findVacantSlot(Member $underMember, string $side): Member` — BFS
     down the tree from $underMember on the given $side until it finds
     a member with an empty left/right child slot; returns that member.
   - `place(Member $newMember, Member $sponsor, string $preferredSide)`:
     resolves the actual placement parent via findVacantSlot starting
     from the sponsor, sets placement_parent_id + placement_side on the
     new member, sets the corresponding left_child_id/right_child_id on
     binary_nodes for the parent — all inside a DB transaction with
     `lockForUpdate` on the parent's binary_nodes row.
3. `MemberCodeGenerator` — generates MBR-100001-style codes atomically
   (use a DB-backed counter or `lockForUpdate` on a sequence row, not
   `Member::count()+1` which races under concurrent registration).
4. Registration only creates the member in `pending` status; activation
   (status → active, member_code assigned, placement executed) happens
   after payment succeeds (wire this to Phase 4's payment webhook —
   for now, add an `activateMember()` method Phase 4 will call, and a
   temporary admin action to manually activate for testing).
5. Duplicate detection: reject registration if mobile or NID already
   exists on an active member (fraud prevention rule #12).

Write feature tests: (a) placement under a sponsor with two vacant
slots picks the direct child; (b) placement when direct slots are full
correctly spills to the next vacant slot down that side; (c) member
codes are sequential and unique under concurrent registration
(dispatch multiple in a loop, assert no duplicates); (d) duplicate
mobile/NID is rejected.

Commit: git add -A && git commit -m "Phase 3: registration, sponsor selection, binary placement engine"
```

---

## PHASE 4 — Package/Product Sales & Payment Gateway

```
Build the package purchase flow with bKash, SSLCommerz, and Nagad.

1. Package listing + checkout (Vue + Inertia) showing Basic/Standard/
   Premium/Business (from the `packages` table, admin-editable, not
   hardcoded).
2. Order creation (`orders`, `order_items`) on checkout, status `pending`.
3. Payment gateway integration — build one `PaymentGatewayInterface`
   with `initiate(Order $order)` and `handleCallback(Request $request)`,
   then concrete `BkashGateway`, `SslcommerzGateway`, `NagadGateway`
   implementations (use sandbox credentials from .env; stub the actual
   HTTP calls behind an interface so they're mockable in tests).
4. On successful payment callback: mark order `paid`, create the `sales`
   record with the package's `bv_value`, call
   `PlacementService::activateMember()` from Phase 3 (member goes
   pending → active, member_code assigned, tree placement executed),
   create the `payments` record.
5. On failed/cancelled payment: order stays `pending`/`failed`, no
   member activation, no sale.
6. Refund flow: admin-triggered refund (Phase 10 will build the UI —
   for now build `RefundService::refund(Sale $sale, string $reason)`)
   that: marks sale `refunded`, creates a `refunds` record, and queues
   a `ReverseCommissionForSale` job (implement the job body in Phase 5
   once commission logic exists — for now leave a TODO with the exact
   method signature so Phase 5 fills it in).

Feature tests: successful payment activates the member and creates a
sale with correct BV; failed payment leaves the member pending; refund
marks the sale refunded and does not double-refund.

Commit: git add -A && git commit -m "Phase 4: package checkout, payment gateways, sale + activation flow"
```

---

## PHASE 5 — Team Volume, Matching & Commission Engine

```
Build the core binary commission engine — the financial heart of the
system, so be precise and test heavily.

1. `TeamVolumeService::accrueVolume(Sale $sale)`: walks up the
   PLACEMENT tree (not sponsor chain) from the selling member to the
   root, and for every ancestor, adds the sale's bv_value to that
   ancestor's left_volume or right_volume on `binary_nodes` (whichever
   side the walk came up through) — inside a transaction with row
   locks. Call this from the Phase 4 sale-creation flow.
2. `MatchingService::runCycle(Carbon $cycleDate)`: for every member with
   left_volume > 0 and right_volume > 0: matched = min(left, right);
   deduct matched from both sides (respecting `carry_forward_enabled`
   from commission_rules — if disabled, zero out the unmatched side
   instead of leaving it); write a `team_volumes` row for this cycle;
   create a `commissions` row (type=binary, amount = matched ×
   commission_rate from commission_rules) capped by the daily/weekly/
   monthly caps already earned this period — implement cap checking by
   summing existing `commissions` for that member in the relevant
   window before applying the new one, and apply `cap_overflow_behavior`
   (void vs carry forward) to any excess.
3. `ReferralBonusService::payOnSale(Sale $sale)`: if the sale's package
   is `is_qualifying`, credit the sponsor `referral_bonus` = sale
   amount × referral_rate from commission_rules; create a `commissions`
   row (type=referral) and a `wallet_transactions` credit.
4. Wire both `MatchingService` commission rows and
   `ReferralBonusService` payouts into `WalletService::credit()` (build
   this now): every commission/bonus that reaches `status=paid` writes
   a `wallet_transactions` credit row and updates `wallets.balance` in
   the same transaction.
5. Implement the `ReverseCommissionForSale` job stubbed in Phase 4:
   reverses the BV contribution up the tree (subtract, not add-negative
   — keep the tree math consistent), voids or reverses any
   `commissions`/`wallet_transactions` that were generated from that
   sale (write reversal entries, never delete), inside one transaction.
6. Schedule `MatchingService::runCycle()` via `app/Console/Kernel.php`
   on the cadence from `COMMISSION_CYCLE` (.env).

Feature tests (be thorough — this is the module most likely to have
real money bugs): matching picks the correct min(left,right); carry
forward correctly persists the unmatched side when enabled and zeroes
it when disabled; daily cap correctly voids/carries excess commission;
referral bonus only pays on qualifying packages; refund reversal
exactly cancels out the original BV/commission/wallet entries (assert
post-reversal state equals pre-sale state).

Commit: git add -A && git commit -m "Phase 5: team volume tracking, matching engine, binary + referral commission"
```

---

## PHASE 6 — Member Wallet System

```
Build the member-facing wallet (WalletService partially exists from
Phase 5 — complete it here).

1. `WalletService`: `credit()`, `debit()`, `balance()`, `history()` — all
   ledger-based (never mutate balance directly outside these methods),
   every call inside a DB transaction with `lockForUpdate` on the wallet
   row.
2. Wallet transaction types already defined in Phase 2's schema —
   ensure every credit/debit path in the app goes through
   `WalletService`, never a raw model update.
3. Member-facing wallet page (Vue + Inertia): running balance, a
   paginated transaction list (type, amount, date, reference,
   description, status) with filters by type and date range.
4. A wallet summary widget for the Member Dashboard (built in Phase 8)
   showing: available balance, this cycle's referral income, this
   cycle's binary income, total lifetime income.

Feature tests: concurrent credit/debit calls on the same wallet don't
race (dispatch parallel jobs in a test, assert final balance is
correct); balance always equals SUM(credits)-SUM(debits) after any
sequence of operations.

Commit: git add -A && git commit -m "Phase 6: wallet service, ledger, member wallet UI"
```

---

## PHASE 7 — Withdrawal System

```
Build the withdrawal request + approval state machine.

1. Member-facing withdrawal request (Vue + Inertia): amount (≥ min
   withdrawal from settings), method (bank/mobile banking), saved
   withdrawal_methods to pick from or add new. On submit: validate
   amount ≤ wallet balance, place a `wallet_transactions` debit
   (type=withdrawal, status=pending) that immediately reduces the
   available balance (hold the funds), create the `withdrawals` row
   (status=pending).
2. State machine service `WithdrawalService`: `approve()`,
   `startProcessing()`, `markPaid()`, `reject()` — each is admin-only,
   logs to ActivityLog, and only allows the valid transitions (pending
   → approved → processing → paid; pending/approved → rejected).
   `reject()` reverses the wallet hold (credit back the amount, void
   the pending wallet_transactions debit).
3. Member-facing withdrawal history page with status badges.

Feature tests: withdrawal reduces available balance immediately;
rejection restores the balance exactly; invalid transitions (e.g.
paid → pending) are blocked; withdrawal below minimum is rejected at
validation.

Commit: git add -A && git commit -m "Phase 7: withdrawal request flow and admin approval state machine"
```

---

## PHASE 8 — Member Dashboard (Vue 3 + Inertia)

```
Build the full member-facing dashboard, tying together everything from
Phases 3–7.

1. Overview page: total income, available balance, personal sales,
   left/right team sales, total team size, active team size (cards +
   a simple chart of income over the last 6 cycles).
2. Income page: tabs for referral income / binary income / rank bonus /
   other bonus, each a filterable, paginated table pulling from
   `commissions`.
3. Team page: my sponsor (name + code), my direct left/right team, a
   visual binary tree (build a simple recursive Vue component — nodes
   as cards, expand/collapse children, show active/inactive status,
   package, and cumulative team sales per node; lazy-load deeper levels
   via an API endpoint rather than shipping the whole tree at once).
4. Wallet page: from Phase 6 (link it into the dashboard nav).
5. Referral link page: shows the member's unique referral URL
   (`/register?ref=MBR-100001`), a copy-to-clipboard button, and
   pre-filled share links for WhatsApp/Facebook.
6. Profile/KYC page: submit NID/passport + photo for KYC (status
   pending/approved/rejected shown), edit basic profile fields.

Feature/browser tests: dashboard cards show correct numbers for a
seeded member with known sales/commission history (use Phase 2's
seeded tree); the binary tree component correctly reflects
`binary_nodes` for a multi-level seeded member.

Commit: git add -A && git commit -m "Phase 8: member dashboard — overview, income, team tree, wallet, referral link, KYC"
```

---

## PHASE 9 — Admin Panel Foundation (AdminLTE)

```
Build the AdminLTE admin panel shell and dashboard.

1. AdminLTE layout (Blade) with the `admin` guard, sidebar nav grouped
   by: Dashboard, Members, Binary Tree, Sales, Financial, Withdrawals,
   KYC, Reports, Settings — each item gated by the matching Spatie
   permission from Phase 1.
2. Admin dashboard: total members, active members, new members (this
   period), total sales, today's sales, total commission paid, pending
   withdrawals amount, company revenue, company expenses, estimated
   gross/net profit (revenue − sales-cost-of-goods − commission −
   expenses — make "cost of goods per package" a configurable field on
   `packages` so this isn't a guess).
3. Admin auth: separate login at `/admin/login`, separate session
   guard, 2FA optional (leave a TODO if out of scope this phase).

Acceptance: an admin user (seeded in Phase 2) can log in, see the
dashboard with real numbers computed from the seeded tree/sales data,
and permission-gated nav items correctly hide for a limited admin role.

Commit: git add -A && git commit -m "Phase 9: AdminLTE panel shell, permission-gated nav, admin dashboard"
```

---

## PHASE 10 — Admin: Member, Tree, Sales & Financial Management

```
Build the core admin CRUD/management screens.

1. Member management: list (search/filter by status, package, date),
   view (full profile incl. sponsor, placement, team stats, wallet,
   transaction history), edit, activate/suspend, change package —
   every state-changing action writes to ActivityLog.
2. Binary tree management: full tree browser (same lazy-load component
   pattern as Phase 8's member tree, but admin can view any member's
   subtree), view team volume/matching history, and a tightly
   permission-gated "manual placement adjustment" tool for edge cases —
   every manual adjustment requires a reason field and writes a
   detailed ActivityLog entry (before/after tree state).
3. Sales management: list with filters (product, member, date, status),
   totals by status (completed/pending/cancelled/refunded), drill into
   a sale to see its BV flow and any commission it generated, trigger
   refund (calls `RefundService` from Phase 4/5).
4. Financial management: income (product sales, other income) and
   expense (product cost, commission paid, delivery, marketing,
   salary, server, gateway fees, other) entry screens against the
   `income_transactions`/`expenses` tables, plus report views: daily/
   weekly/monthly/yearly, Profit & Loss, Commission report, Withdrawal
   report, Sales report — each exportable to CSV.
5. Withdrawal management: queue view by status, approve/reject/mark-
   processing/mark-paid actions wired to Phase 7's `WithdrawalService`.

Feature tests: admin without `manage-members` permission gets 403 on
member edit; refund from the sales screen correctly triggers the
Phase 5 reversal; a manual placement adjustment writes a complete
before/after ActivityLog entry.

Commit: git add -A && git commit -m "Phase 10: admin member/tree/sales/financial management screens"
```

---

## PHASE 11 — Rank & Bonus System

```
Build rank progression and the remaining bonus types.

1. `RankService::evaluate(Member $member)`: checks the member's
   personal sales, team sales, and active team count against the
   `ranks` table thresholds (ordered by sort_order), assigns the
   highest rank they qualify for, writes a `rank_achievements` row on
   promotion (only on change, not every run), and if the rank has a
   `bonus_amount`, credits it via `WalletService` as a `commissions`
   row (type=rank).
2. Schedule `RankService::evaluate()` for all active members on the
   same cadence as the commission cycle (Phase 5).
3. Leadership Bonus, Sales Bonus, Performance Bonus: implement as
   configurable rule sets in a small rules table or JSON config
   (leadership: team-size threshold; sales: personal-sales target;
   performance: admin-triggered one-off) — each pays out through the
   same `bonuses` table + `WalletService::credit()` path used
   elsewhere, so wallet history stays consistent.
4. Member-facing: show current rank + progress toward next rank on the
   dashboard overview (Phase 8); admin-facing: rank report showing
   distribution of members across ranks.

Feature tests: a member crossing a rank threshold gets promoted exactly
once (running evaluate() twice doesn't double-pay the bonus); rank
bonus appears correctly in wallet history.

Commit: git add -A && git commit -m "Phase 11: rank progression engine and remaining bonus types"
```

---

## PHASE 12 — KYC, Fraud Prevention & Audit

```
Harden the system per the fraud-prevention rules.

1. Admin KYC review screen: pending/approved/rejected queue, document
   viewer (via Spatie MediaLibrary), approve/reject with a reason.
2. Duplicate detection: enforce at the DB level (unique constraints on
   normalized mobile/NID for active members) in addition to the
   application-level check from Phase 3.
3. IP/device logging: capture on registration and every login
   (`login_history` table — add this migration now if not already
   present), flag (not block) logins from a new device/IP with a
   notification to the member.
4. Suspicious-activity flags: a scheduled job that flags (for admin
   review, does not auto-block) members with unusually rapid
   registration-to-withdrawal-request timing, or withdrawal requests
   that would exceed the member's legitimately earned (non-carried,
   non-reversed) lifetime commission.
5. Confirm every money-moving and tree-structure-changing action across
   all prior phases is captured in ActivityLog — audit and add any
   missing `activity()->log()` calls (do a grep across
   Services/Actions for anything touching wallets, commissions, or
   binary_nodes without a nearby log call).
6. Admin audit log viewer: filterable by actor, action type, date, and
   affected member.

Feature tests: duplicate NID registration is rejected even under a
race (two near-simultaneous requests); a flagged suspicious-withdrawal
case appears in the admin review queue.

Commit: git add -A && git commit -m "Phase 12: KYC review, fraud detection, complete audit logging"
```

---

## PHASE 13 — Notifications

```
Wire up the notification system (email at minimum; SMS/WhatsApp behind
a driver interface so a gateway can be swapped later without touching
call sites).

1. `NotificationChannel` interface with an `EmailChannel` (Laravel Mail)
   implementation now, and stub `SmsChannel`/`WhatsAppChannel` classes
   with a TODO for the actual gateway (Bangladeshi SMS provider) —
   route all sends through Laravel's Notification system so channels
   are swappable.
2. Notifications to implement: registration confirmation, member
   activation, sale/commission received, withdrawal status change (each
   transition), KYC status change, password reset, and an
   admin-composable "Important Announcement" broadcast to all/segment
   of members.
3. In-app notification bell (Vue + Inertia) on the member dashboard
   using Laravel's database notifications channel.

Feature tests: each event above queues the correct notification class;
the announcement broadcast reaches the selected member segment only.

Commit: git add -A && git commit -m "Phase 13: notification system — email, in-app, SMS/WhatsApp driver stubs"
```

---

## PHASE 14 — Full Test Suite & Hardening Pass

```
This phase adds no new features — it closes gaps before deployment.

1. Run full coverage report; add feature tests for any business rule
   from the Phase 0 context that doesn't yet have an explicit test
   (cross-check against the numbered rules list).
2. Load-test the matching/commission cycle job against a seeded tree of
   1,000+ members (extend the seeder) — confirm it completes within an
   acceptable window and fix any N+1 query issues (use
   `DB::enableQueryLog()` or Laravel Debugbar to check).
3. Security pass: confirm every admin route is permission-gated,
   confirm mass-assignment protection on all models, confirm rate
   limiting on login/registration/withdrawal-request endpoints, run
   `composer audit`.
4. Confirm every money calculation uses the single money type decided
   in Phase 1 consistently (grep for stray float usage).

Acceptance: `php artisan test` green across the full suite, no N+1
warnings on the dashboard/tree endpoints, `composer audit` clean.

Commit: git add -A && git commit -m "Phase 14: full test coverage, performance and security hardening"
```

---

## PHASE 15 — Deployment & Production Setup

```
Prepare for production deployment.

1. Production `.env` checklist (document in README, do not commit real
   secrets): DB, mail, bKash/SSLCommerz/Nagad live credentials, queue
   driver (Redis recommended), cache driver.
2. Queue worker + scheduler setup: supervisor config for
   `php artisan queue:work`, cron entry for `php artisan schedule:run`
   (needed for the commission cycle and rank evaluation jobs).
3. SSL, domain, and a deployment script (or GitHub Actions deploy
   workflow) — zero-downtime if feasible (`php artisan down` /
   `php artisan up` guarded deploy at minimum).
4. Database backup: confirm Spatie Backup is scheduled and tested
   (actually run a restore-from-backup test in staging).
5. Monitoring: basic uptime + error tracking hook (Sentry or
   equivalent) wired into `bootstrap/app.php` exception handling.
6. Final README pass: setup, deployment, and "how the commission cycle
   and cron jobs work" documentation for whoever maintains this after
   handover.

Acceptance: a staging deploy from a clean server following only the
README succeeds, scheduled jobs visibly run (check logs), and a test
backup/restore cycle completes successfully.

Commit: git add -A && git commit -m "Phase 15: production deployment, queue/cron setup, backups, monitoring"
```

---

### ব্যবহারের নিয়ম

1. Phase 0 প্রথমে পেস্ট করুন (বা `CLAUDE.md` হিসেবে রুটে রেখে দিন যাতে প্রতি session-এ Claude Code নিজেই পড়ে নেয়)।
2. এরপর Phase 1 থেকে ক্রমান্বয়ে — প্রতিটি Phase আলাদা কথোপকথনে/সেশনেও চালানো যাবে, কারণ প্রতিটিতে দরকারি context নিজের মধ্যেই আছে।
3. প্রতিটি Phase শেষে `git commit` — কোনো Phase-এ সমস্যা হলে সহজেই আগের commit-এ ফিরে যেতে পারবেন।
4. Phase 5 (Commission Engine) সবচেয়ে গুরুত্বপূর্ণ ও ঝুঁকিপূর্ণ অংশ — এখানে টেস্ট স্কিপ করবেন না।
