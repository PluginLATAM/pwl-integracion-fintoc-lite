<?php

namespace PwlIntegracionFintoc\Integration\Lite;

use UserDOMP\WpAdminDS\Components;

defined('ABSPATH') || exit;

/**
 * Lite-only: upgrade card on settings page. Loaded only in Lite artifact / dev Lite.
 */
final class LiteAdminBootstrap
{
	public static function register_hooks(): void
	{
		add_action('pwl_fintoc_settings_after_form', [self::class, 'render_upgrade_card']);
	}

	public static function render_upgrade_card(): void
	{
		$url      = defined('PWL_FINTOC_PRO_URL') ? PWL_FINTOC_PRO_URL : 'https://github.com/PluginLATAM';
		$features = [
			__('Order updates when Fintoc sends payment events (no thank-you page required)', 'pwl-integracion-fintoc'),
			__('Signed webhooks, debug event log, and clearer setup guidance in settings', 'pwl-integracion-fintoc'),
			__('Refunds from WooCommerce admin', 'pwl-integracion-fintoc'),
			__('Async handling of pending payments', 'pwl-integracion-fintoc'),
		];
		$list = '<ul class="wads-stack wads-stack--sm" style="margin:0;padding-left:1.25em;list-style:disc;">';
		foreach ($features as $f) {
			$list .= '<li>' . esc_html($f) . '</li>';
		}
		$list .= '</ul>';

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- WpAdminDS Components::* return escaped HTML.
		echo Components::card(
			[
				'title'    => __('PWL Fintoc Pro', 'pwl-integracion-fintoc'),
				'subtitle' => __('Available in the Pro edition.', 'pwl-integracion-fintoc'),
				'variant'  => 'accent',
				'body'     => $list,
				'footer'   => Components::button(
					__('Learn about Pro', 'pwl-integracion-fintoc'),
					'primary',
					['href' => $url, 'attrs' => ['target' => '_blank', 'rel' => 'noopener noreferrer']],
				),
			],
		);
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
