<?php
/**
 * Plugin Name:       PWL Integración Fintoc
 * Plugin URI:        https://github.com/PluginLATAM/pwl-integracion-fintoc-lite
 * Description:       Fintoc integration for WooCommerce payments and reconciliation.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Tested up to:      6.9
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
 * Author:            PluginLATAM
 * Author URI:        https://github.com/PluginLATAM
 * Text Domain:       pwl-integracion-fintoc
 * Domain Path:       /languages
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 */

defined('ABSPATH') || exit;

if (in_array('pwl-integracion-fintoc-pro/pwl-integracion-fintoc-pro.php', (array) get_option('active_plugins', []), true)) {
	return;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- PWL_FINTOC_* is the project-wide constant prefix.
define('PWL_FINTOC_VERSION', '1.0.0');
define('PWL_FINTOC_EDITION', 'lite'); // injected by build.js
define('PWL_FINTOC_FILE', __FILE__);
define('PWL_FINTOC_DIR', plugin_dir_path(__FILE__));
define('PWL_FINTOC_URL', plugin_dir_url(__FILE__));
define('PWL_FINTOC_PRO_URL', 'https://github.com/PluginLATAM');
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound

if (PWL_FINTOC_EDITION === 'pro') {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- scoped to this file; name is plugin-prefixed.
	$pwl_fintoc_lite_active = in_array(
		'pwl-integracion-fintoc/pwl-integracion-fintoc.php',
		(array) get_option('active_plugins', []),
		true,
	);
	if ($pwl_fintoc_lite_active) {
		add_action('admin_notices', static function () {
			echo '<div class="notice notice-error"><p>'
				. esc_html__('PWL Fintoc Pro cannot run while Lite is active. Deactivate Lite and keep only one edition.', 'pwl-integracion-fintoc')
				. '</p></div>';
		});
		return;
	}
}

require_once PWL_FINTOC_DIR . 'vendor/autoload.php';

register_activation_hook(__FILE__, ['PwlIntegracionFintoc\Core\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['PwlIntegracionFintoc\Core\Deactivator', 'deactivate']);

add_action(
	'plugins_loaded',
	static function (): void {
		PwlIntegracionFintoc\Core\Plugin::run();
	},
	10
);
