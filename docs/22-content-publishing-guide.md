# Content and publishing guide

This guide is for Secretariat officers who look after the public website and the member portal. It explains how to edit the managed pages, write news and member notices, maintain activities, leadership entries, galleries and member documents, run events, and follow the association's editorial rules. Button names, page names and field labels are written exactly as they appear on screen.

> All names, dates and examples in this guide are synthetic. Official wording, leadership details, contact details and policies still await the association's approval (see `docs/23-client-decisions.md`).

## Who can do this

| Task | Role(s) that can do it |
|---|---|
| Write and edit pages, news, notices, activities, leadership entries, galleries, documents and images | Secretariat Officer |
| Publish, unpublish, return to draft or archive content | Secretariat Officer |
| Create, publish, cancel and archive events; download responses | Secretariat Officer |
| Change the contact details shown on the website, and whether they are shown | System Administrator (in **Settings**) |

Editing and publishing are separate permissions. An officer who can edit but not publish sees "A publisher must publish this … before it appears on the website", and an attempt to publish is refused with "You can prepare content, but publishing needs the publishing permission." In the default roles the Secretariat Officer holds both.

## Where to find things

In the administration menu, under **Website and communication**:

- **Content** opens **Website content**: the **Managed pages** table, the **Collections** list (**News**, **Member notices**, **Activities**, **Leadership**, **Galleries**, **Member documents**) and the **Media library** button.
- **Events** opens the events list.

Everything new starts as a draft and appears on the website or portal only once published.

## Managed pages

There are five managed pages: **About the association**, **History and heritage**, **Membership responsibilities and guidance**, **Privacy notice** and **Website and portal terms**. Each keeps a history of revisions.

### Editing a page

1. On **Website content**, press **Edit** beside the page.
2. In **Edit draft**, change the **Title**, **Summary** and **Page text (Markdown)**.
3. In **What changed?**, write a short note for other officers (for example "Corrected founding year").
4. Press **Save draft**. A new revision is created; the public page does not change.
5. Check **Preview of the latest draft** on the same page.

### Publishing or unpublishing

1. In the **Revisions** table, press **Publish** beside the revision you want and confirm. It replaces the public page immediately. You can publish an older revision in the same way to go back to earlier wording.
2. To take the text off the website, press **Unpublish page** under **Published version** and confirm. The website then shows its standard holding text.

The **Managed pages** table shows whether each page is published, draft only or not started, and warns "Newer draft not yet published" when a later draft exists. The 20 most recent revisions are listed.

## Writing in Markdown

Page text, articles, notices, activity descriptions and event descriptions use Markdown, as the hint under each box explains:

- Leave a blank line between paragraphs.
- Start a line with `##` for a heading.
- Start a line with `-` for a list item.
- Write links as `[link text](https://example.org)`.
- HTML is removed, and unsafe links are removed.

## News and member notices

- **News** is public. It appears on the website's news pages and the latest three on the home page.
- **Member notices** are visible only to signed-in members, under **Notices** in the member portal, where each member can mark a notice as read.

To write one:

1. Open **News** or **Member notices** and press **New news article** or **New notice**.
2. Fill in **Title**, **Summary (shown in lists)** (or **Summary** for a notice) and **Article** (or **Notice**). For news you may choose a **Cover image** from the media library.
3. Press **Save draft**.
4. Under **Publication**, press **Publish** and confirm. Use **Return to draft** to withdraw it for changes, or **Archive** to remove it from view while keeping the record.

The web address of a news article is made from its title when first saved and does not change if the title is edited later.

## Activities

1. Open **Activities** and press **New activity**.
2. Fill in **Title**, **Category**, **Summary** (required), **Description**, optionally an **Image**, and **Display order** (lower numbers are shown first).
3. Press **Save draft**, then **Publish** when approved.

## Leadership entries

Publish only names, titles and photographs that the association has approved for publication.

1. Open **Leadership** and press **New leadership entry**.
2. Fill in **Name (as approved for publication)**, **Office or title**, **Group** (**Executive**, **Council**, **Chiefs** or **Other**), **Term (e.g. 2025–2027)** and, only if approved, **Short biography** and **Approved photograph**.
3. Set **Display order**: within each group, lower numbers are shown first, then by name.
4. Press **Save draft**, then **Publish**.

On the public **Leadership** page, entries are shown under their group headings; groups appear in the order Executive, Council, Chiefs, Other officers. The home page shows up to four published Executive entries in display order.

## Media library and galleries

### Uploading an image

1. Press **Media library** on **Website content**.
2. Under **Upload an image**, choose the **Image file** (JPEG or PNG, up to 5 MB).
3. Write the **Description for screen readers (alt text)**, describing what the image shows (for example "Members at the 2025 annual general meeting"). Alt text is required.
4. Tick **The association has permission to publish this image, including from anyone clearly shown in it.** Do not tick it unless this is true.
5. Press **Upload image**.

Every image is re-encoded and resized on upload, which removes hidden information such as camera and location details. The image pickers on content and event forms list the 60 most recent uploads. Images cannot be deleted from these screens, so check before uploading.

### Building a gallery

1. Open **Galleries**, press **New gallery**, enter **Title** and **Description**, and press **Save draft**.
2. On the saved gallery, under **Add an image**, choose the **Image file**, write the **Description for screen readers (alt text)** (required) and an optional **Caption (optional)**, tick **The association has permission to publish this image (and consent from anyone pictured)**, then press **Add image**.
3. Repeat for each image, then press **Publish**.

## Member documents

Documents are stored privately and can be downloaded only by signed-in members, from **Documents** in the portal, once published.

1. Open **Member documents** and press **New document**.
2. Fill in **Title** and **Description**, and choose the **File (PDF, JPEG or PNG)** (PDF up to 10 MB; images up to 5 MB).
3. Press **Save draft**, then **Publish**.
4. To replace the file later, open the document and choose a new file; leave the box empty to keep the current one.

PDFs that contain scripts, embedded files or automatic actions are refused with a message asking you to print the document to a plain PDF and upload again.

## Events

Enter all dates and times in Lagos time.

### Creating an event

1. Open **Events** and press **New event**.
2. Fill in **Title**, **Summary**, **Full description (Markdown)** and **Location**.
3. Set **Starts** and, if known, **Ends (optional)**.
4. Choose **Who can see this event?**: **Everyone (public website and portal)** or **Members only (portal)**.
5. Under **Attendance responses**, tick **Ask members whether they will attend** if you want replies. Optionally set **Responses close (optional)** (no later than the start time) and **Capacity (optional)**.
6. Optionally choose a **Cover image** from the media library.
7. Press **Save as draft**. Later edits use **Save changes**.

### Event status

Use the buttons under **Publication**:

| Current status | Buttons offered |
|---|---|
| Draft | **Publish**, **Archive** |
| Published | **Mark as cancelled**, **Archive**, **Return to draft** |
| Cancelled | **Archive**, **Publish** |
| Archived | **Return to draft** |

A cancelled event stays visible with a cancellation notice until it is archived. Members-only events appear only in the portal; public events appear on the website and in the portal.

### Attendance responses

- Members reply in the portal with attending, maybe or not attending, and may add up to ten guests and a note.
- Replies are accepted only while the event is published, before the closing time (if set) and before the event starts.
- When a capacity is set, each attending member counts as one place plus their guests; replies that would exceed the capacity are refused.
- The **Attendance responses** panel shows the totals and each reply. Press **Download responses (CSV)** to save them.

## Contact details on the website

The secretariat email, telephone and address shown on the website are not edited here. They are controlled by the System Administrator in **Settings** (**Secretariat email**, **Secretariat telephone**, **Secretariat address**) and stay hidden until **Publish the contact details below on the public website** is switched on. Leave it off until the association has confirmed the details. The contact form works either way, and messages arrive under **Enquiries**.

## Editorial rules

1. Do not publish history, leadership biographies, statistics or testimonials that the association has not approved.
2. Do not publish photographs of people without their consent and the association's permission.
3. Verify every date-sensitive claim (anniversaries, terms of office, "current" officers, upcoming dates) before publishing, and review it again when it may have changed.
4. Use the **What changed?** note on pages so other officers can follow your edits.
5. Write alt text that describes the image plainly; do not leave it as a file name.
6. When unsure, save a draft and ask for approval before pressing **Publish**.

## If something goes wrong

- **Something was published by mistake:** press **Return to draft** or **Archive** on the item, or **Unpublish page** for a managed page. For a page, you can also publish the previous revision.
- **An upload is refused:** check the file type (JPEG or PNG for images; PDF, JPEG or PNG for documents) and size, and print PDFs to a plain PDF.
- **"An event cannot move from … to …":** use one of the buttons shown on the page; only those changes are allowed.
- **Contact details are wrong or missing on the website:** ask the System Administrator to check **Settings**.
- **Anything else unexpected:** note the exact message and contact the technical maintainer.
