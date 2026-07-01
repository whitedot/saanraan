# Issue 397 Schema Fallback Snapshot

Related issue: #397

Generated with:

```sh
php .tools/bin/snapshot-schema-fallbacks.php
```

The default snapshot scans runtime source under `core/` and `modules/`.
Use `--include-tools` when legacy fixture/assertion references under `.tools/bin/` need to be reviewed separately.

## Initial Summary

This initial snapshot was captured before removing the first settlement/refund schema aliases for #397.

- `schema_unavailable` occurrences: 1
- `legacy_unknown` SQL alias occurrences: 7
- other `legacy_unknown` occurrences: 4
- named schema guard calls: 120
- unique named schema guard helpers: 25
- generic optional guard calls: 58

## Generic Optional Guard Status

- `literal_table_in_install`: 52
- `needs_manual_resolution`: 6

## Manual Resolution Queue

These generic optional guards use dynamic table or column arguments. They must be resolved with constant folding or caller tracing before they can be removed automatically. If they cannot be resolved, keep them in the manual review set.

- `modules/community/helpers/board-cleanup.php:27` `sr_community_optional_table_exists` table=`$tableName` column=`-`
- `modules/content/helpers.php:1165` `sr_content_optional_table_exists` table=`$tableName` column=`-`
- `modules/content/helpers/references.php:280` `sr_content_optional_column_exists` table=`'sr_content_items'` column=`$column`
- `modules/content/helpers/references.php:331` `sr_content_optional_column_exists` table=`'sr_content_group_settings'` column=`$column`
- `modules/content/helpers/references.php:336` `sr_content_optional_column_exists` table=`'sr_content_groups'` column=`$column`
- `modules/content/helpers/references.php:417` `sr_content_optional_column_exists` table=`'sr_content_items'` column=`$column`

## Legacy Unknown SQL Aliases

- `modules/community/helpers/attachments.php:922` alias=`settlement_kind`
- `modules/community/privacy-export.php:111` alias=`settlement_kind`
- `modules/community/privacy-export.php:435` alias=`settlement_kind`
- `modules/content/helpers/files.php:1057` alias=`settlement_kind`
- `modules/content/privacy-export.php:114` alias=`settlement_kind`
- `modules/content/privacy-export.php:208` alias=`settlement_kind`
- `modules/content/privacy-export.php:227` alias=`settlement_kind`

## Schema Unavailable

- `modules/content/helpers/files.php:971`

## Current Snapshot After First Removal Pass

Generated after removing the runtime `schema_unavailable` fallback and the 7 runtime `legacy_unknown` SQL aliases:

```sh
php .tools/bin/snapshot-schema-fallbacks.php
```

- `schema_unavailable` occurrences: 0
- `legacy_unknown` SQL alias occurrences: 0
- other `legacy_unknown` occurrences: 4
- named schema guard calls: 116
- unique named schema guard helpers: 25
- generic optional guard calls: 58

The generic optional guard manual resolution queue remains unchanged at 6 entries.

Tooling fixture/assertion scan:

```sh
php .tools/bin/snapshot-schema-fallbacks.php --include-tools
```

- `schema_unavailable` occurrences: 0
- `legacy_unknown` SQL alias occurrences: 0

## Current Snapshot After Comment Guard Removal Pass

Generated after removing current-schema comment fallback guards for content, quiz, and survey comments:

```sh
php .tools/bin/snapshot-schema-fallbacks.php
```

- `schema_unavailable` occurrences: 0
- `legacy_unknown` SQL alias occurrences: 0
- other `legacy_unknown` occurrences: 4
- named schema guard calls: 92
- unique named schema guard helpers: 20
- generic optional guard calls: 58

The generic optional guard manual resolution queue remains unchanged at 6 entries.

Tooling fixture/assertion scan:

```sh
php .tools/bin/snapshot-schema-fallbacks.php --include-tools
```

- `schema_unavailable` occurrences: 0
- `legacy_unknown` SQL alias occurrences: 0
- other `legacy_unknown` occurrences: 18
- named schema guard calls: 94
- unique named schema guard helpers: 20
- generic optional guard calls: 58

## Current Snapshot After Community Post/Comment Guard Removal Pass

Generated after removing current-schema community post/comment guards for secret, thread, guest author, author snapshot, hidden metadata, extra values, reaction preset, summary feed candidate, and category columns:

```sh
php .tools/bin/snapshot-schema-fallbacks.php
```

- `schema_unavailable` occurrences: 0
- `legacy_unknown` SQL alias occurrences: 0
- other `legacy_unknown` occurrences: 4
- named schema guard calls: 23
- unique named schema guard helpers: 10
- generic optional guard calls: 58

Remaining named schema guards are outside the community post/comment surface:

- community attachment download refund columns
- content file download snapshot/refund columns
- site menu icon name
- banner table columns
- content asset log settlement metadata
- member profile adult flag

The generic optional guard manual resolution queue remains unchanged at 6 entries.

Tooling fixture/assertion scan:

```sh
php .tools/bin/snapshot-schema-fallbacks.php --include-tools
```

- `schema_unavailable` occurrences: 0
- `legacy_unknown` SQL alias occurrences: 0
- other `legacy_unknown` occurrences: 18
- named schema guard calls: 23
- unique named schema guard helpers: 10
- generic optional guard calls: 58

## Current Snapshot After Download/Settlement Log Guard Removal Pass

Generated after removing current-schema fallback guards for community attachment download refund columns, content file download snapshot/refund columns, and content asset log settlement metadata helpers:

```sh
php .tools/bin/snapshot-schema-fallbacks.php
```

- `schema_unavailable` occurrences: 0
- `legacy_unknown` SQL alias occurrences: 0
- other `legacy_unknown` occurrences: 4
- named schema guard calls: 7
- unique named schema guard helpers: 4
- generic optional guard calls: 58

Remaining named schema guards are limited to non-download admin/profile surfaces:

- site menu item icon name
- banner table columns
- member profile adult flag

The generic optional guard manual resolution queue remains unchanged at 6 entries.

## Current Snapshot After Remaining Named Guard Removal Pass

Generated after removing the remaining current-schema named guards for site menu item icon names, banner content fields, member profile adult flag, and an unused community asset log metadata helper:

```sh
php .tools/bin/snapshot-schema-fallbacks.php
```

- `schema_unavailable` occurrences: 0
- `legacy_unknown` SQL alias occurrences: 0
- other `legacy_unknown` occurrences: 4
- named schema guard calls: 0
- unique named schema guard helpers: 0
- generic optional guard calls: 58

The generic optional guard manual resolution queue remains unchanged at 6 entries.

## Current Snapshot After Community Generic Guard Removal Pass

Generated after removing current-schema generic optional table guards for community-owned board cleanup, extra field, and level recalculation job tables while keeping external module reference counts on the optional boundary:

```sh
php .tools/bin/snapshot-schema-fallbacks.php
```

- `schema_unavailable` occurrences: 0
- `legacy_unknown` SQL alias occurrences: 0
- other `legacy_unknown` occurrences: 4
- named schema guard calls: 0
- unique named schema guard helpers: 0
- generic optional guard calls: 34

The generic optional guard manual resolution queue remains unchanged at 6 entries.
