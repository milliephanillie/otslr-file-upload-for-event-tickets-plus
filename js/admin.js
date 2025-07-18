// jQuery(document).ready(function ($) {
//   $(document).on('click', '.add-attendee-field', function (e) {
//     const $button = $(this);
//     const fieldType = $button.data('type'); // e.g. "file", "text", etc.

//     // Find all existing type inputs inside the sortable area
//     const existingTypes = $('#tribe-tickets-attendee-sortables input[type="hidden"][name*="[type]"]')
//       .map(function () {
//         return $(this).val(); // "file", "text", etc.
//       }).get();

//     const alreadyExists = existingTypes.includes(fieldType);

//     if (alreadyExists) {
//       e.preventDefault();
//       alert(`A "${fieldType}" field already exists.`);
//       return false;
//     }
//   });

//   // Mark the newly added field row after it's injected via AJAX
//   $(document).on('event-tickets-plus-field-added.tribe', function (e, data) {
//     const fieldType = data.type;
//     console.log('Field added:', fieldType);

//     const $lastField = $('#tribe-tickets-attendee-sortables .tribe-tickets__admin-attendee-info-field').last();
//     $lastField.attr('data-type', fieldType);
//   });
// });


jQuery(document).ready(function ($) {
  // Backup their original function
  const originalAddField = window.ticketsPlus?.metaAdmin?.event?.addField;

  if (!originalAddField) {
    console.warn('Could not find original addField method from Event Tickets');
    return;
  }

  // Unbind original handler
  $(document).off('click', '.add-attendee-field');

  // Rebind with our custom logic
  $(document).on('click', '.add-attendee-field', function (e) {
    e.preventDefault();

    const $button = $(this);
    const fieldType = $button.data('type'); // e.g. "file", "text", etc.

    const existingTypes = $('#tribe-tickets-attendee-sortables input[type="hidden"][name*="[type]"]')
      .map(function () {
        return $(this).val();
      }).get();

    const alreadyExists = existingTypes.includes(fieldType);

    if (alreadyExists) {
      alert(`A "${fieldType}" field already exists.`);
      return false; // Do NOT call original
    }

    // Safe to add: call the original method
    originalAddField.call(this, e);
  });

  // Optional: mark new fields after they're added
  $(document).on('event-tickets-plus-field-added.tribe', function (e, data) {
    const $last = $('#tribe-tickets-attendee-sortables .tribe-tickets__admin-attendee-info-field').last();
    $last.attr('data-type', data.type);
  });
});
