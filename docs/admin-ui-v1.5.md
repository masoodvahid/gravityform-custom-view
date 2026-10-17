# Version 1.5.0 — Admin interface, field options, and test SMS

- Admin screen: responsive, right-to-left styling and a four-column field ID browser.
- Table columns: select by name from a dropdown or click a field card; removable chips store IDs in the existing `field_ids` column.
- **Show all multiple-choice options** (default on): radio and checkbox questions list all possible options and indicate the saved selection. Turn off to show only the chosen answers.
- **Respect conditional field visibility** (default on): for annotated Details View HTML, hide fields when Gravity Forms' saved-entry condition does not match, and hide blank annotated responses. Turn off to retain all those annotated fields, including unanswered ones.
- **Test SMS:** save the view before testing. Preview uses the latest active form entry and the saved admin SMS lookup pattern. Only WordPress admins can access the test endpoints. The confirmation button sends the real message to 1–10 saved admin mobile numbers via Kavenegar. A one-minute server-side cooldown reduces duplicate submissions. This is a real, potentially billable send; test on a staging environment first.
- Existing custom view records acquire the new fields via a `dbDelta` migration; default values are 1.

## Compatibility

PHP 7.4+, WordPress 5.8+, Gravity Forms and (for conditional HTML cleanup) PHP DOM extension. No new JavaScript libraries.

Important: the condition setting affects the Details View, not the table columns. For conditional visibility, each field wrapper in Details View HTML needs a `data-gf-field="ID"` annotation as described in `docs/conditional-print-and-github-updates.md`.

To publish an update through GitHub, merge changes, bump the version in plugin header and plugin constant, and publish a release tag `v1.5.0` with an optional `gravity-form-custom-view.zip` plugin archive.
