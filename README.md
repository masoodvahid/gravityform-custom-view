# Gravity Forms Results Custom View with ACL

*Farsi: نمایش رکوردهای گرویتی فرم با تعیین سطح دسترسی*

A WordPress plugin that adds custom view functionality to Gravity Forms with SMS and API integration capabilities and Access Control List (ACL).

## Description

Gravity Forms Results Custom View with ACL extends Gravity Forms by allowing administrators to create custom views of form entries with granular access control. Each view can be displayed on the frontend using a shortcode, showing selected form fields in a table format with interactive buttons for previewing details, viewing SMS templates, and sending data to external APIs.

## Features

- Create multiple custom views for any Gravity Form
- Select which form fields to display in the table view
- Configure custom SMS templates for both users and administrators
- Integration with Kavenegar SMS service
- API integration for sending form data to external services
- Customizable HTML for detailed entry views
- User access control based on WordPress user IDs
- Responsive design for both admin and frontend interfaces

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- Gravity Forms 2.4 or higher

## Installation

1. Upload the `gravity-form-custom-view` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure Gravity Forms is installed and activated
4. Navigate to Forms > Custom View to start creating custom views

## Usage

### Creating a Custom View

1. Go to Forms > Custom View in the WordPress admin menu
2. Click "Add New" to create a new custom view
3. Fill in the following fields:
   - View Title: A name for your custom view
   - Form: Select the Gravity Form to use
   - Field IDs to Display: Enter comma-separated field IDs to show in the table
   - Admin SMS Pattern: Template for admin SMS messages
   - User SMS Pattern: Template for user SMS messages
   - Kavenegar API Key: Your SMS service API key
   - Send to API: Enable/disable API integration
   - Send to API Details: Configuration for API integration
   - Details View HTML: Custom HTML for the detailed view popup
   - User Access IDs: Comma-separated WordPress user IDs who can access this view
4. Save the custom view

### Using the Shortcode

Once you've created a custom view, you can display it on any page or post using the shortcode:

```
[gf-custom-view id=X]
```

Replace `X` with the ID of your custom view.

### Frontend Features

The frontend display includes:

- A table showing the selected form fields for each entry
- Action buttons for each entry:
  - Preview Details: Shows a popup with detailed entry information
  - User SMS: Displays the user SMS template with entry data
  - Admin SMS: Displays the admin SMS template with entry data
  - Send to API: Shows the entry data in JSON format for API integration

## Template Tags

In the SMS patterns and Details View HTML, you can use the following template tags:

- `{X}` - Replace X with a field ID to include that field's value
- `{entry_id}` - The ID of the entry
- `{date_created}` - The date the entry was created

## Support

For support or feature requests, please contact the plugin author.

## Author

Masood Vahid - [https://rahkar-digital.ir](https://rahkar-digital.ir)

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```