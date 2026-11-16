# Design system

The public site preserves the established Bobagunwa identity from the V1 Netlify site (palette, crest-led hero, large serif headings, ruled editorial sections, numbered values, diagonal pattern panels) and corrects its accessibility gaps (8–11 px labels, missing focus states, decorative placeholders).

## Identity

* **Crest**: `public/images/crest-original.png` (812×900, supplied artwork, not redrawn). Derived WebP sizes 160/240/480 and a PNG favicon. Its wording — EGBE BOBAGUNWA OKUNRIN, AKILE IJEBU (1980 - 82), "Motto: Fun Itesiwaju" — is the only motto used.
* **Palette** (`resources/css/app.css` `:root`): wine `#972C2D`, deep wine `#6B1C22`, ink `#281F20`, crown yellow `#D3DA3B` (only on dark grounds), ochre `#E5B33F`, sun `#F3CA61`, crest blue `#D7EDFB`, paper `#FFFDF8`, mist `#F5F0E6`. Text/background pairs pass WCAG AA (axe colour-contrast rule passes on all 205 audited views).
* **Type**: Noto Serif (self-hosted; Latin, Latin-Extended and Vietnamese subsets cover Yoruba ẹ ọ ṣ and combining tone marks) for headings and editorial text; the system sans-serif stack for forms and tables. Minimum UI text 13 px; body 17 px (16 px on phones).
* **Language**: Yoruba words keep their diacritics and are marked `lang="yo"`.

## Principles

Editorial sections and rule lines instead of card grids; no gradients beyond the hero's subtle wine shading; no glassmorphism, stock photography, invented statistics, testimonials or placeholder leaders. Motion limited to underline transitions and disabled under `prefers-reduced-motion`. Sections that would be empty (news, events, leadership) are omitted rather than filled.

## Components (`resources/views/components`)

`x-field`, `x-textarea`, `x-select`, `x-check` (label, hint and error linked through `aria-describedby`; `aria-invalid`; optional `prefix` keeps IDs unique), `x-status` (consistent status badges), `x-money`, `x-idem` (idempotency key), `x-page-head`, `x-empty`, `x-public-hero`. Flash messages use `role=status` / `role=alert`. One-time links appear in a single highlighted secret box with a copy button.

## Application shells

Portal and administration share a dark header, a permission-aware side navigation (collapses behind a Menu button under 1080 px) and a work area with stats, details lists and tables. Tables sit in focusable, labelled scroll regions on small screens. Print styles hide navigation for statements.

## Rules for contributors

No inline `style` or `<script>` (CSP). Use the components. Every page has exactly one `h1`. New colours must be added as tokens and checked for contrast.
