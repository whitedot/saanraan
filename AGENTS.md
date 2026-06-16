# AGENTS.md

## Project Naming

This project is named `saanraan`.

Use `sr` as the project prefix for database tables and related identifiers that need a shared project namespace.

Examples:

- `sr_site_settings`
- `sr_modules`
- `sr_module_settings`
- `sr_member_accounts`

Avoid generic prefixes such as `core_` or module-only prefixes such as `member_` for database table names unless there is a specific compatibility reason.

## Development Direction

- Keep the codebase friendly to procedural PHP development.
- Prefer PHP, vanilla JavaScript, and plain CSS.
- Assume low-cost shared web hosting as a supported deployment environment.
- Treat member authentication as a module, even when it is provided as a default module.
- Prefer a readable core over a clever core.
- Make request flow visible by reading files, not hidden behind automatic registration.
- Prioritize clear module boundaries over adding core features.

## Scope Control

- Treat the user's latest scope correction as a hard stop or boundary, even when an older broad goal says to keep reviewing, fixing, or committing.
- Do not turn a review request into open-ended defect hunting. Review only the requested issue, feature, file set, or acceptance criteria unless the user explicitly expands the scope.
- When the user says the work feels excessive, unrealistic, or off-goal, stop autonomous changes immediately, revert any uncommitted scope-creep edits, and ask for or wait for a narrower target.
- Do not justify adjacent security, privacy, cleanup, refactor, or quality improvements merely because they are nearby. If they are not required for the current target, leave them as notes unless the user asks to implement them.
- Prefer proving the requested end state over finding more work. Once the stated target is satisfied and verified, report completion instead of continuing the review loop.

## Core Boundary Rules

- Keep the core as a small execution foundation, not a management system.
- Core may provide request entry, install/update flow, DB connection, settings lookup, module lookup, security helpers, translation helpers, output slots, and shared operational helpers.
- Core must not own domain concepts such as posts, pages, products, orders, points, coupons, comments, categories, menus, SEO scoring, analytics, or content workflows.
- Put domain tables, domain admin screens, domain permissions, and domain policies in the module that owns the domain.
- Do not add fields to core or member tables just because a future community, commerce, content, marketing, or analytics module may need them.
- If several modules need a capability, first define a narrow helper or contract. Promote it to core only when it is truly generic and has no domain policy.
- Admin screens should coordinate core and module operations, but domain-specific management belongs to the owning module.
- Prefer explicit module-owned extension tables connected by stable identifiers such as `account_id` over widening shared tables.

## PHP Style

- Keep request flow readable as procedural PHP.
- Prefer direct `if` / `elseif` request branches or explicit `include` files over hidden dispatch flows.
- Do not use route registration APIs such as `sr_route()` as the default routing model.
- If a module exposes routable handlers, prefer a plain array file that returns allowed method/path to handler mappings, then validate and include explicitly.
- Do not use PHP short tags or short echo tags.
- Use `<?php echo ...; ?>` instead of `<?= ... ?>`.
- Do not render a full HTML layout with heredoc strings such as `echo <<<HTML`.
- Prefer closing PHP and writing normal HTML for view output, using small `<?php echo ...; ?>` blocks only where values are needed.
- Escape output before printing user-controlled or variable values.

## UI Date and Time Display

- In public and admin UI, display authored/created timestamps in relative Korean form such as `며칠 전`, `몇시간 전`, or `방금 전` when the context benefits from quick scanning.
- Preserve the original exact timestamp in the markup and expose it through a tooltip on hover or click, such as a `title` attribute on a `<time>` element.
- Keep the exact timestamp escaped and machine-readable where practical, for example with `<time datetime="...">상대 시간</time>`.

## Documentation

- When changing behavior, features, database schema, admin screens, module contracts, request flow, security/privacy policy, deployment assumptions, or operational procedures, update the relevant GitHub Wiki pages in the same work item.
- At minimum, check whether the change affects the DB specification, administrator screen field guide, developer guides, request flow, module development guide, security/privacy guide, testing guide, deployment guide, or troubleshooting guide.
- If a code change intentionally does not require a Wiki update, mention that decision in the final response or commit body when useful.
- Keep repository docs and Wiki docs aligned with the current implementation rather than the initial project plan.

## Verification and Smoke Tests

- After code changes, run the relevant automated checks before reporting completion. At minimum, run `php .tools/bin/check.php` when PHP is available and the change is not documentation-only.
- When reviewing local commits or a working tree that includes code changes, include automated check and smoke-test status in the review. If a check or smoke test was skipped, state the concrete reason.
- Treat HTTP smoke tests as the default follow-up when a local or staging base URL is available, or when a local PHP built-in server can be started safely without secrets or production data. Use `php -S 127.0.0.1:<port> -t .tools/public .tools/bin/dev-router.php` with an available port, then run `SR_SMOKE_BASE_URL=http://127.0.0.1:<port> php .tools/bin/smoke-http.php`.
- If the target environment is expected to have the community module installed, run the HTTP smoke test with `SR_SMOKE_EXPECT_COMMUNITY=1`.
- Run authenticated smoke tests such as `php .tools/bin/smoke-community-auth.php` only against a local or staging database and only when explicit smoke-test credentials are available. These tests create and modify data, so do not run them against production.
- For local or staging authenticated smoke tests, the temporary test administrator credentials are `admin` / `12341234`. These credentials are for testing only and must be removed from this file and any related test notes before milestone release.
- When reward reclaim or other admin-only workflows need data to verify, create dummy local or staging data as needed. Do not use production data for destructive or mutating smoke tests.
- Treat a smoke-test failure as a real finding unless the failure is clearly caused by missing local environment, unavailable credentials, or an already documented pre-existing issue. Record that distinction in the final response.

## GitHub Operations

- Do not commit unless the user explicitly asks to commit.
- Do not push unless the user explicitly asks to push.
- Do not open, reopen, close, or otherwise change GitHub issue state unless the user explicitly asks for that issue operation.
- Do not add GitHub issue comments unless the user explicitly asks to comment or to update the issue.
- When creating GitHub issues or issue comments, format the body with clear Markdown line breaks and blank lines between paragraphs, lists, and sections. Do not submit a dense single-line body when multiple ideas are included.
- For GitHub issue comments especially, preserve newlines by using a body file or another multiline-safe method instead of inline shell strings whenever the comment has more than one short sentence.
- When the user asks to implement or modify code, treat that as a request for working tree changes and verification only. Ask or wait for a separate explicit request before committing, pushing, or changing issue state.

## Admin Form Validation

- Treat server-side validation as the source of truth for required admin fields. Do not rely on HTML `required`, disabled buttons, or JavaScript-only checks as the only protection.
- When an admin field is required for save/update, keep all three layers aligned where applicable: visible `(필수)`, browser/front-end validation, and server-side POST validation.
- Keep admin form labels to field names. Put explanatory text such as URL behavior, accepted formats, units, upload limits, or automatic behavior below the control as `.admin-form-help` instead of appending parenthetical explanations to the label.
- For admin key text inputs such as `*_key`, `module_key`, and `menu_key`, enforce lowercase letters, digits, and `_` with `data-admin-key-input` plus matching browser attributes, and normalize again in the save action before domain validation. Treat public slugs that allow hyphens as a separate field type.
- If a visible control only derives hidden POST fields, validate the actual hidden POST result on the server as well. For example, a picker that turns `1차/2차/권한` choices into `permission_keys[]` must reject an empty or invalid `permission_keys[]` payload server-side.
- Conditional required fields should still display only the short `(필수)` label. Toggle that label and any browser validation as the condition changes, and enforce the same condition on the server. This includes paired fields such as reference type/reference ID, target/match type/subject ID, policy/member-group choices, and terminal status/admin note.
- Admin POST save actions should use Post/Redirect/Get for both success and validation failure paths whenever the form does not need to preserve unsaved field values inline. Use flash result helpers for messages so browser refresh does not resubmit the form.
- Do not mark search filters, lookup-only controls, or helper selectors as required unless their value is directly required by the save action.

## Commit Messages

- Write commit messages in Korean.
- Use the format `type: message`.
- Use common Conventional Commits-style types only:
  - `feat`: user-facing feature or capability addition
  - `fix`: bug fix or behavioral correction
  - `docs`: documentation-only change
  - `chore`: repository maintenance, tooling, or housekeeping
  - `refactor`: internal restructuring without behavior change
  - `test`: test-only change
  - `style`: formatting-only change
  - `perf`: performance improvement
  - `build`: build or dependency change
  - `ci`: CI configuration change
  - `revert`: revert a previous commit
- Do not use project-area prefixes such as `core`, `member`, `admin`, or `install` as the commit type.
- Put the affected area in the Korean message or body when useful.
- When a commit handles a GitHub issue, include the issue number such as `#26` in the subject.
- Keep the subject concise and describe the actual change.
- Add a Korean body for non-trivial changes, especially when multiple files or behaviors are affected.

Examples:

- `docs: 루트 진입점 배포 기준 정리`
- `feat: 회원 로그인 실패 기록 정책 보완`
- `fix: 설치 상태 확인 조건 수정`
- `chore: 로컬 개발 도구 설정 정리`

## Core Decisions

- Treat `docs/core-decisions.md` as the highest-level decision log when implementation plans appear ambiguous.
- Store token hashes, not token originals.
- Keep SEO value decisions in modules; core only provides output slots and helpers.
- Keep GDPR support split between minimal member/core foundations and optional privacy/admin workflows.
