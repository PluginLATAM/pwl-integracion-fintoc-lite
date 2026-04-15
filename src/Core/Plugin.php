<?php

namespace PwlIntegracionFintoc\Core;

defined('ABSPATH') || exit;

/** Main plugin loader. */
final class Plugin
{
	private static ?self $instance = null;

	public static function run(): void
	{
		if (null !== self::$instance) {
			return;
		}
		self::$instance = new self();
		self::$instance->init();
	}

	private function init(): void
	{
		Activator::maybe_upgrade();

		add_filter('load_textdomain_mofile', [$this, 'fallback_spanish_mofile'], 10, 2);
		add_action('init', [$this, 'load_textdomain_on_init'], 0);

		// Settings screen always loads so API keys and recipient data can be saved even if WooCommerce is not active yet.
		$this->boot(\PwlIntegracionFintoc\Admin\Admin::class);

		if (!class_exists('WooCommerce')) {
			add_action('admin_notices', [$this, 'wc_missing_notice']);

			return;
		}

		foreach ([
			\PwlIntegracionFintoc\Integration\CheckoutReturn::class,
			\PwlIntegracionFintoc\Integration\Gateway\GatewayLoader::class,
		] as $class) {
			$this->boot($class);
		}
	}

	public function load_textdomain_on_init(): void
	{
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- needed for local installs and load_textdomain_mofile fallback; .org also loads, explicit call is harmless.
		load_plugin_textdomain('pwl-integracion-fintoc', false, dirname(plugin_basename(PWL_FINTOC_FILE)) . '/languages');
	}

	public function wc_missing_notice(): void
	{
		if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
			return;
		}
		echo '<div class="notice notice-error"><p>'
			. esc_html__('Activate WooCommerce to use the Fintoc payment method.', 'pwl-integracion-fintoc')
			. '</p></div>';
	}

	private function boot(string $class): void
	{
		if (!class_exists($class)) {
			return;
		}
		$instance = new $class();
		if (method_exists($instance, 'register_hooks')) {
			$instance->register_hooks();
		}
	}

	/**
	 * If WordPress locale is Spanish (e.g. es_ES) but no matching .mo exists, use bundled es_CL catalog.
	 */
	public function fallback_spanish_mofile($mofile, string $domain): mixed
	{
		if ($domain !== 'pwl-integracion-fintoc') {
			return $mofile;
		}
		$locale = determine_locale();
		if (!str_starts_with($locale, 'es')) {
			return $mofile;
		}
		if (file_exists($mofile)) {
			return $mofile;
		}

		$base = trailingslashit(PWL_FINTOC_DIR) . 'languages';
		$cl   = trailingslashit($base) . 'pwl-integracion-fintoc-es_CL.mo';
		if (file_exists($cl)) {
			return $cl;
		}

		return $mofile;
	}
}
