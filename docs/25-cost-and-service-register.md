# Cost and service register

**New recurring hosting/software cost introduced by this platform: ₦0.**

| Service | Status | Cost | Notes |
|---|---|---|---|
| Namecheap Stellar shared hosting | Existing (association pays) | Existing | Hosts application, database, cron, mailbox |
| `bobagunwa.com` domain | Existing | Existing renewal | DNS unchanged |
| MariaDB 11.4 | Included in hosting | ₦0 | |
| SSL (AutoSSL / Let's Encrypt via cPanel) | Included | ₦0 | Do not purchase |
| Email (hosting mailbox SMTP) | Included, optional | ₦0 | Off by default |
| Laravel, PHP packages | Open source (MIT etc.) | ₦0 | Locked in `composer.lock`; `composer audit` clean |
| Noto Serif font | Open Font Licence, self-hosted | ₦0 | No Google Fonts calls |
| Vite, axe-core, Playwright | Build/test tools only | ₦0 | Not deployed |
| Cloudflare, Turnstile, D1, Workers | **Not used** | — | |
| Paid email/SMS/auth/CMS/payment gateway | **Not used** | — | |
| Off-site backup storage | Association to choose | Ideally an existing free account (e.g. an association Google Drive) | Decision D-014 |

Not free: developer time, ongoing security maintenance (monthly dependency updates), officer time for verification and reconciliation, and the existing hosting/domain renewals.
