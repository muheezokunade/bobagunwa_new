# Official website content (association-supplied)

**Source:** five documents from Gunwa Abiodun Orehin (Aremo Egbe) for the association, email "FW: Bobagunwa Website Development", 3 October 2026; confirmed for use at the 22 November 2026 demo. Loaded with `php artisan boba:content:official [--publish]` from `database/content/official-2026-10.php`.

| Document | Loaded as | Status |
|---|---|---|
| About Us ("Who we are") | page `about` | published with `--publish` |
| Giwa's Welcome Address | page `welcome`, shown on the home page | published with `--publish` |
| History on the Awujale | page `history` | **draft only** — see the flag below |
| GIC / Chiefs / Excos profile | 37 leadership entries: Giwa-in-Council (3), Bobagunwa Chiefs (23), Bobagunwa Executives 2025 – 2027 (11) | published with `--publish`; profile pop-ups show a biography once one is added |
| Website Profile (sitemap sketch) | not loaded | proposed menu: Home · About Egbe · Members · Activities · Events · Contact us (current menu differs; to agree) |

The loader saves pages as **new revisions** (history kept, earlier text recoverable from Administration → Content), only when the text changed, and records each publication in the audit log. Running it again changes nothing.

## Needs the association's confirmation

1. **History on the Awujale — not published.** The text calls Oba (Dr.) Sikiru Kayode Adetona "one of Nigeria's greatest living monarchs", "ninety-one years old" and "about sixty-six years" on the throne. Public reports state that he passed away on 13 July 2025. The association is redrafting the history (22 Nov demo action for Gunwa Abiodun Orehin); publish only the confirmed text. "History on Regberegbe" (listed in the sitemap sketch) has not been supplied.
2. **Spelling corrections** made while loading (wording otherwise unchanged):
   - About: "Our believe" → "Our belief"; "uploading the principles of Omoluabi" → "upholding …"; "comprises of" → "comprises"; "different works of life" → "different walks of life"; "Artisans, Traders et al." → "artisans, traders and more"; commitments reworded from "To promoting…" to "Promoting…" after "We are committed to".
   - Welcome: heading completed as "…Okunrin Akile Ijebu" (the document stops at "Akile").
   - History (draft): "Nigerian's greatest living monarch" → "Nigeria's greatest living monarchs"; "Price Rufai" → "Prince Rufai"; "the the" → "the"; "Ijebu's" → "Ijebus"; "6months" → "six months".
   - Leadership: "Asst. general Secretary" → "Asst. General Secretary".
3. **Leadership list:** "Gunwa Kolawole Orekoya – Akogun Egbe" appears twice (loaded once). Five titles have no named holder and are not shown: Babalaje Egbe, Bobaselu Egbe, Bamofin Egbe, Tunwashe Egbe, Otun Balogun Egbe. Photographs and short biographies are still to come.
4. **Core values**: a draft of five value tiles (English and Yoruba) is on the site — Progress / Ìtẹ̀síwájú, Character / Ìwà Ọmọlúàbí, Protection / Ààbò, Unity / Ìṣọ̀kan, Service / Iṣẹ́ Ìsìn — summarising the five commitments; the association should confirm the Yoruba and the wording. Yoruba diacritics in the supplied pages are still to come; that text is loaded exactly as written.

## Not loaded on purpose

The pack also lists **86 members by name**. That is personal data for the member register, not website copy: it is imported only after the verified roster is approved (decision D-019) and never onto staging, which holds synthetic data only. A public member list is decision D-012 (currently: none).
