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
		$methods[] = Gateway_Fintoc::class;

		return $methods;
	}
}
