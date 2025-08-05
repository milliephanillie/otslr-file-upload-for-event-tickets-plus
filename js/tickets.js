function bindOtslrFileTrigger() {
	jQuery('body')
		.off('click.otslr')
		.on('click.otslr', '.otslr-file-trigger', function () {
			const $button = jQuery(this);
			const $input = $button.siblings('.otslr-file-input');
			const $input_text = $button.siblings('.otslr-file-input-text');
			const $filename = $button.siblings('.otslr-file-name');

			console.log("in the click")

			if (!$input.length) return;

			$input.off('change.otslr').on('change.otslr', async function () {
				const file = this.files[0];
				const name = $button.attr('data-name');
				const attendeeId = $input.data('attendee');

				console.log("in the click 2")

				console.log(file)
				console.log(name)
				console.log(attendeeId)

				if (!file || !name || !attendeeId) return;

				console.log("in the click 3")

				try {
					if (window.otslrVars?.loader) {
						let loaderGif = `<img style="width: 21px; height: 21px;" width="21" height="21" src="${window.otslrVars.loader}" />`;
						$filename.html(loaderGif);
					}
					
					const response = await setFile(file, name, attendeeId);
					const json = await response.json();

					console.log(json)

					if (json?.url) {
						$filename.text(file.name);
						$button.attr('data-uploaded-url', json.url);
						$input_text.val(json.url);
					}
				} catch (err) {
					console.error('Upload failed', err);
					$filename.text('Upload failed. Please ensure you are uploading the correct file type.');
				}
			});

			$input.trigger('click');
		});
}

document.addEventListener('DOMContentLoaded', () => {
	bindOtslrFileTrigger();
	jQuery(document).on('tribe-ar-fields-appended', bindOtslrFileTrigger);
});

const setFile = async (file, name, attendeeId) => {
	const url = `${window.otslrVars?.siteUrl}/wp-json/otslr/v1/upload-file-to-temp`;
	const formData = new FormData();
	const postId = window.otslrVars?.postId;

	if (name && file) {
		formData.append('name', name);
		formData.append('file', file);
		formData.append('attendee_id', attendeeId);
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
		console.warn('Missing file, name, or attendee ID.');
		return null;
	}
};




