<?php

namespace PwlIntegracionFintoc\Admin;

use PwlIntegracionFintoc\Settings\Options;
use UserDOMP\WpAdminDS\Components;
use UserDOMP\WpAdminDS\DesignSystem;

defined('ABSPATH') || exit;

final class Admin
{
	public function register_hooks(): void
	{
		add_action('admin_menu', [$this, 'add_menu_pages']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
		add_action('admin_init', [$this, 'maybe_save_settings']);
		add_filter('plugin_action_links_' . plugin_basename(PWL_FINTOC_FILE), [$this, 'action_links']);
	}

	public function action_links(array $links): array
	{
		$url = admin_url('admin.php?page=pwl-integracion-fintoc');
		array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'pwl-integracion-fintoc') . '</a>');

		return $links;
	}

	/**
	 * Who may open settings and save (avoid options.php which requires manage_options only).
	 */
	public static function can_configure_fintoc(): bool
	{
		if (current_user_can('manage_options')) {
			return true;
		}

		return class_exists('WooCommerce') && current_user_can('manage_woocommerce');
	}

	/**
	 * Save handler — works for administrators (manage_options) and WooCommerce shop managers (manage_woocommerce).
	 */
	public function maybe_save_settings(): void
	{
		if (!isset($_POST['pwl_fintoc_save_nonce'], $_POST['option_page']) || $_POST['option_page'] !== 'pwl_fintoc_settings') {
			return;
		}

		if (!wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['pwl_fintoc_save_nonce'])), 'pwl_fintoc_save_settings')) {
			return;
		}

		if (!self::can_configure_fintoc()) {
			return;
		}

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- multi-line read: sanitization is in sanitize_settings() per field.
		$input = isset($_POST[ Options::OPTION_KEY ]) && is_array($_POST[ Options::OPTION_KEY ])
			? wp_unslash($_POST[ Options::OPTION_KEY ])
			: [];
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$clean = $this->sanitize_settings($input);
		update_option(Options::OPTION_KEY, $clean, false);

		$redirect = add_query_arg(
			['page' => 'pwl-integracion-fintoc', 'settings-updated' => 'true'],
			admin_url('admin.php'),
		);
		wp_safe_redirect($redirect);
		exit;
	}

	/**
	 * @param mixed $input
	 * @return array<string, mixed>
	 */
	public function sanitize_settings($input): array
	{
		if (!is_array($input)) {
			return Options::get_all();
		}

		$prev  = Options::get_all();
		$wp_recip = !empty($input['recipient_from_wordpress']) ? 'yes' : 'no';

		$clean = [
			'testmode'                 => !empty($input['testmode']) ? 'yes' : 'no',
			'test_secret_key'          => isset($input['test_secret_key']) ? sanitize_text_field((string) $input['test_secret_key']) : $prev['test_secret_key'],
			'live_secret_key'          => isset($input['live_secret_key']) ? sanitize_text_field((string) $input['live_secret_key']) : $prev['live_secret_key'],
			'recipient_from_wordpress' => $wp_recip,
			'recipient_holder_id'      => $prev['recipient_holder_id'],
			'recipient_number'         => $prev['recipient_number'],
			'recipient_type'           => $prev['recipient_type'],
			'recipient_institution_id' => $prev['recipient_institution_id'],
			'webhook_secret'           => $prev['webhook_secret'],
		];

		if ($wp_recip === 'yes') {
			$clean['recipient_holder_id']      = isset($input['recipient_holder_id']) ? sanitize_text_field((string) $input['recipient_holder_id']) : '';
			$clean['recipient_number']          = isset($input['recipient_number']) ? sanitize_text_field((string) $input['recipient_number']) : '';
			$clean['recipient_type']            = isset($input['recipient_type']) && in_array($input['recipient_type'], ['checking_account', 'sight_account'], true)
				? $input['recipient_type']
				: 'checking_account';
			$clean['recipient_institution_id'] = $this->sanitize_recipient_institution_id($input);
		}

		if (isset($input['test_secret_key']) && (string) $input['test_secret_key'] === '') {
			$clean['test_secret_key'] = $prev['test_secret_key'];
		}
		if (isset($input['live_secret_key']) && (string) $input['live_secret_key'] === '') {
			$clean['live_secret_key'] = $prev['live_secret_key'];
		}

		if (defined('PWL_FINTOC_EDITION') && PWL_FINTOC_EDITION === 'pro') {
			if (isset($input['webhook_secret']) && (string) $input['webhook_secret'] !== '') {
				$clean['webhook_secret'] = sanitize_text_field((string) $input['webhook_secret']);
			}
		}

		return $clean;
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private function sanitize_recipient_institution_id(array $input): string
	{
		$raw = isset($input['recipient_institution_id']) ? sanitize_text_field((string) $input['recipient_institution_id']) : '';
		if ($raw === '') {
			return '';
		}

		$choices = Options::chile_institution_choices();

		return isset($choices[$raw]) ? $raw : '';
	}

	/**
	 * Select options: placeholder, optional legacy unknown ID, then official Fintoc Chile codes.
	 *
	 * @return array<string, string>
	 */
	private function chile_institution_select_choices(string $current): array
	{
		$official = Options::chile_institution_choices();
		$rows     = ['' => __('— Select institution —', 'pwl-integracion-fintoc')];

		if ($current !== '' && !isset($official[$current])) {
			$rows[$current] = sprintf(
				/* translators: %s: institution_id stored in options but not in the current official list */
				__('Unknown value (reselect): %s', 'pwl-integracion-fintoc'),
				$current
			);
		}

		return $rows + $official;
	}

	public function add_menu_pages(): void
	{
		// Match Admin::can_configure_fintoc(): WooCommerce shop managers + admins when WC is active; otherwise site admins only.
		$menu_cap = class_exists('WooCommerce') ? 'manage_woocommerce' : 'manage_options';
		add_menu_page(
			__('PWL Fintoc', 'pwl-integracion-fintoc'),
			__('PWL Fintoc', 'pwl-integracion-fintoc'),
			$menu_cap,
			'pwl-integracion-fintoc',
			[$this, 'render_settings'],
			'dashicons-money',
			58,
		);
	}

	public function enqueue_assets(string $hook): void
	{
		if (!str_contains($hook, 'pwl-integracion-fintoc')) {
			return;
		}

		// Package is `pwl/wp-admin-design-system`; DesignSystem::assets_url() still assumes legacy path.
		$wads_assets = trailingslashit(PWL_FINTOC_URL) . 'vendor/pwl/wp-admin-design-system/assets/';
		DesignSystem::enqueue($wads_assets, PWL_FINTOC_VERSION);

		wp_enqueue_style(
			'pwl-fintoc-admin',
			PWL_FINTOC_URL . 'assets/admin/css/admin.css',
			['wp-admin'],
			PWL_FINTOC_VERSION,
		);

		wp_enqueue_script(
			'pwl-fintoc-admin',
			PWL_FINTOC_URL . 'assets/admin/js/admin.js',
			[],
			PWL_FINTOC_VERSION,
			true
		);
	}

	public function render_settings(): void
	{
		if (!self::can_configure_fintoc()) {
			wp_die(esc_html__('You do not have permission to access this page.', 'pwl-integracion-fintoc'));
		}

		$o   = Options::get_all();
		$opt = Options::OPTION_KEY;

		$ph_opts = [
			'desc' => __('API keys, payout, and Pro webhooks.', 'pwl-integracion-fintoc'),
		];
		if (defined('PWL_FINTOC_EDITION') && PWL_FINTOC_EDITION === 'pro') {
			$ph_opts['badge'] = __('Pro', 'pwl-integracion-fintoc');
		}

		echo '<div class="wrap">';
		echo '<div class="wads">';

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- WpAdminDS Components::* return escaped HTML.
		echo Components::page_header(__('PWL Fintoc integration', 'pwl-integracion-fintoc'), $ph_opts);
		// phpcs:enable

		echo '<div class="pwl-fintoc-main-stack">';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only query flag after redirect from settings save (core pattern).
		if (isset($_GET['settings-updated'])) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Components::notice(
				esc_html__('Settings saved.', 'pwl-integracion-fintoc'),
				'success',
			);
			// phpcs:enable
		}

		if (!class_exists('WooCommerce')) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Components::notice(
				esc_html__('WooCommerce is inactive. Save keys here; enable Fintoc under WooCommerce → Payments when WC is active.', 'pwl-integracion-fintoc'),
				'warning',
			);
			// phpcs:enable
		} else {
			$this->render_wc_checkout_status_panel();
		}

		$save_btn = Components::button(__('Save changes', 'pwl-integracion-fintoc'), 'primary', ['type' => 'submit']);

		$api_rows = [
			[
				'label'   => __('Test mode', 'pwl-integracion-fintoc'),
				'desc'    => __('When on, uses the test secret key.', 'pwl-integracion-fintoc'),
				'control' => Components::toggle(
					$opt . '[testmode]',
					__('Use Fintoc test API keys', 'pwl-integracion-fintoc'),
					[
						'checked' => ($o['testmode'] ?? 'yes') === 'yes',
						'id'      => 'pwl-testmode',
					],
				),
			],
			[
				'label'   => __('Test secret key', 'pwl-integracion-fintoc'),
				'desc'    => sprintf(
					/* translators: %s: Secret key prefix (sk_test_ or sk_live_). */
					__('%s from the Fintoc Dashboard (not the public pk_ key). Empty = keep saved value.', 'pwl-integracion-fintoc'),
					'sk_test_',
				),
				'control' => Components::input(
					$opt . '[test_secret_key]',
					[
						'type'        => 'password',
						'id'          => 'pwl-test-sk',
						'value'       => (string) ($o['test_secret_key'] ?? ''),
						'placeholder' => '',
						'attrs'       => ['autocomplete' => 'off'],
					],
				),
			],
			[
				'label'   => __('Live secret key', 'pwl-integracion-fintoc'),
				'desc'    => sprintf(
					/* translators: %s: Secret key prefix (sk_test_ or sk_live_). */
					__('%s from the Fintoc Dashboard (not the public pk_ key). Empty = keep saved value.', 'pwl-integracion-fintoc'),
					'sk_live_',
				),
				'control' => Components::input(
					$opt . '[live_secret_key]',
					[
						'type'        => 'password',
						'id'          => 'pwl-live-sk',
						'value'       => (string) ($o['live_secret_key'] ?? ''),
						'placeholder' => '',
						'attrs'       => ['autocomplete' => 'off'],
					],
				),
			],
		];

		$recipient_from_wp = ($o['recipient_from_wordpress'] ?? 'no') === 'yes';

		$recipient_toggle_row = [
			[
				'label'   => __('Recipient bank account from WordPress', 'pwl-integracion-fintoc'),
				'desc'    => __('Off: payout account is only in Fintoc (no bank fields). On: enter RUT, account, and bank; they are required and sent with each payment.', 'pwl-integracion-fintoc'),
				'control' => Components::toggle(
					$opt . '[recipient_from_wordpress]',
					__('Send holder ID, account number, and bank from these settings', 'pwl-integracion-fintoc'),
					[
						'checked' => $recipient_from_wp,
						'id'      => 'pwl-recipient-from-wp',
					],
				),
			],
		];

		$recipient_bank_rows = [
			[
				'label'    => __('Holder ID (RUT)', 'pwl-integracion-fintoc'),
				'control'  => Components::input(
					$opt . '[recipient_holder_id]',
					['id' => 'pwl-rut', 'value' => (string) ($o['recipient_holder_id'] ?? '')],
				),
			],
			[
				'label'    => __('Account number', 'pwl-integracion-fintoc'),
				'control'  => Components::input(
					$opt . '[recipient_number]',
					['id' => 'pwl-acc', 'value' => (string) ($o['recipient_number'] ?? '')],
				),
			],
			[
				'label'   => __('Account type', 'pwl-integracion-fintoc'),
				'control' => Components::select(
					$opt . '[recipient_type]',
					[
						'checking_account' => __('Checking account', 'pwl-integracion-fintoc'),
						'sight_account'    => __('Sight account (vista)', 'pwl-integracion-fintoc'),
					],
					[
						'selected' => (string) ($o['recipient_type'] ?? 'checking_account'),
						'id'       => 'pwl-recipient-type',
					],
				),
			],
			[
				'label'   => __('Institution ID', 'pwl-integracion-fintoc'),
				'desc'    => __('Chile institution code (Fintoc docs).', 'pwl-integracion-fintoc'),
				'control' => Components::select(
					$opt . '[recipient_institution_id]',
					$this->chile_institution_select_choices((string) ($o['recipient_institution_id'] ?? '')),
					[
						'selected' => (string) ($o['recipient_institution_id'] ?? ''),
						'id'       => 'pwl-bank',
					],
				),
			],
		];

		$toggle_html = Components::setting_row(
			$recipient_toggle_row[0]['label'],
			$recipient_toggle_row[0]['control'],
			['desc' => $recipient_toggle_row[0]['desc'] ?? ''],
		);
		$bank_inner = '';
		foreach ($recipient_bank_rows as $row) {
			$bank_inner .= Components::setting_row(
				$row['label'] ?? '',
				$row['control'] ?? '',
				['desc' => $row['desc'] ?? ''],
			);
		}
		$recipient_body = $toggle_html
			. '<div id="pwl-recipient-bank-fields"' . ($recipient_from_wp ? '' : ' hidden') . '>'
			. $bank_inner
			. '</div>';

		echo '<form method="post" action="' . esc_url(admin_url('admin.php')) . '">';
		echo '<input type="hidden" name="page" value="pwl-integracion-fintoc" />';
		echo '<input type="hidden" name="option_page" value="pwl_fintoc_settings" />';
		wp_nonce_field('pwl_fintoc_save_settings', 'pwl_fintoc_save_nonce');

		echo '<div class="wads-stack wads-stack--lg">';

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Components::settings_section(
			[
				'title' => __('API keys', 'pwl-integracion-fintoc'),
				'desc'  => __('Server-side secret keys (sk_).', 'pwl-integracion-fintoc'),
				'rows'  => $api_rows,
			],
		);

		echo Components::settings_section(
			[
				'title' => __('Recipient account (Chile direct deposit)', 'pwl-integracion-fintoc'),
				'desc'  => __('Turn on the toggle below only if WordPress must send RUT, account number, and bank to Fintoc.', 'pwl-integracion-fintoc'),
				'body'  => $recipient_body,
			],
		);
		// phpcs:enable

		if (defined('PWL_FINTOC_EDITION') && PWL_FINTOC_EDITION === 'pro') {
			$rest_url = rest_url('pwl-fintoc/v1/webhook');

			$webhook_rows = [
				[
					'label'   => __('Webhook signing secret', 'pwl-integracion-fintoc'),
					'desc'    => __('Dashboard → Webhooks. Empty = keep saved value.', 'pwl-integracion-fintoc'),
					'control' => Components::input(
						$opt . '[webhook_secret]',
						[
							'type'        => 'password',
							'id'          => 'pwl-wh',
							'value'       => (string) ($o['webhook_secret'] ?? ''),
							'placeholder' => '',
							'attrs'       => ['autocomplete' => 'off'],
						],
					),
				],
				[
					'label'   => __('Webhook URL', 'pwl-integracion-fintoc'),
					'desc'    => __('Add in Fintoc with the same test/live mode as your keys.', 'pwl-integracion-fintoc'),
					'control' => '<code class="wads-font-mono" style="font-size:12px;word-break:break-all;">' . esc_html($rest_url) . '</code>',
				],
			];

			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Components::settings_section(
				[
					'title' => __('Webhooks (Pro)', 'pwl-integracion-fintoc'),
					'desc'  => __('Signature check and request log.', 'pwl-integracion-fintoc'),
					'rows'  => $webhook_rows,
				],
			);
			// phpcs:enable
		}

		echo '<div class="wads-cluster" style="margin-top:8px">';
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $save_btn;
		// phpcs:enable
		echo '</div>';

		echo '</div>';
		echo '</form>';

		if (defined('PWL_FINTOC_EDITION') && PWL_FINTOC_EDITION !== 'pro') {
			$this->render_pro_upgrade_card();
		}

		echo '</div>';

		echo '</div>';
		echo '</div>';
	}

	private function render_wc_checkout_status_panel(): void
	{
		$o = Options::get_all();

		$has_key = Options::get_secret_key() !== '';
		$recipient_ok = !Options::sends_recipient_from_wordpress()
			|| (
				$o['recipient_holder_id'] !== ''
				&& $o['recipient_number'] !== ''
				&& $o['recipient_institution_id'] !== ''
			);

		$gw_opts = get_option('woocommerce_pwl_fintoc_settings', []);
		$wc_enabled = is_array($gw_opts) && (($gw_opts['enabled'] ?? 'no') === 'yes');

		$payments_url = admin_url('admin.php?page=wc-settings&tab=checkout');
		$gateway_url  = admin_url('admin.php?page=wc-settings&tab=checkout&section=pwl_fintoc');

		$key_badge = $has_key
			? Components::badge(__('OK', 'pwl-integracion-fintoc'), 'dot-success')
			: Components::badge(__('Missing', 'pwl-integracion-fintoc'), 'dot-danger');
		$recip_badge = $recipient_ok
			? Components::badge(__('OK', 'pwl-integracion-fintoc'), 'dot-success')
			: Components::badge(__('Incomplete', 'pwl-integracion-fintoc'), 'dot-danger');
		$wc_badge = $wc_enabled
			? Components::badge(__('Enabled', 'pwl-integracion-fintoc'), 'dot-success')
			: Components::badge(__('Off', 'pwl-integracion-fintoc'), 'dot-danger');

		$checkout_ready = $has_key && $recipient_ok && $wc_enabled;

		$fintoc_only_payout = !Options::sends_recipient_from_wordpress();

		$list = '<ol class="wads-stack wads-stack--sm" style="margin:0 0 12px;padding-left:1.25em;">';
		$list .= '<li>'
			. esc_html__('Secret key for the active mode (paste once if the field was empty).', 'pwl-integracion-fintoc')
			. ' ' . $key_badge
			. '</li>';
		// When payout is set only in Fintoc, no bank fields in WP — skip this checklist row (nothing to verify here).
		if (!$fintoc_only_payout) {
			$list .= '<li>'
				. esc_html__('Payout: holder ID (RUT), account number, and bank in the recipient section above.', 'pwl-integracion-fintoc')
				. ' ' . $recip_badge
				. '</li>';
		}
		$list .= '<li>'
			. esc_html__('WooCommerce → Payments → Fintoc: enable the gateway.', 'pwl-integracion-fintoc')
			. ' ' . $wc_badge
			. '</li>';
		$list .= '</ol>';

		$list .= '<p class="wads-text-muted" style="margin:0 0 12px;font-size:13px;">'
			. esc_html__('Checkout: CLP and MXN only.', 'pwl-integracion-fintoc')
			. '</p>';

		$actions = '<div class="wads-cluster">';
		$actions .= Components::button(__('All payment methods', 'pwl-integracion-fintoc'), 'secondary', ['href' => $payments_url]);
		$actions .= Components::button(__('Fintoc in WooCommerce', 'pwl-integracion-fintoc'), 'primary', ['href' => $gateway_url]);
		$actions .= '</div>';

		$title = $checkout_ready
			? __('Ready at checkout (check currency)', 'pwl-integracion-fintoc')
			: __('Fintoc missing at checkout?', 'pwl-integracion-fintoc');

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Components::card(
			[
				'title'    => $title,
				'subtitle' => __('Keys here; enable the gateway in WooCommerce.', 'pwl-integracion-fintoc'),
				'variant'  => $checkout_ready ? 'flat' : 'accent',
				'body'     => $list . $actions,
			],
		);
		// phpcs:enable
	}

	private function render_pro_upgrade_card(): void
	{
		$url      = defined('PWL_FINTOC_PRO_URL') ? PWL_FINTOC_PRO_URL : 'https://github.com/PluginLATAM';
		$features = [
			__('Automatic order updates via webhooks', 'pwl-integracion-fintoc'),
			__('Webhook log and signature verification', 'pwl-integracion-fintoc'),
			__('Refunds from WooCommerce admin', 'pwl-integracion-fintoc'),
			__('Async handling of pending payments', 'pwl-integracion-fintoc'),
		];
		$list = '<ul class="wads-stack wads-stack--sm" style="margin:0;padding-left:1.25em;list-style:disc;">';
		foreach ($features as $f) {
			$list .= '<li>' . esc_html($f) . '</li>';
		}
		$list .= '</ul>';

		$body = $list;

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Components::card(
			[
				'title'   => __('PWL Fintoc Pro', 'pwl-integracion-fintoc'),
				'subtitle' => __('Available in the Pro edition.', 'pwl-integracion-fintoc'),
				'variant' => 'accent',
				'body'    => $body,
				'footer'  => Components::button(
					__('Learn about Pro', 'pwl-integracion-fintoc'),
					'primary',
					['href' => $url, 'attrs' => ['target' => '_blank', 'rel' => 'noopener noreferrer']],
				),
			],
		);
		// phpcs:enable
	}
}
