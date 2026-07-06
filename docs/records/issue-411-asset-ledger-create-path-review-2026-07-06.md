# Issue #411 Asset Ledger Transaction Creation Review

## Scope

This record reviews whether `point`, `reward`, and `deposit` can share `sr_ledger_create_transaction()` as the transaction creation primitive after the `member-assets.php` contract shape from issue #410 was clarified.

The current implementation should not migrate `point` or `reward` in the same unit. `deposit` is the reference implementation for the generic primitive, while `point` and `reward` still own expiration lot, consumption mapping, and reclaim/refund policy around their transaction insert paths.

## Comparison

| Axis | `deposit` | `point` | `reward` | Decision |
| --- | --- | --- | --- | --- |
| Generic primitive use | Uses `sr_ledger_create_transaction()` in `sr_deposit_create_transaction()` | Uses `sr_point_insert_ledger_transaction()` | Uses `sr_reward_insert_ledger_transaction()` | Keep `deposit` as reference |
| Balance table lock | Primitive locks `sr_deposit_balances` | Module helper locks `sr_point_balances` | Module helper locks `sr_reward_balances` | Common lock order exists, but module fields differ |
| Transaction fields | Basic amount, balance, type, reason, reference, actor, created time | Adds `expires_at`, `expires_remaining`, `expired_at` | Adds `expires_at`, `expires_remaining`, `expired_at` | `point`/`reward` need a wider insert contract |
| Expiration lot | None in transaction primitive | Grant/refund lots and consumption rows in `sr_point_expiration_consumptions` | Grant/refund lots and consumption rows in `sr_reward_expiration_consumptions` | Keep module-owned until a lot-aware primitive exists |
| Refund handling | Single referenced refund transaction | `refund_split_function` can split one refund into several transactions by source expiration | Single transaction today, but expiration state is still module-owned | Use `member-assets.php` capability instead of key branching |
| Reclaim policy | None | None | `transaction_type=reclaim` with target transaction reference and remaining amount checks | Keep reward-specific helper |
| Rollback boundary | Primitive joins outer PDO transaction | Module helper joins outer PDO transaction | Module helper joins outer PDO transaction | Existing invariant is compatible |
| Contract shape | `balance_table`, `transaction_table`, `transaction_function` | Same plus `refund_split_function` | Same | Issue #410 shape is sufficient for consumers, not for primitive migration |

## Decision

`deposit` remains the only asset module using `sr_ledger_create_transaction()` directly. `point` and `reward` are not migrated in this issue because their transaction creation path is also the owner of expiration lots, consumption mapping, and reward reclaim policy.

Future migration is possible only as a module-sized unit:

- Move one asset module at a time.
- Extend the primitive or add a lot-aware primitive before moving `point` or `reward`.
- Preserve dedupe, balance locking, rollback, refund/reversal reference, expiration consumption, and reclaim fixtures in the same change.
- Do not keep a long-lived half-migrated state inside one asset module.

## Verification

The current contract convergence is covered by:

- `php .tools/bin/check-member-assets-transaction-contract.php`
- `php .tools/bin/check-asset-reconciliation.php`
- `php .tools/bin/check.php`
