/**
 * WooCommerce Blocks — Fintoc payment method (redirect).
 * Source file: copied to assets/blocks/ after `vite build` (see scripts/copy-blocks-asset.mjs + build.js).
 * Mirrors core gateways (e.g. BACS) using wcSettings.getPaymentMethodData.
 */
( function () {
	const registry = window.wc && window.wc.wcBlocksRegistry;
	const settingsApi = window.wc && window.wc.wcSettings;
	const i18n = window.wp && window.wp.i18n;
	const htmlEntities = window.wp && window.wp.htmlEntities;
	const sanitize = window.wc && window.wc.sanitize;
	const element = window.wp && window.wp.element;

	if (
		!registry ||
		!settingsApi ||
		!i18n ||
		!htmlEntities ||
		!sanitize ||
		!element
	) {
		return;
	}

	const { registerPaymentMethod } = registry;
	const { getPaymentMethodData } = settingsApi;
	const { __ } = i18n;
	const { decodeEntities } = htmlEntities;
	const { sanitizeHTML } = sanitize;
	const { createElement, RawHTML } = element;

	const settings = getPaymentMethodData( 'pwl_fintoc', {} );
	const defaultTitle = __(
		'Pay with Fintoc',
		'pwl-integracion-fintoc'
	);
	const labelText =
		decodeEntities( settings.title || '' ) || defaultTitle;

	function Content() {
		return createElement( RawHTML, {
			children: sanitizeHTML( settings.description || '' ),
		} );
	}

	function Label( props ) {
		const PaymentMethodLabel = props.components.PaymentMethodLabel;
		return createElement( PaymentMethodLabel, { text: labelText } );
	}

	registerPaymentMethod( {
		name: 'pwl_fintoc',
		label: createElement( Label ),
		content: createElement( Content ),
		edit: createElement( Content ),
		canMakePayment: () => true,
		ariaLabel: labelText,
		supports: {
			features: settings.supports || [],
		},
	} );
} )();
