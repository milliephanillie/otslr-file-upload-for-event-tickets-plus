document.addEventListener('DOMContentLoaded', () => {
  jQuery(document).on('tribe-ar-fields-appended', function () {
    console.log("hello there");
    console.log(jQuery('body .otslr-device'))
    console.log(jQuery('.otslr-device'))

    jQuery('body')
      .off('change.otslr')
      .on('change.otslr', '.otslr-device', async function (e) {
            console.log(jQuery('.otslr-device'))
        const input = e.target;
        if (input.type === 'file' && input.files.length > 0) {
          const file = input.files[0];
          const name = input.name;
         const attendeeId = input.dataset.attendee !== undefined && input.dataset.attendee !== null
          ? input.dataset.attendee
          : null;

          console.log(file)
          console.log(name)
          console.log("Attendee")
          console.log(attendeeId)
          try {
            // i need to set a cookie here called otslr-file-cookie

            const response = await setFile(file, name, attendeeId);
            const json = await response.json();
            console.log(json);
          } catch (error) {
            console.error('Upload failed:', error);
          }
        }
      });
  });
});




const setFile = async (file, name, attendeeId) => {
  const url = `${window.otslrVars?.siteUrl}/wp-json/otslr/v1/upload-file-to-temp`;
  const formData = new FormData();
  const otslrFileCookie = window.otslrVars?.otslrFileCookie;
  const postId = window.otslrVars?.postId;

  if (otslrFileCookie && name && file) {
    formData.append('otslrFileCookie', otslrFileCookie);
    formData.append('name', name);
    formData.append('file', file);
    formData.append('attendee_id', attendeeId)
    formData.append('post_id', postId);

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'X-WP-Nonce': window.otslrVars?.restNonce || ''
        },
        body: formData
      });

      return response;
    } catch (error) {
      console.error('File upload failed:', error);
      return null;
    }
  } else {
    console.warn('Missing file, name, or cookie data for file upload.');
    return null;
  }
};

window.tribe_aggregator.fields.construct.myCustomInit = function($fields) {
    console.log('Fields initialized:', $fields);
    // Or add event listeners, inject extra logic, etc.
};

console.log(window.tribe_aggregator.fields);



