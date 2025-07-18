# The Email Templates

## Admin email to receive photo ID in receipt

- The attendee does **not** receive the photo.
- Only the admin receives the file reference in their confirmation email.

## Where do the original template email files live?

You can view where the original template files for emails for Event Tickets and Event Tickets Plus are located here:  
https://theeventscalendar.com/knowledgebase/ticket-rsvp-template-files/#emails

## Customizing Admin Email Templates

The email templates are different for the admin in this setup and require following the override conventions described in the above link.

To include a link to the uploaded photo ID in admin emails, the following templates must be customized and placed inside your theme:

```
{your-theme}/tribe/tickets/emails/admin-new-order.php
{your-theme}/tribe/tickets/emails/new-order/admin-body.php
{your-theme}/tribe/tickets/emails/template-parts/body/order/attendees-table/admin-attendee-info.php
{your-theme}/tribe/tickets/emails/template-parts/body/order/admin-attendees-table.php
```

---

# File Upload

## Modal Template

You must create a new template file for the modal and place it inside your theme. This file is specifically for rendering the upload modal. It will be referenced by a PHP class created later.

This modal works in conjunction with a JavaScript file that handles the file upload upon input change. The JavaScript file lives in the plugin (not the theme) and will be discussed separately.

The modal template should be located at:

# The Plugin

## OTSLR Additional Fieldsets for Tribe Events Plus Plugin

This is a lightweight plugin where the core functionality lives. It extends Event Tickets Plus to support file uploads tied to attendee metadata.

### Classes

Main class file:

### OtslrUpload Class

- The `OtslrUpload` class is a custom WordPress plugin component that registers a secure REST API endpoint to handle file uploads for attendees.
- Files are uploaded to a custom directory inside the WordPress uploads folder.
- The directory itself is permanent and structured by ticket and attendee IDs.
- A transient is created to temporarily store the file reference and metadata.

---

### File Storage Structure

Uploaded files are saved in the following format:

`/wp-content/uploads/tribe/attendee-meta/{ticket_id}/{attendee_id}/`


This folder is persistent unless manually deleted.

---

### Transient Metadata

If the upload is successful, a transient is created for 48 hours using this structure:

```php
[
  'url' => 'https://example.com/wp-content/uploads/tribe/attendee-meta/...',
  'transient_id' => 'otslr_attendee_file_{ticket_id}_{attendee_id}',
  'attendee_id' => {attendee_id},
  'ticket_id' => {ticket_id},
  'label' => {field_key}
]
```

# Admin View

## Photo ID Upload Field

A single **Photo ID** field has been created for the admin interface. This is a file input restricted to one field per attendee, labeled specifically as "Photo ID".

Currently, only one file upload field is allowed per attendee.

---

## Admin View Templates

All templates for the admin interface are located in the following directory:

`plugins/otslr-additional-fields/src/admin-views/meta-fields/`


These templates control how the custom field appears in the attendee admin area.

---

## ⚠️ file.php Note

The following file is intentionally left empty:


This file exists to satisfy the system's structure but does not require any content, as the logic is handled in the other files within the `meta-fields` folder.
