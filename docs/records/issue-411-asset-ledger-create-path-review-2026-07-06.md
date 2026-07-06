# Issue #411 Asset Ledger Transaction Creation Review

## Scope

This record reviews whether `point`, `reward`, and `deposit` can share `sr_ledger_create_transaction()` as the transaction creation primitive after the `member-assets.php` contract shape from issue #410 was clarified.

The implementation migrates `reward` and `point` to the generic primitive after `sr_ledger_create_transaction()` gained an allowlisted extra transaction column contract. `deposit` remains the reference implementation for the basic primitive. `point` keeps its wider refund split path, but the final balance update and transaction insert now share the primitive.

## Comparison

| Axis | `deposit` | `point` | `reward` | Decision |
| --- | --- | --- | --- | --- |
| Generic primitive use | Uses `sr_ledger_create_transaction()` in `sr_deposit_create_transaction()` | `sr_point_insert_ledger_transaction()` delegates to `sr_ledger_create_transaction()` | `sr_reward_insert_ledger_transaction()` delegates to `sr_ledger_create_transaction()` | All asset insert primitives share the helper |
| Balance table lock | Primitive locks `sr_deposit_balances` | Primitive locks `sr_point_balances` | Primitive locks `sr_reward_balances` | Common lock order is retained |
| Transaction fields | Basic amount, balance, type, reason, reference, actor, created time | Adds `expires_at`, `expires_remaining`, `expired_at` via allowlisted extra columns | Adds `expires_at`, `expires_remaining`, `expired_at` via allowlisted extra columns | Wider insert contract is primitive-supported |
| Expiration lot | None in transaction primitive | Grant/refund lots and consumption rows in `sr_point_expiration_consumptions` stay module-owned around the primitive insert | Grant/reclaim lots and consumption rows in `sr_reward_expiration_consumptions` stay module-owned around the primitive insert | Keep policy logic module-owned |
| Refund handling | Single referenced refund transaction | `refund_split_function` can split one refund into several transactions by source expiration before each primitive insert | Single transaction today, but expiration state is still module-owned | Use `member-assets.php` capability instead of key branching |
| Reclaim policy | None | None | `transaction_type=reclaim` with target transaction reference and remaining amount checks | Keep reward-specific helper |
| Rollback boundary | Primitive joins outer PDO transaction | Module helper joins outer PDO transaction | Module helper joins outer PDO transaction | Existing invariant is compatible |
| Contract shape | `balance_table`, `transaction_table`, `transaction_function` | Same plus `refund_split_function` | Same | Issue #410 shape is sufficient for consumers, not for primitive migration |

## Decision

`deposit`, `reward`, and `point` now use `sr_ledger_create_transaction()` for the atomic balance update and transaction insert. The primitive still does not own expiration, consumption mapping, refund, or reclaim policy; it only accepts allowlisted module-owned transaction columns such as `expires_at`, `expires_remaining`, and `expired_at`.

The module-owned policy boundary remains:

- Preserve dedupe, balance locking, rollback, refund/reversal reference, expiration consumption, and reclaim fixtures in the same change.
- Keep refund split and expiration consumption in the asset module, not in `asset_ledger`.
- Do not keep a long-lived half-migrated state inside one asset module.

## Verification

The current contract convergence is covered by:

- `php .tools/bin/check-member-assets-transaction-contract.php`
- `php .tools/bin/check-asset-reconciliation.php`
- `php .tools/bin/check.php`
