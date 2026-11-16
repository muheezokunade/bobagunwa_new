# Design system

## Direction

Preserve the public site's distinguished editorial character. Portal/admin interfaces should share the crest, colour, type hierarchy, and tone while prioritising clarity over ornament.

## Brand tokens currently present

| Token | Value | Intended use |
| --- | --- | --- |
| Crest blue / staff | `#D7EDFB` | Cool supporting accent |
| Horse / ink | `#281F20` | Primary dark text/background |
| Crown yellow / gold | `#D3DA3B` | Restrained highlight |
| Crown / wine | `#972C2D` | Primary brand action/accent |
| Shield bottom | `#C6261F` | Secondary red accent |

The supplied crest remains the source artwork. Do not redraw it, remove its wording, or invent alternate marks without approval.

## Typography

- Public editorial headings: restrained serif stack, currently Georgia fallback.
- Paragraphs: readable serif on public editorial sections; practical sans-serif for forms, tables, and admin UI.
- Minimum practical interface copy target: 14px; captions should normally be at least 12px.
- Preserve Yoruba Unicode marks; never substitute unaccented copy merely to avoid font issues.

## Layout and components

- Use consistent page gutters and a compact spacing scale.
- Prefer editorial sections and rule lines on the public site, not repeated generic card grids.
- Portal: personal summaries, transaction lists, clear status badges, and one dominant action per task.
- Admin: compact navigation, filters, tables/stacked mobile rows, explicit validation, confirmation dialogs, and persistent task context.
- All controls require visible focus, disabled, loading, success, and error states.
- Motion must be subtle and disabled/reduced for `prefers-reduced-motion`.

## Accessibility backlog

Increase 8–11px interface labels, add a global `:focus-visible` system, verify colour contrast, size touch targets to at least 44×44 CSS pixels where practical, and test at 200% zoom plus common mobile widths.

## Content design

Use specific approved facts. Avoid decorative metrics, fabricated quotes/testimonials, generic promotional language, and repeated mission statements. Unknown facts belong in the verification checklist, not in public copy.
