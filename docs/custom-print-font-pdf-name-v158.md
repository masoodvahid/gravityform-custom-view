# Version 1.5.8 — Custom Print Font and Meaningful PDF Names

## Custom Print Font

The global admin screen (**لیست‌های گرویتی فرم**) now labels the font setting **«فونت سفارشی»** instead of «IranYekan». Administrators can supply two **separate**, legally hosted font URLs:

- **Regular (400)**: used for ordinary field labels, nonselected checkbox/radio options and surrounding report text.
- **Bold (700)**: used for field answers, selected checkbox/radio options and emphasized report text.

Supported direct file extensions: WOFF2 (preferred), WOFF, TTF and OTF. Use HTTPS on the same WordPress domain when possible. The plugin **does not distribute licensed font binaries**. The print window registers both files as one internal family, `GFCV Custom Print Font`, with different weights. A previously saved IranYekan URL is automatically used for the Regular field until the administrator explicitly saves a new value.

**Typography rules:** `.mstid-label` → 400, `.mstid-answer` → 700. Multiple-choice grids remain **three columns**, font-size **90%**, borderless; options marked `gfcv-choice--unselected` use 400 even within a bold answer cell, while `gfcv-choice--selected` uses 700. The print stylesheet enforces the same rules.

## PDF document and suggested filename

Each view's edit screen now has a **«نام فایل PDF»** section where the admin chooses the form fields used for the **first name** and **last name**. The fields are populated from the selected Gravity Form. For a compound Gravity Forms Name field, its sub-inputs (e.g. `1.3`, `1.6`) are also selectable. For a single full-name field, select only the first selector.

During **record details → Print / Save as PDF**, the server builds the current entry's document title:

`شناسه فرم - نام و نام خانوادگی ثبت‌کننده - عنوان فرم`

Example: `12 - علی رضایی - پرسشنامه ساخت افزایشی فلزی.pdf`

The extension `.pdf` is appended **by the browser**, not by the document title. The popup's `document.title` controls Chrome/Edge's usual **Save as PDF** suggested filename. Some browsers and printer drivers can override the suggestion.

For views that have no configured name IDs or an entry with empty names, the title falls back to `12 - رکورد 1007 - عنوان فرم`. Unsafe filename characters and HTML tags are stripped. Field IDs are stored per view, not as global site settings.

**Migration:** `GFCV_DB::maybe_upgrade()` adds `pdf_first_name_field_id` and `pdf_last_name_field_id` to existing views on plugin activation/load. Existing view data and report HTML remain intact. The details AJAX endpoint now checks view access and verifies that a requested active entry belongs to the view's form before returning a rendered report or its proposed PDF filename.

Publish Release `v1.5.8` after the changes are merged, then update WordPress and configure the font files and per-view PDF filename fields. Test print preview with at least one checkbox/radio selected, an empty name, a compound Name field, and an accented/Persian name.

## Validation caveat

Static source checks are possible, but a live WordPress/Gravity Forms and browser print test is still required. The model's container could not reach GitHub to run full PHP/JS syntax checks against downloaded repository files.
