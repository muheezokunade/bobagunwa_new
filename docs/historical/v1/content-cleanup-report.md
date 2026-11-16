# Bobagunwa content cleanup report

Date: 12 October 2026
Scope: all 54 inventoried application routes, shared interface components, user-facing API feedback, responsive presentation and page metadata.

## Outcome

The audit removed repetitive subtitles, generic platform claims, developer-facing implementation language and unnecessary status labels without redesigning the website. The heritage typography, colour palette, logo treatment, public storytelling, navigation and functional safeguards remain intact.

The cleanup is implemented in shared components wherever possible, so the same editorial standard now applies consistently to public collections, authentication screens, the member portal and administrative modules.

## Route coverage

The read-only `scripts/audit-visible-copy.mjs` check signs in with local synthetic member and administrator fixtures, resolves available detail records, loads every inventoried route and rejects the banned copy patterns identified during this audit.

| Area | Route | State inspected | Result |
|---|---|---|---|
| Public | `/` | Published home page | Passed |
| Public | `/about` | Association story | Passed |
| Public | `/members` | Published member listing | Passed |
| Public | `/activities` | Published/empty collection | Passed |
| Public | `/events` | Published collection | Passed |
| Public | `/events/[slug]` | Published event detail | Passed |
| Public | `/news` | Published collection | Passed |
| Public | `/news/[slug]` | Published article detail | Passed |
| Public | `/contact` | Enquiry form and contact details | Passed |
| Public | `/membership/apply` | Public application form | Passed |
| Public | `/membership/status` | Private reference lookup | Passed |
| Public | `/privacy` | Privacy notice | Passed |
| Public | `/terms` | Terms | Passed |
| Authentication | `/portal/login` | Signed-out form | Passed |
| Authentication | `/portal/activate` | Missing-token guidance | Passed |
| Authentication | `/portal/recover` | Recovery request | Passed |
| Authentication | `/portal/reset-password` | Missing-token guidance | Passed |
| Authentication | `/portal/logout` | Redirect state | Passed |
| Member | `/portal` | Authenticated redirect | Passed |
| Member | `/portal/dashboard` | Member summary | Passed |
| Member | `/portal/profile` | Record and edit form | Passed |
| Member | `/portal/finances` | Obligations and payments | Passed |
| Member | `/portal/finances/statement` | Statement | Passed |
| Member | `/portal/announcements` | Member notices | Passed |
| Member | `/portal/events` | Eligible events | Passed |
| Member | `/portal/events/[eventId]` | Event detail and RSVP | Passed |
| Member | `/portal/settings` | Preferences and sessions | Passed |
| Administration | `/admin` | Overview cards | Passed |
| Administration | `/admin/members` | Search, filters and export | Passed |
| Administration | `/admin/members/import` | CSV import workflow | Passed |
| Administration | `/admin/members/[memberId]` | Member record and controls | Passed |
| Administration | `/admin/applications` | Application queue | Passed |
| Administration | `/admin/applications/[applicationId]` | Review and decision state | Passed |
| Administration | `/admin/finance/categories` | Categories and editor | Passed |
| Administration | `/admin/finance/assessments` | Assessment list | Passed |
| Administration | `/admin/finance/assessments/new` | Assessment form | Passed |
| Administration | `/admin/finance/assessments/[assessmentId]` | Assessment and obligations | Passed |
| Administration | `/admin/finance/payments` | Payment list | Passed |
| Administration | `/admin/finance/payments/new` | Payment form | Passed |
| Administration | `/admin/finance/payments/[paymentId]` | Verification/reversal state | Passed |
| Administration | `/admin/reports` | Financial report and filters | Passed |
| Administration | `/admin/announcements` | Announcement list/editor | Passed |
| Administration | `/admin/announcements/[announcementId]` | Current record or not-found state | Passed |
| Administration | `/admin/events` | Event list/editor | Passed |
| Administration | `/admin/events/[eventId]` | Event editor | Passed |
| Administration | `/admin/content` | Website content and editors | Passed |
| Administration | `/admin/content/[contentKey]` | Current record or not-found state | Passed |
| Administration | `/admin/enquiries` | Enquiry queue | Passed |
| Administration | `/admin/enquiries/[enquiryId]` | Assignment and resolution | Passed |
| Administration | `/admin/access` | Users and recovery requests | Passed |
| Administration | `/admin/access/[userId]` | User roles | Passed |
| Administration | `/admin/audit` | Filters, events and export | Passed |
| Administration | `/admin/backups` | Backup register | Passed |
| Administration | `/admin/settings` | Association settings | Passed |

Total: 54/54 routes accounted for — 13 public, 5 authentication, 9 member portal and 27 administration.

## Copy patterns removed or rewritten

- Removed security-themed promotional filler from the login page while retaining the actionable sign-in instruction.
- Removed labels such as “Live database view”, “Persisted record”, “Controlled workflow”, “Versioned pages”, “Immutable payment record” and references to atomic implementation details.
- Removed repetitive “Member portal” and “Administrative dashboard” eyebrow labels from shared page headers.
- Removed descriptive paragraphs from administrative list pages when the title, controls and table already identify the task.
- Replaced verbose action labels with direct wording such as “Save changes”, “Export CSV”, “Download template”, “Import records”, “Apply filters” and “Record adjustment”.
- Hid internal record identifiers, actor IDs, normalized values, source hashes and optimistic version numbers from general record displays.
- Replaced public collection provenance commentary with the collection title and a useful empty state.
- Simplified recovery, application tracking, publishing, finance and enquiry feedback while retaining the information required to complete each task safely.
- Updated server-generated fallback errors that exposed implementation wording such as “atomically”.

## Shared components changed

- `AdminShell`, `PortalShell`, `AdminModulePage`, `AdminRecordPage`, `AdminRecord` and `AdminWorkflowPage`
- `PublicCollectionPage`, `LoginForm` and `TurnstileWidget`
- `PublishingEditor`, `MemberImportPanel`, `MemberProfileCorrectionForm`, `MemberAdminActions` and `RoleAssignmentPanel`
- `AssessmentForm`, `FinanceCategoryForm`, `PaymentRecordForm`, `PaymentOperations`, `ObligationAdjustments`, `FinancialReportPanel` and `HistoricalFinanceImportPanel`
- `EnquiryWorkflow` and `SystemSettingsManager`

Small spacing and action-alignment rules were added to `app/globals.css` so removed headings do not leave empty visual gaps.

## Essential text retained

- Membership eligibility, the 1 January 1980–31 December 1982 screening context and the statement that applying does not guarantee membership.
- Password length, activation, one-time recovery-link and account verification instructions.
- Duplicate warnings, import validation, incomplete-record handling and the rule that row numbers do not become official membership numbers.
- Payment totals, outstanding balances, allocation details, reversal reasons and financial confirmation prompts.
- Permission denials, final application status, delivery records and audit references needed for support.
- Privacy, terms, public-directory consent, protected-field and contact information.
- Form labels, accessible names, screen-reader headings, validation messages and useful empty states.

## Visual evidence

Representative desktop before-and-after evidence:

| Before | After |
|---|---|
| ![Login before cleanup](evidence/content-cleanup/before-login.png) | ![Login after cleanup](evidence/content-cleanup/after-login.png) |

Responsive evidence:

- [Login page at 390px](evidence/content-cleanup/after-login-mobile.png)
- [Members page at 390px](evidence/content-cleanup/after-members-mobile.png)
- [Members page after cleanup](evidence/content-cleanup/after-members.png)

At 390px, the representative login and public member pages reported no horizontal overflow, one primary heading and no unnamed interactive controls or links.

## Verification results

| Check | Result |
|---|---|
| TypeScript (`tsconfig.check.json`) | Passed |
| ESLint | Passed with 0 errors; 13 existing `no-img-element` performance warnings |
| Unit tests | 28/28 passed |
| Schema validation | Passed: 48 tables, 19 permissions, 5 roles |
| Database invariant tests | Passed |
| Static route inventory | Passed: 54/54 pages present |
| Public live routes/navigation | Passed: 14 pages reached, 13 navigation targets, protected public workflows verified |
| Member authentication/live routes | Passed: anonymous denial and all 9 member routes plus profile, RSVP, notices, preferences and sessions |
| Visible-copy route audit | Passed: all 54 routes with public/member/admin roles |
| Production build | Passed with Vinext |
| Responsive browser check | Passed at 1280px and 390px for representative public/authentication pages |

## Content requiring client approval

- Confirm the wording and historical accuracy of public association story, values, motto and impact statements.
- Supply and approve named leadership profiles before replacing office placeholders.
- Confirm the published address, telephone number and secretariat email.
- Review the privacy notice and terms with the association’s legal adviser before production approval.
- Remove synthetic members, articles, events, enquiries and financial fixtures from any production dataset.

## Remaining issues

- The 13 Next.js image-performance warnings were resolved during release hardening by adding intrinsic/responsive framework image handling without changing the established visual design.
- The local test database does not always contain a current record for every CMS detail type. The audit resolves live records when present and verifies the not-found state otherwise; approved client content is still needed for final editorial acceptance.
- Final production sign-off still requires the association to approve the public historical claims, leadership information and legal wording listed above.
