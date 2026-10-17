# v1.5.5 — Three-column multiple-choice answers

This release changes the **presentation** of radio and checkbox choices in record details and their black-and-white archive printouts:

- Three equal-width RTL columns (`display:grid; grid-template-columns:repeat(3,minmax(0,1fr))`).
- No border, pill, shadow, or card background around individual answers.
- Choice text at **90%** of the containing answer's font size; no 120% enlargement.
- Selected options retain the black solid selection marker, bold label and "(انتخاب‌شده)" text. Unselected options retain a hollow marker.
- Long answers wrap within their column.
- Old `<input disabled>` fallback print labels are grouped into three columns as well.

The changes are implemented inside the plugin and work with existing Details View HTML templates such as the monochrome MSTID three-column-registration template. **There is no need to replace that saved HTML.** The three-column user-information table and other report layout sections are unchanged.

Publish a GitHub Release tagged `v1.5.5` after staging tests. Confirm both the modal and browser print preview, including questions with many choices and long Persian labels.
