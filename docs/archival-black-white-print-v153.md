# Black-and-white archival printing (1.5.3)

This release changes the **print output** of custom entry detail views:
- Black text and rules on a white page; colored and gray backgrounds, shadows and gradients are removed in the print window even if the saved Details View HTML has inline color styles.
- All radio and checkbox selections render as printable text/symbols rather than disabled HTML form controls. **■** indicates selected and **□** indicates not selected; selected answers are also bold and include the Persian phrase "(انتخاب‌شده)" for clarity.
- The administrative "show all multiple-choice options" setting still works: with it enabled, all alternatives are displayed with markers; if disabled, only selected choices appear.
- Print document uses A4 margins and breaks between question rows where possible.
- Logo images are converted to high-contrast monochrome in the print window.

An optional, fully monochrome HTML version of the MSTID Details View template has been provided separately to paste into the per-view settings. The stored template must be updated manually; plugin updates do not overwrite saved custom view HTML.

Publish a GitHub Release using tag `v1.5.3` after testing on a staging site. The plugin version header has already been updated to 1.5.3. Check printed output in at least Chrome and Firefox (especially the black-and-white logo and checkbox glyph font fallback).
