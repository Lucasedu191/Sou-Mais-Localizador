(function () {
	const data = window.SouMaisLocator;
	if (!data || !window.wp || !window.wp.apiFetch) {
		return;
	}

	const { apiFetch } = window.wp;

	document.addEventListener('DOMContentLoaded', () => {
		const containers = document.querySelectorAll('.sm-locator');
		containers.forEach((container) => initLocator(container));
	});

	function initLocator(root) {
		const searchForm = root.querySelector('.sm-locator__search');
		const queryInput = root.querySelector('.sm-input--query');
		const locationBtn = root.querySelector('.sm-button--location');
		const resultsWrap = root.querySelector('.sm-locator__results');
		const statusEl = root.querySelector('.sm-locator__status');
		const modal = root.querySelector('.sm-modal');
		const closeModalBtn = root.querySelector('.sm-modal__close');
		const leadForm = root.querySelector('.sm-locator__lead-form');
		const unitField = leadForm ? leadForm.querySelector('input[name="unidade"]') : null;
		const unitLabel = leadForm ? leadForm.querySelector('.sm-lead__unit') : null;
		const submitBtn = leadForm ? leadForm.querySelector('button[type="submit"]') : null;

		if (!searchForm || !resultsWrap || !leadForm) {
			return;
		}

		setupPhoneMask(leadForm.querySelector('input[name="telefone"]'));
		populateUtms(leadForm);

		const defaults = {
			radius: data.settings.radius,
			limit: data.settings.results_limit,
		};

		resultsWrap.classList.add('sm-locator__results--hidden');

		const revealResults = () => {
			resultsWrap.classList.remove('sm-locator__results--hidden');
		};

		searchForm.addEventListener('submit', (event) => {
			event.preventDefault();
			const query = queryInput.value.trim();
			const params = { ...defaults, query };
			revealResults();
			fetchUnits(params, { resultsWrap, statusEl, root });
		});

		if (locationBtn) {
			locationBtn.addEventListener('click', () => {
				if (!navigator.geolocation) {
					showStatus(statusEl, data.strings.error_message, true);
					return;
				}

				showStatus(statusEl, data.strings.success_message);

				navigator.geolocation.getCurrentPosition(
					(position) => {
						const params = {
							...defaults,
							lat: position.coords.latitude,
							lng: position.coords.longitude,
						};
						revealResults();
						fetchUnits(params, { resultsWrap, statusEl, root });
					},
					() => {
						showStatus(statusEl, data.strings.error_message, true);
					},
					{ enableHighAccuracy: true, timeout: 8000 }
				);
			});
		}

		resultsWrap.addEventListener('click', (event) => {
			const target = event.target.closest('.sm-card__cta');
			if (!target) {
				return;
			}
			const unitId = target.dataset.unit;
			const unitName = target.dataset.unitName || target.textContent;
			if (unitField) {
				unitField.value = unitId;
			}
			if (unitLabel) {
				unitLabel.textContent = unitName;
			}
			openModal(modal);
		});

		if (closeModalBtn) {
			closeModalBtn.addEventListener('click', () => closeModal(modal));
		}
		modal?.addEventListener('click', (event) => {
			if (event.target === modal) {
				closeModal(modal);
			}
		});

		leadForm.addEventListener('submit', (event) => {
			event.preventDefault();
			if (!submitBtn) {
				return;
			}

			const payload = formToJSON(leadForm);
			payload.nonce = data.nonce;

			submitBtn.disabled = true;
			submitBtn.classList.add('is-loading');
			showStatus(statusEl, data.strings.success_message);

			apiFetch({
				path: '/soumais/v1/lead',
				method: 'POST',
				data: payload,
				headers: {
					'X-WP-Nonce': data.nonce,
				},
			})
				.then((response) => {
					if (response.redirect_url) {
						window.location.href = response.redirect_url;
					} else {
						showStatus(statusEl, data.strings.success_message);
						closeModal(modal);
					}
				})
				.catch(() => {
					showStatus(statusEl, data.strings.error_message, true);
				})
				.finally(() => {
					submitBtn.disabled = false;
					submitBtn.classList.remove('is-loading');
				});
		});

		// Carrega resultados iniciais.
		fetchUnits(defaults, { resultsWrap, statusEl, root });
	}

	function fetchUnits(params, context) {
		const { resultsWrap, statusEl } = context;
		showStatus(statusEl, '', false);
		resultsWrap.classList.add('is-loading');

		const searchParams = new URLSearchParams();
		Object.entries(params).forEach(([key, value]) => {
			if (value !== undefined && value !== null && value !== '') {
				searchParams.append(key, value);
			}
		});

		return window.wp
			.apiFetch({
				path: '/soumais/v1/unidades?' + searchParams.toString(),
				method: 'GET',
			})
			.then((items) => {
				renderResults(items, context);
			})
			.catch(() => {
				showStatus(statusEl, data.strings.error_message, true);
			})
			.finally(() => {
				resultsWrap.classList.remove('is-loading');
			});
	}

	function renderResults(items, context) {
		const { resultsWrap } = context;
		if (!Array.isArray(items) || items.length === 0) {
			resultsWrap.classList.remove('has-results');
			resultsWrap.innerHTML = '<p class="sm-locator__empty">' + (data.strings.empty || 'Nenhuma unidade encontrada.') + '</p>';
			return;
		}

		resultsWrap.classList.add('has-results');

		resultsWrap.innerHTML = items
			.map((unit) => {
				const distance = unit.distance ? `<span class="sm-card__distance">${unit.distance.toFixed(1)} km</span>` : '';
				const whatsapp =
					data.settings.show_whatsapp && unit.whatsapp
						? `<a class="sm-card__whatsapp" href="https://wa.me/${unit.whatsapp}?text=${encodeURIComponent('Olá! Tenho interesse na unidade ' + unit.title)}" target="_blank" rel="noopener">${data.strings.whatsapp_label}</a>`
						: '';
				return (
					'<article class="sm-card">' +
					'<div class="sm-card__halo"></div>' +
					'<div class="sm-card__inner">' +
					`<figure class="sm-card__media"><img src="${unit.thumbnail || data.assets.placeholder}" alt="${unit.title}"></figure>` +
					'<div class="sm-card__body">' +
					`<h3 class="sm-card__title">${unit.title}</h3>` +
					`<p class="sm-card__address">${unit.address || ''}</p>` +
					distance +
					'</div>' +
					'<div class="sm-card__actions">' +
					`<button type="button" class="sm-card__cta" data-unit="${unit.id}" data-unit-name="${unit.title}">${data.strings.plans_label}</button>` +
					whatsapp +
					'</div>' +
					'</div>' +
					'</article>'
				);
			})
			.join('');
	}

	function showStatus(statusEl, message, isError = false) {
		if (!statusEl) {
			return;
		}
		if (!message) {
			statusEl.textContent = '';
			statusEl.classList.remove('is-error', 'is-visible');
			return;
		}
		statusEl.textContent = message;
		statusEl.classList.add('is-visible');
		statusEl.classList.toggle('is-error', !!isError);
	}

	function openModal(modal) {
		if (modal) {
			modal.removeAttribute('hidden');
			modal.classList.add('is-visible');
		}
	}

	function closeModal(modal) {
		if (modal) {
			modal.classList.remove('is-visible');
			modal.setAttribute('hidden', 'hidden');
		}
	}

	function formToJSON(form) {
		const formData = new FormData(form);
		const payload = {};
		formData.forEach((value, key) => {
			payload[key] = value;
		});
		return payload;
	}

	function populateUtms(form) {
		const params = new URLSearchParams(window.location.search);
		const utmFields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
		utmFields.forEach((field) => {
			const input = form.querySelector(`input[name="${field}"]`);
			if (input && !input.value) {
				input.value = params.get(field) || '';
			}
		});
		const origem = form.querySelector('input[name="origem"]');
		if (origem && !origem.value) {
			origem.value = window.location.pathname.replace(/^\//, '') || 'site';
		}
	}

	function setupPhoneMask(input) {
		if (!input) {
			return;
		}
		input.addEventListener('input', () => {
			let digits = input.value.replace(/\D/g, '').slice(0, 11);
			if (digits.length >= 2) {
				digits = `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
			}
			if (digits.length >= 10) {
				const index = digits.length - 4;
				digits = `${digits.slice(0, index)}-${digits.slice(index)}`;
			}
			input.value = digits;
		});
	}
})();

