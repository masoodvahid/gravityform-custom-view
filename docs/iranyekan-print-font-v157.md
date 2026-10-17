# v1.5.7 — IranYekan in printed reports

## What changed

- **Print only:** The A4 report and the `صفحه X از Y` footer now use the `GFCV IranYekan` / `IRANYekan` / `IranYekan` font family stack instead of Tahoma.
- **Admin setting:** Open WordPress admin > **لیست‌های گرویتی فرم**. Enter the full URL of your legitimately licensed IranYekan `.woff2`, `.woff`, `.ttf` or `.otf` file into the **فونت چاپ گزارش‌ها — IranYekan** panel and click save.
- The plugin loads the chosen font into the **separate print window** through the browser FontFace API. It waits for the font to load before the print dialog (with an 8-second fallback if resources stall). This also applies to browser **Save as PDF**.
- When no font URL is configured, the browser uses a locally installed IranYekan font if one exists; otherwise it falls back to Tahoma. A theme's `@font-face` rules do not automatically propagate to a newly opened print window.
- All reports keep black text and page-count footers. Other changes from v1.5.6 are untouched (three-column checkbox answers, report CSS classes, pagination, conditional fields).

## Hosting

Use the font file you are licensed to use, preferably hosted on the **same domain** as WordPress, for example `https://example.com/wp-content/uploads/fonts/IRANYekan.woff2`. Font files are **not** included in the public GitHub repository; no license is implied or redistributed. If using a CDN/another domain, configure CORS to permit the site's origin.

You may upload the file through your host's file manager/SFTP. Depending on WordPress security settings, the Media Library may not accept font files by default.

## Deployment

Once PR merged, publish GitHub Release tag `v1.5.7`, update the plugin from the WordPress dashboard, then configure the IranYekan font URL in the **لیست‌های گرویتی فرم** page.

Test Print/Save as PDF in Chrome/Edge. If the font appears unchanged, open Developer Tools in the print window and inspect the font URL/network errors (404, blocked MIME type, CORS, licensing issues). Do not upload font binaries to the GitHub plugin distribution.

The installed site's font file was unavailable for integration testing.
