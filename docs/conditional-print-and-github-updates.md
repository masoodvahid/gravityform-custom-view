# Conditional details and GitHub updates

## Conditional fields in a details/print HTML template

The detail rendering engine now supports **opt-in**, server-side cleanup before
the existing `{field_id}` substitutions. This only affects the HTML details view;
SMS and API templates retain their previous behavior.

Use these attributes/classes in your custom Details View HTML:

```html
<table class="mstid-can-hide">
  <tr><th colspan="2">Company</th></tr>
  <tr class="mstid-data-row">
    <td class="mstid-bound" data-gf-field="83">
      Company name: <span class="mstid-value">{83}</span>
    </td>
    <td class="mstid-bound" data-gf-field="58">
      Company ID: <span class="mstid-value">{58}</span>
    </td>
  </tr>
</table>
```

- `data-gf-field="83"`: the smallest element that should disappear if that field
  is hidden by Gravity Forms conditional logic **or** its saved answer is empty.
- `mstid-bound`: a field wrapper counted when pruning empty rows.
- `mstid-data-row`: remove a row containing no remaining bound field; expand
  a surviving cell to the full table width.
- `mstid-can-hide`: remove the entire table, including its heading, if no
  annotated fields remain.
- `data-mstid-optional="yes"`: mark an optional section table.
- `mstid-optional-intro`: optional introduction to remove when no optional
  section remains.

Use the prepared HTML template for the MSTID form, or annotate another
HTML template using the same conventions. Templates without `data-gf-field`
are unchanged. The cleanup requires the PHP DOM extension.

**Important:** The plugin's PHP code cannot remove static labels from an
unannotated HTML template automatically. It is necessary to annotate the
appropriate wrappers once. Gravity Forms evaluates conditional rules using
saved entry data, which is not the same thing as simply hiding unanswered fields.

## GitHub updates

The plugin has WordPress's custom **Update URI** header and uses GitHub's
**latest published Release**, rather than changes to main. Requires WordPress
5.8+ and a GitHub repository accessible to the WordPress server.

1. Merge the update implementation, install the updated plugin once manually.
2. Create a Release tagged `v1.4.0`; use this as the bootstrap release.
3. For each later version, bump the `Version` header and
   `GFCV_PLUGIN_VERSION` to the same version, e.g. `1.4.1`.
4. Publish a GitHub Release for that tag. Optionally attach
   `gravity-form-custom-view.zip` containing the top-level
   `gravity-form-custom-view/` plugin directory (recommended).
5. WordPress → Dashboard → Updates → Check Again. Newer releases should
   show as standard plugin updates, with normal update installation.

The updater handles GitHub's auto-generated source ZIP folder names too.
It checks GitHub on the normal WordPress update schedule and caches metadata
for six hours. It does not require a GitHub token for this public repository.

**Caution:** Back up the site and test updates on staging before enabling
automatic background updates. WordPress may need outbound access to
`api.github.com` and `github.com`.
