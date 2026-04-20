<?php

namespace PwlIntegracionFintoc\Integration\Lite;

use PwlIntegracionFintoc\Core\DistributionUrls;
use UserDOMP\WpAdminDS\Components;

defined('ABSPATH') || exit;

/**
 * Lite-only: upgrade card on settings page. Loaded only in Lite artifact / dev Lite.
 */
final class LiteAdminBootstrap
{
	public static function register_hooks(): void
	{
		add_action('pwlintegracionfintoc_settings_after_form', [self::class, 'render_upgrade_card']);
	}

	public static function render_upgrade_card(): void
	{
		$url        = DistributionUrls::pro_product_url();
		$author_url = DistributionUrls::author_url();

		/**
		 * Fires before the Lite settings-page upsell card (Pro product / store).
		 *
		 * @param string $pro_product_url Pro product page URL (plans, trial).
		 * @param string $author_url      Vendor / author site URL.
		 */
		do_action('pwlintegracionfintoc_lite_settings_pro_upsell_before', $url, $author_url);

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

		$list .= '<p class="wads-mt-md" style="margin-bottom:0;font-size:13px;">'
			. '<a href="' . esc_url($author_url) . '" target="_blank" rel="noopener noreferrer">'
			. esc_html__('Plugin Wordpress LATAM — more WooCommerce integrations', 'pwl-integracion-fintoc')
			. '</a></p>';

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- WpAdminDS Components::* return escaped HTML.
		echo Components::card(
			[
				'title'    => __('PWL Fintoc Pro', 'pwl-integracion-fintoc'),
				'subtitle' => __('Available in the Pro edition.', 'pwl-integracion-fintoc'),
				'variant'  => 'accent',
				'body'     => $list,
				'footer'   => Components::button(
					__('View Pro plans', 'pwl-integracion-fintoc'),
					'primary',
					['href' => $url, 'attrs' => ['target' => '_blank', 'rel' => 'noopener noreferrer']],
				),
			],
		);
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		/**
		 * Fires after the Lite settings-page upsell card.
		 *
		 * @param string $pro_product_url Pro product page URL.
		 * @param string $author_url      Vendor / author site URL.
		 */
		do_action('pwlintegracionfintoc_lite_settings_pro_upsell_after', $url, $author_url);
	}
}
