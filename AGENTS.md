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

## Documentation

- When changing behavior, features, database schema, admin screens, module contracts, request flow, security/privacy policy, deployment assumptions, or operational procedures, update the relevant GitHub Wiki pages in the same work item.
- At minimum, check whether the change affects the DB specification, administrator screen field guide, developer guides, request flow, module development guide, security/privacy guide, testing guide, deployment guide, or troubleshooting guide.
- If a code change intentionally does not require a Wiki update, mention that decision in the final response or commit body when useful.
- Keep repository docs and Wiki docs aligned with the current implementation rather than the initial project plan.

## Admin Form Validation

- Treat server-side validation as the source of truth for required admin fields. Do not rely on HTML `required`, disabled buttons, or JavaScript-only checks as the only protection.
- When an admin field is required for save/update, keep all three layers aligned where applicable: visible `(필수)`, browser/front-end validation, and server-side POST validation.
- If a visible control only derives hidden POST fields, validate the actual hidden POST result on the server as well. For example, a picker that turns `1차/2차/권한` choices into `permission_keys[]` must reject an empty or invalid `permission_keys[]` payload server-side.
- Conditional required fields should still display only the short `(필수)` label. Toggle that label and any browser validation as the condition changes, and enforce the same condition on the server.
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
