(function (wp) {
	if (!wp || !wp.blocks) {
		return;
	}

	const { registerBlockType } = wp.blocks;
	const { __ } = wp.i18n;
	const { Fragment } = wp.element;
	const { InspectorControls, ServerSideRender } = wp.blockEditor || wp.editor;
	const { PanelBody, SelectControl, ToggleControl, TextControl } = wp.components;

	registerBlockType('soumais/localizador', {
		edit: (props) => {
			const { attributes, setAttributes } = props;

			return wp.element.createElement(
				Fragment,
				null,
				wp.element.createElement(
					InspectorControls,
					null,
					wp.element.createElement(
						PanelBody,
						{ title: __('Configurações', 'soumais-localizador'), initialOpen: true },
						wp.element.createElement(SelectControl, {
							label: __('Layout', 'soumais-localizador'),
							value: attributes.layout || 'full',
							options: [
								{ label: __('Completo', 'soumais-localizador'), value: 'full' },
								{ label: __('Compacto', 'soumais-localizador'), value: 'compact' },
							],
							onChange: (value) => setAttributes({ layout: value }),
						}),
						wp.element.createElement(TextControl, {
							label: __('Raio padrão (km)', 'soumais-localizador'),
							type: 'number',
							value: attributes.radius || 10,
							onChange: (value) => setAttributes({ radius: parseInt(value, 10) || 10 }),
							min: 1,
							max: 50,
						}),
						wp.element.createElement(ToggleControl, {
							label: __('Exibir botão WhatsApp', 'soumais-localizador'),
							checked: !!attributes.show_whatsapp,
							onChange: (value) => setAttributes({ show_whatsapp: value }),
						}),
						wp.element.createElement(TextControl, {
							label: __('URL de redirecionamento', 'soumais-localizador'),
							value: attributes.redirect || '',
							onChange: (value) => setAttributes({ redirect: value }),
							placeholder: 'https://academiasoumais.com.br/',
						})
					)
				),
				wp.element.createElement(ServerSideRender, {
					block: 'soumais/localizador',
					attributes,
				})
			);
		},
		save: () => null,
	});
})(window.wp);
