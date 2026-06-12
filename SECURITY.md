# Security Policy

saanraan is a pre-1.0 PHP project that is still being hardened. Security reports are welcome, especially for authentication, authorization, CSRF, HTML sanitization, uploads, downloads, privacy cleanup, and asset ledger consistency.

## Reporting

Please report suspected security issues privately by email:

```text
kimminsup@gmail.com
```

Include the affected version or commit, reproduction steps, expected impact, and whether the issue was tested on a local/staging environment. Do not include production personal data, secrets, or third-party credentials in the report.

## Scope

In scope:

- Authentication, sessions, password reset, email verification
- CSRF, admin permission checks, member-only mode
- Rich text sanitization, CKEditor HTML, embed markers
- File upload, download, storage proxy, direct file exposure
- Point, reward, deposit, coupon, exchange, paid access, refund consistency
- Privacy export, cleanup, withdrawal, retention behavior
- Install, update, module zip, deployment protection

Out of scope:

- Social engineering
- Denial-of-service reports that rely only on unrealistic traffic volume
- Reports requiring access to production data or credentials not owned by the reporter
- Issues in modified third-party deployments that cannot be reproduced against this repository

## Handling

Security fixes should include the smallest practical code change, regression coverage or smoke evidence, and documentation updates when behavior or deployment assumptions change.

Detailed triage, severity, evidence, and release expectations are documented in [보안 제보와 처리 기준](docs/security-response-policy.md).

