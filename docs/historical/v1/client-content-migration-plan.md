# Client content and member migration plan

Date: 14 October 2026
Source: `Bobagunwa_Client_Data_Import_Package.zip` supplied by the client
Status: private dry-run only; no production import or publication performed

## Package verification

All 12 files listed by the package checksum register match their SHA-256 values. The package contains:

- 86 provisional member candidates;
- 43 leadership review rows: 3 Giwa-in-Council positions, 29 chiefs entries and 11 executive entries;
- 23 sitemap/subsection entries;
- three draft editorial documents;
- an empty historical-finance template;
- one four-sheet administrative review workbook.

The private roster has 86 populated source names and no normalised exact-name duplicate groups. Email, phone, date of birth, official membership number and joined date are blank for all 86 rows. Every row is `PENDING_VERIFICATION` and every production-approval field is `NO`.

## Existing-member mapping

| Source column | Application field | Treatment |
|---|---|---|
| `source_sequence` | Retained in staged source JSON | Provenance only; never treated as a membership number. |
| `name_as_provided` | `display_name` | Required; spacing normalised only. |
| `email` | `email` | Optional and currently blank; never inferred. |
| `phone` | `phone` | Optional and currently blank; never inferred. |
| `date_of_birth` | `birth_date` | Optional and currently blank; existing-member status requires association verification rather than new-applicant auto-screening. |
| `membership_number` | `membership_number` | Optional and currently blank; source sequence is not substituted. |
| `joined_on` | `joined_on` | Optional and currently blank; never invented. |
| Review/approval/notes fields | Retained in staged source JSON | Administrative evidence; `NO` prevents production commit. |

The current parser accepted all 86 rows with this explicit mapping. The 86-row query/parameter budget test passes. A committed import would create inactive member records only and zero login accounts, but no real commit is authorised while approval remains `NO`.

## CMS mapping

- Welcome and About copy map to versioned `content_pages` drafts.
- Sitemap items map to existing public routes where a matching route exists. A sitemap label alone does not justify a new route.
- Activities and events map to their existing draft collections only when source records contain usable content.
- Leadership entries remain private review records until names, offices, 2025–2027 terms, photographs and publication consent are confirmed.
- The Awujale history remains unpublished because the supplied narrative contains an outdated living-status description and requires factual and editorial approval.
- No historical finance import can be prepared from this package because it contains zero financial rows.

## Required client decisions

1. Confirm the 86-name roster, any missing members and official membership-number convention.
2. Supply or approve the minimum identity fields required for deduplication and future activation.
3. Approve each public leadership name, title, term and photograph.
4. Approve corrected history, welcome and About copy before publication.
5. Supply verified finance records, cut-off date and reconciliation totals separately if historical finance is required.

The package remains outside the public webroot and repository. No private name list is copied into this document or any public fixture.
