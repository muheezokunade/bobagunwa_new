# Documentation index

**Start here:** [28 · Release-readiness report](28-release-readiness-report.md) (authoritative status) · [29 · Approval packet for Mr Ibrahim](29-approval-packet.md) · [`../CLAUDE.md`](../CLAUDE.md) (contributor rules)

| # | Document | For |
|---|---|---|
| 01 | [Product requirements](01-product-requirements.md) | everyone |
| 02 | [Source reference inventory](02-source-reference-inventory.md) | maintainers |
| 03 | [Requirements traceability matrix](03-requirements-traceability.md) | reviewers |
| 04 | [Database schema](04-database-schema.md) — *generated* | maintainers |
| 05 | [Database integrity safeguards](05-database-integrity-safeguards.md) | maintainers, auditors |
| 06 | [Financial rules and accounting definitions](06-financial-rules.md) | treasury, auditors |
| 07 | [Design system](07-design-system.md) | designers, developers |
| 08 | [Architecture](08-architecture.md) | maintainers |
| 09 | [Deployment runbook](09-deployment-runbook.md) | technical maintainer |
| 10 | [Backup and recovery](10-backup-and-recovery.md) | maintainer, custodians |
| 11 | [Membership procedures](11-membership-procedures.md) | membership officers |
| 12 | [Imports and reconciliation](12-imports-and-reconciliation.md) | secretariat, treasury |
| 13 | [Authentication and security](13-authentication-and-security.md) | maintainers |
| 14 | [Privacy requirements](14-privacy.md) | executive, legal adviser |
| 15 | [Roles and permissions](15-roles-and-permissions.md) | system administrator, executive |
| 16 | [Namecheap hosting compatibility](16-namecheap-hosting-compatibility.md) | technical maintainer |
| 17 | [Route inventory](17-route-inventory.md) — *generated* | maintainers |
| 18 | [Testing plan](18-testing-plan.md) | maintainers |
| 19 | [Executed test evidence](19-test-evidence.md) | reviewers |
| 20 | [Administrator guide](20-administrator-guide.md) | system administrator, membership/secretariat officers |
| 21 | [Treasurer guide](21-treasurer-guide.md) | treasurer, payment verifier, executive |
| 22 | [Content publishing guide](22-content-publishing-guide.md) | secretariat |
| 23 | [Client decision register](23-client-decisions.md) | executive |
| 24 | [Known issues and limitations](24-known-issues.md) | everyone |
| 25 | [Cost and service register](25-cost-and-service-register.md) | executive |
| 26 | [Handover plan](26-handover-plan.md) | executive, maintainer |
| 27 | [Production release checklist](27-production-release-checklist.md) | maintainer, executive |
| 28 | [Release-readiness report](28-release-readiness-report.md) | everyone |
| 29 | [Approval packet](29-approval-packet.md) | Mr Ibrahim |
| 30 | [Remaining work — prioritised](30-remaining-work.md) | executive, maintainer |
| 31 | [Integrity alerting and escalation](31-integrity-alerting.md) | treasury, administrators |
| 32 | [Two-factor authentication](32-two-factor-authentication.md) | administrators |
| 33 | [One-time link exposure assessment](33-one-time-link-assessment.md) | maintainers, reviewers |
| 34 | [Administrative endpoint coverage matrix](34-admin-endpoint-coverage.md) — *generated* | reviewers |
| 35 | [Route catalogue (purpose, roles, validation, states, tests)](35-route-catalogue.md) — *generated* | maintainers, reviewers |
| 36 | [Protected staging installation plan](36-staging-installation-plan.md) | technical maintainer, executive |
| — | [Evidence](evidence/) | reviewers |
| — | [Historical V1 documentation](historical/v1/README.md) — *reference only, not evidence* | maintainers |

Regenerate 04, 17, 34 and 35 after schema, route or test changes: `php artisan boba:docs:reference` (the route meanings live in `app/Support/RouteCatalogue.php`; `RouteCatalogueSyncTest` fails if they drift).
