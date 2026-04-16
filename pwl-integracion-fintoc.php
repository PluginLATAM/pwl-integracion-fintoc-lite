<?php
/**
 * Plugin Name:       PWL Integración Fintoc
 * Plugin URI:        https://github.com/PluginLATAM/pwl-integracion-fintoc-lite
 * Description:       Fintoc integration for WooCommerce payments and reconciliation.
 * Version:           1.0.2
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

/*
 * If Lite + Pro are both active, two main files run. Do not use class_exists(…, false): Core\Plugin
 * is only autoloaded later, so the second copy would miss the duplicate and would redefine constants.
 * Rely on PWL_FINTOC_VERSION (defined before autoload in the first loaded copy).
 */
if (defined('PWL_FINTOC_VERSION')) {
	add_action(
		'admin_notices',
		static function (): void {
			if (!current_user_can('activate_plugins') && !current_user_can('manage_woocommerce')) {
				return;
			}
			echo '<div class="notice notice-warning"><p>'
				. esc_html__('Another edition of PWL Integración Fintoc is already running. Deactivate the duplicate under Plugins.', 'pwl-integracion-fintoc')
				. '</p></div>';
		}
	);
	return;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- PWL_FINTOC_* is the project-wide constant prefix.
define('PWL_FINTOC_VERSION', '1.0.2');
define('PWL_FINTOC_EDITION', 'lite'); // injected by build.js
define('PWL_FINTOC_FILE', __FILE__);
define('PWL_FINTOC_DIR', plugin_dir_path(__FILE__));
define('PWL_FINTOC_URL', plugin_dir_url(__FILE__));
define('PWL_FINTOC_PRO_URL', 'https://github.com/PluginLATAM');
define('PWL_FINTOC_GATEWAY_CLASS', \PwlIntegracionFintoc\Integration\Gateway\Gateway_Fintoc::class);
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound

require_once PWL_FINTOC_DIR . 'vendor/autoload.php';



add_action(
	'plugins_loaded',
	[\PwlIntegracionFintoc\Integration\Lite\LiteAdminBootstrap::class, 'register_hooks'],
	9
);

/*PWL_FINTOC_REGISTER_ACTIVATION*/
register_activation_hook(__FILE__, ['PwlIntegracionFintoc\Core\Activator', 'activate']);
/*END PWL_FINTOC_REGISTER_ACTIVATION*/

register_deactivation_hook(__FILE__, ['PwlIntegracionFintoc\Core\Deactivator', 'deactivate']);

add_action(
	'plugins_loaded',
	static function (): void {
		PwlIntegracionFintoc\Core\Plugin::run();
	},
	10
);
