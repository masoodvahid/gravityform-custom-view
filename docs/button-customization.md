# Button Customization Guide

## Overview
This guide explains how to customize the appearance of buttons in the Gravity Forms Custom View plugin.

## Default Button Styles
The plugin includes four types of action buttons in the frontend view:

1. **View Details** (Blue) - For viewing entry details
2. **User SMS** (Green) - For viewing user SMS templates
3. **Admin SMS** (Orange) - For viewing admin SMS templates
4. **API** (Red) - For sending data to external APIs

## How to Customize Buttons

### Method 1: Edit the Custom CSS File
The easiest way to customize button styles is to edit the custom CSS file:

1. Navigate to: `wp-content/plugins/gravity-form-custom-view/assets/css/custom-buttons.css`
2. Modify the CSS rules to match your desired styling
3. Save the file

### Method 2: Add Custom CSS to Your Theme
If you prefer not to edit plugin files (recommended for update compatibility):

1. Add custom CSS to your theme's stylesheet or customizer
2. Use the same CSS selectors as in the custom-buttons.css file

## CSS Selectors

- `.gfcv-btn` - Targets all buttons
- `.gfcv-btn-details` - Targets the View Details button
- `.gfcv-btn-user-sms` - Targets the User SMS button
- `.gfcv-btn-admin-sms` - Targets the Admin SMS button
- `.gfcv-btn-api` - Targets the API button

## Example Customizations

### Change Button Size
```css
.gfcv-btn {
    padding: 3px 6px; /* Make buttons smaller */
    font-size: 10px;  /* Reduce font size */
}
```

### Change Button Colors
```css
.gfcv-btn-details {
    background-color: #9b59b6 !important; /* Purple color */
    border-color: #8e44ad !important;
    background-image: linear-gradient(to bottom, #9b59b6, #8e44ad) !important;
}
```

### Remove Button Gradient
```css
.gfcv-btn {
    background-image: none !important;
}
```

### Add Rounded Corners
```css
.gfcv-btn {
    border-radius: 15px !important; /* Pill-shaped buttons */
}
```

## Notes
- Use `!important` to override the default styles
- Changes will affect all instances of the custom view shortcode
- Clear your browser cache after making changes if you don't see them immediately