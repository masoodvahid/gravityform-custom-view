# Version 1.5.6 — CSS classes and page numbers in archival reports

## Single source of styling

- `assets/css/report.css`: reusable RTL report layout, field labels and values, three-column registration table, monochrome section headings, optional blocks, choice markers and 90% text in the three-column answer grid. It is enqueued on frontend pages that use `[gf-custom-view]`.
- `assets/css/report-print.css`: print-window-only overrides for A4 paper and black text/lines on white, plus a CSS `@page @bottom-center` page footer.
- The print window loads both linked CSS files rather than embedding hundreds of lines of CSS in JavaScript.
- Cache busting uses `GFCV_PLUGIN_VERSION`, so updated CSS is fetched on the next release.

A lightweight class-based MSTID template (with no inline `style` attributes) is supplied as the separate output `mstid_report_class_based_v156.html`. Paste it into **Details View HTML** of the view you intend to print. Existing saved HTML is NOT overwritten on plugin upgrades.

Example class-only markup:

```html
<table class="mstid-section mstid-can-hide" data-mstid-columns="2">
  <tr><th colspan="2">اطلاعات شرکت</th></tr>
  <tr class="mstid-data-row">
    <td><div class="mstid-bound mstid-field" data-gf-field="83">
      <div class="mstid-label">نام شرکت</div>
      <div class="mstid-answer" dir="auto">{83}</div>
    </div></td>
  </tr>
</table>
```

The exact `mstid-data-row`, `mstid-bound`, `mstid-can-hide` and `data-gf-field` conventions still activate server-side conditional field filtering. The cleanup also supports three-column `data-mstid-columns="3"` registration rows.

## Page numbering

The print-window-only stylesheet includes:

```css
@page {
  size: A4;
  margin: 12mm 13mm 20mm;
  @bottom-center {
    content: "صفحه " counter(page) " از " counter(pages);
  }
}
```

Use **Print / PDF** from the record details dialog. Page numbers appear on every physical PDF/printed sheet, not just at the end of the HTML. This relies on CSS page-margin box support (Chrome/Edge 131+ supported; Firefox varies and may omit the page counter). For consistent archiving, use current Chrome/Edge and turn OFF their default “Headers and footers” setting if it creates a second footer. Do not manually insert a static X/Y footer into the HTML.

## Compatibility and testing

- No data migration, new table columns, or changed merge-tag IDs.
- All 63 IDs, nine sections, three optional subsections and registration three-column layout were checked in the lightweight MSTID template.
- A three-page WeasyPrint render verified the generated “صفحه 1 از 3” / “صفحه 2 از 3” / “صفحه 3 از 3” output; final behavior in the target WordPress print popup still needs live Chrome/Edge verification.
- Tests of conditional filtering on the actual site's stored entries remain to be performed in staging.

Publish GitHub Release `v1.5.6` after merging and testing this branch.
