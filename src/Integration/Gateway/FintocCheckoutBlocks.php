<?php

namespace PwlIntegracionFintoc\Integration\Gateway;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined('ABSPATH') || exit;

/**
 * WooCommerce Cart / Checkout block integration for {@see Gateway_Fintoc}.
 */
final class FintocCheckoutBlocks extends AbstractPaymentMethodType
{
	protected $name = Gateway_Fintoc::ID;

	public function initialize(): void
	{
		$this->settings = get_option('woocommerce_' . Gateway_Fintoc::ID . '_settings', []);
	}

	public function is_active(): bool
	{
		// Match core Blocks gateways (e.g. BACS): enqueue when enabled. Availability for
		// placing the order is enforced by WC()->payment_gateways->get_available_payment_gateways().
		return filter_var($this->get_setting('enabled', false), FILTER_VALIDATE_BOOLEAN);
	}

	public function get_payment_method_script_handles(): array
	{
		wp_register_script(
			'wc-payment-method-pwl-fintoc',
			PWL_FINTOC_URL . 'assets/blocks/wc-payment-method-pwl-fintoc.js',
			[
				'wc-blocks-registry',
				'wc-sanitize',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
				'wp-polyfill',
			],
			PWL_FINTOC_VERSION,
			true
		);

		if (function_exists('wp_set_script_translations')) {
			wp_set_script_translations('wc-payment-method-pwl-fintoc', 'pwl-integracion-fintoc', PWL_FINTOC_DIR . 'languages');
		}

		return ['wc-payment-method-pwl-fintoc'];
	}

	public function get_payment_method_data(): array
	{
		if (!$this->is_active()) {
			return [];
		}

		return [
			'title'       => $this->get_setting('title'),
			'description' => $this->get_setting('description'),
			'supports'    => $this->get_supported_features(),
		];
	}
}
