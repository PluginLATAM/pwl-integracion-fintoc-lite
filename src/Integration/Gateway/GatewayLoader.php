<?php

namespace PwlIntegracionFintoc\Integration\Gateway;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined('ABSPATH') || exit;

final class GatewayLoader
{
	public function register_hooks(): void
	{
		add_filter('woocommerce_payment_gateways', [$this, 'register_gateway']);
		add_action('woocommerce_blocks_payment_method_type_registration', [$this, 'register_checkout_blocks']);
	}

	public function register_checkout_blocks($payment_method_registry): void
	{
		if (!class_exists(AbstractPaymentMethodType::class)) {
			return;
		}

		$payment_method_registry->register(new FintocCheckoutBlocks());
	}

	/**
	 * @param array<int, string> $methods
	 * @return array<int, string>
	 */
	public function register_gateway(array $methods): array
	{
		$gateway = PWL_FINTOC_GATEWAY_CLASS;
		if (
			PWL_FINTOC_EDITION === 'pro'
			&& class_exists(\PwlIntegracionFintoc\Integration\Pro\ProFeatures::class)
			&& ! \PwlIntegracionFintoc\Integration\Pro\ProFeatures::is_pro_license_active()
		) {
			$gateway = \PwlIntegracionFintoc\Integration\Gateway\Gateway_Fintoc::class;
		}

		$methods[] = $gateway;

		return $methods;
	}
}
