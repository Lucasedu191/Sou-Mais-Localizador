(function () {
	const notices = document.querySelectorAll('.notice.is-dismissible');
	notices.forEach((notice) => {
		const button = notice.querySelector('.notice-dismiss');
		if (button) {
			button.addEventListener('click', () => {
				notice.classList.add('sm-dismissed');
			});
		}
	});

	const mediaFields = document.querySelectorAll('.soumais-media-field');
	if (!mediaFields.length || !window.wp || !window.wp.media) {
		return;
	}

	mediaFields.forEach((field) => {
		const selectButton = field.querySelector('.soumais-media-select');
		const removeButton = field.querySelector('.soumais-media-remove');
		const input = field.querySelector('input[type="hidden"]');
		const preview = field.querySelector('.soumais-media-preview');

		let frame = null;

		const updatePreview = (attachment) => {
			if (!preview) {
				return;
			}
			if (attachment && attachment.url) {
				preview.innerHTML = '<img src="' + attachment.url + '" alt="">';
			} else {
				preview.innerHTML = '<span class="soumais-media-placeholder">Nenhuma imagem selecionada.</span>';
			}
		};

		if (selectButton) {
			selectButton.addEventListener('click', (event) => {
				event.preventDefault();

				if (!frame) {
					frame = wp.media({
						title: 'Selecionar imagem da unidade',
						button: { text: 'Usar imagem' },
						multiple: false,
					});

					frame.on('select', () => {
						const attachment = frame.state().get('selection').first().toJSON();
						if (input) {
							input.value = attachment.id;
						}
						updatePreview(attachment);
					});
				}

				frame.open();
			});
		}

		if (removeButton) {
			removeButton.addEventListener('click', (event) => {
				event.preventDefault();
				if (input) {
					input.value = '';
				}
				updatePreview(null);
			});
		}
	});
})();
