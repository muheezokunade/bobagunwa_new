# Permissions matrix

`Own` means object ownership is checked server-side. `Scoped` means only explicitly approved summary fields/audiences are available.

| Permission | Visitor | Prospective | Member | Secretary | Treasurer | Executive | System admin |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Public content read | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| Application submit/read own | Public submit; reference + secret status only | Own token | Own token | With `members.approve` | No | With `members.approve` | As granted |
| `members.read` | No | No | Own | Yes | Finance lookup only | Scoped | As granted |
| `members.manage` | No | No | Limited own fields | Yes | No | No | As granted |
| `members.approve` | No | No | No | As granted | No | As granted | As granted |
| `finance.read` | No | No | Own | No | Yes | Summary | As granted |
| `finance.assessments.manage` | No | No | No | No | Yes | As granted | As granted |
| `finance.payments.record` | No | No | No | No | Yes | No | As granted |
| `finance.payments.post` | No | No | No | No | As granted | As granted | As granted |
| `finance.payments.reverse` | No | No | No | No | As granted | As granted | As granted |
| `finance.reports.export` | No | No | Own statement | No | Yes | Approved reports | As granted |
| `content.manage` | No | No | No | As granted | No | As granted | As granted |
| `events.manage` | No | No | No | As granted | No | As granted | As granted |
| `roles.manage` | No | No | No | No | No | No | Yes |
| `audit.read` | No | No | No | Limited | Finance events | Scoped | Yes |

Roles are assignment templates, not security checks by themselves. Endpoints check permission plus record ownership/scope. No role may grant itself permissions, and “system administrator” does not automatically imply treasury authority.

Review, request-information, approval and rejection mutations all require `members.approve`; only approval/rejection satisfy the explicit mandatory permission rule. Exact named approval, posting and reversal assignments remain association decisions.
