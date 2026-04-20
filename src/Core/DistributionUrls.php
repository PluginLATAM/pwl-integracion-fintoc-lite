<?php

namespace PwlIntegracionFintoc\Core;

defined('ABSPATH') || exit;

/**
 * Public URLs for the vendor site and Pro product page (Lite upsell, dashboard CTAs).
 * Values come from plugin headers / constants; filters allow overrides.
 */
final class DistributionUrls
{
	private const DEFAULT_PRO_PRODUCT = 'https://pluginwordpresslatam.cl/producto/integracion-plugin-fintoc-woocommerce-pro/';

	private const DEFAULT_AUTHOR = 'https://pluginwordpresslatam.cl/';

	public static function pro_product_url(): string
	{
		$base = defined('PWL_FINTOC_PRO_URL') ? PWL_FINTOC_PRO_URL : self::DEFAULT_PRO_PRODUCT;

		return (string) apply_filters('pwlintegracionfintoc_pro_product_url', $base);
	}

	public static function author_url(): string
	{
		$base = defined('PWL_FINTOC_AUTHOR_URL') ? PWL_FINTOC_AUTHOR_URL : self::DEFAULT_AUTHOR;

		return (string) apply_filters('pwlintegracionfintoc_author_url', $base);
	}
}
