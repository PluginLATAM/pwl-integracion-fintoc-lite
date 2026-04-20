<?php

namespace PwlIntegracionFintoc\Admin;

use PwlIntegracionFintoc\Api\Client;
use PwlIntegracionFintoc\Core\DistributionUrls;
use PwlIntegracionFintoc\Settings\Options;
use UserDOMP\WpAdminDS\Components;

defined('ABSPATH') || exit;

/**
 * Admin “home” screen: KPIs, optional Fintoc API snippet, Lite upsell / Pro highlights.
 */
final class Dashboard
{
	private const TRANSIENT_ORDER_STATS = 'pwl_fintoc_dash_order_stats';

	private const TRANSIENT_PI_PREFIX = 'pwl_fintoc_dash_pi_';

	private const CACHE_TTL = 600;

	private const WEBHOOK_PREVIEW_ROWS = 6;

	public static function clear_caches(): void
	{
		delete_transient(self::TRANSIENT_ORDER_STATS);
		delete_transient(self::TRANSIENT_PI_PREFIX . 'test');
		delete_transient(self::TRANSIENT_PI_PREFIX . 'live');
	}

	public static function render(): void
	{
		if (! Admin::can_configure_fintoc()) {
			wp_die(esc_html__('You do not have permission to access this page.', 'pwl-integracion-fintoc'));
		}

		$is_pro = defined('PWL_FINTOC_EDITION') && 'pro' === PWL_FINTOC_EDITION;
		$pro_licensed = $is_pro
			&& class_exists(\PwlIntegracionFintoc\Integration\Pro\ProFeatures::class)
			&& \PwlIntegracionFintoc\Integration\Pro\ProFeatures::is_pro_license_active();

		$ph_opts = apply_filters(
			'pwlintegracionfintoc_dashboard_header_options',
			[
				'desc' => __(
					'Payments via Fintoc — quick stats, recent account activity, and shortcuts.',
					'pwl-integracion-fintoc',
				),
			],
		);

		echo '<div class="wrap pwl-fintoc-dashboard">';
		echo '<div class="wads">';

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Components::page_header(__('PWL Fintoc', 'pwl-integracion-fintoc'), $ph_opts);
		// phpcs:enable

		echo '<div class="pwl-fintoc-main-stack">';
		echo '<div class="pwl-fintoc-dashboard-inner">';

		if (! class_exists('WooCommerce')) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Components::notice(
				esc_html__('WooCommerce is inactive. Install and activate it to take payments with Fintoc.', 'pwl-integracion-fintoc'),
				'warning',
			);
			// phpcs:enable
		}

		self::render_kpi_strip();

		if ($is_pro && ! $pro_licensed && class_exists(\PwlIntegracionFintoc\Integration\Pro\LicenseClient::class)) {
			self::render_pro_license_notice();
		}

		if ($is_pro && $pro_licensed) {
			self::render_pro_toolbar();
		}

		$grid_class = 'pwl-fintoc-dash-activity-grid' . ($is_pro && $pro_licensed ? ' pwl-fintoc-dash-activity-grid--split' : '');
		echo '<div class="' . esc_attr($grid_class) . '">';
		self::render_fintoc_api_panel();
		if ($is_pro && $pro_licensed) {
			self::render_webhook_panel();
		}
		echo '</div>';

		if (! $is_pro) {
			self::render_lite_pro_banner();
		}

		$settings_url = admin_url('admin.php?page=pwl-fintoc-settings');
		echo '<p class="pwl-fintoc-dash-footnote">';
		echo wp_kses(
			sprintf(
				/* translators: %s: anchor to plugin settings */
				__('Keys, recipient account, and webhooks: %s', 'pwl-integracion-fintoc'),
				'<a href="' . esc_url($settings_url) . '">' . esc_html__('Open Settings', 'pwl-integracion-fintoc') . '</a>',
			),
			[
				'a' => [
					'href' => [],
				],
			],
		);
		echo '</p>';

		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Four summary tiles: paid 7d / 30d, API mode, gateway on/off.
	 */
	private static function render_kpi_strip(): void
	{
		$n7  = '—';
		$n30 = '—';
		if (function_exists('wc_get_orders')) {
			$stats = get_transient(self::TRANSIENT_ORDER_STATS);
			if (! is_array($stats) || ! isset($stats['7d'], $stats['30d'])) {
				$stats = [
					'7d'  => self::count_paid_fintoc_orders_since(7),
					'30d' => self::count_paid_fintoc_orders_since(30),
				];
				set_transient(self::TRANSIENT_ORDER_STATS, $stats, self::CACHE_TTL);
			}
			$n7  = (string) (int) $stats['7d'];
			$n30 = (string) (int) $stats['30d'];
		}

		$o        = Options::get_all();
		$is_test  = ($o['testmode'] ?? 'yes') === 'yes';
		$mode_lbl = $is_test
			? __('Test', 'pwl-integracion-fintoc')
			: __('Live', 'pwl-integracion-fintoc');

		$gw_on   = self::is_gateway_enabled();
		$gw_lbl  = $gw_on
			? __('On', 'pwl-integracion-fintoc')
			: __('Off', 'pwl-integracion-fintoc');
		$gw_hint = $gw_on
			? __('Gateway enabled in WooCommerce → Payments.', 'pwl-integracion-fintoc')
			: __('Enable Fintoc under WooCommerce → Payments.', 'pwl-integracion-fintoc');

		echo '<div class="pwl-fintoc-dash-kpis" role="group" aria-label="' . esc_attr__('Summary metrics', 'pwl-integracion-fintoc') . '">';
		self::kpi_tile(__('Paid orders · 7 days', 'pwl-integracion-fintoc'), $n7, __('Processing or completed, Fintoc gateway.', 'pwl-integracion-fintoc'));
		self::kpi_tile(__('Paid orders · 30 days', 'pwl-integracion-fintoc'), $n30, __('Processing or completed, Fintoc gateway.', 'pwl-integracion-fintoc'));
		self::kpi_tile(__('API mode', 'pwl-integracion-fintoc'), $mode_lbl, __('Matches the secret key used for API calls.', 'pwl-integracion-fintoc'));
		self::kpi_tile(__('Fintoc at checkout', 'pwl-integracion-fintoc'), $gw_lbl, $gw_hint);
		echo '</div>';
	}

	/**
	 * @param string $label Short label (sentence case).
	 * @param string $value Main value.
	 * @param string $hint  Optional screen-reader / title context.
	 */
	private static function kpi_tile(string $label, string $value, string $hint = ''): void
	{
		$title = $hint !== '' ? ' title="' . esc_attr($hint) . '"' : '';
		echo '<div class="pwl-fintoc-dash-kpi"' . $title . '>';
		echo '<div class="pwl-fintoc-dash-kpi__label">' . esc_html($label) . '</div>';
		echo '<div class="pwl-fintoc-dash-kpi__value">' . esc_html($value) . '</div>';
		echo '</div>';
	}

	private static function is_gateway_enabled(): bool
	{
		$gw_opts = get_option('woocommerce_pwl_fintoc_settings', []);

		return is_array($gw_opts) && (($gw_opts['enabled'] ?? 'no') === 'yes');
	}

	private static function render_pro_license_notice(): void
	{
		$url = admin_url('admin.php?page=' . \PwlIntegracionFintoc\Integration\Pro\LicenseClient::license_admin_page_slug());

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Components::notice(
			wp_kses(
				sprintf(
					/* translators: %s: link to the license screen */
					__('Activate your Pro license to enable webhooks, refunds from WooCommerce, and the event log. %s', 'pwl-integracion-fintoc'),
					'<a href="' . esc_url($url) . '">' . esc_html__('Open License', 'pwl-integracion-fintoc') . '</a>',
				),
				[
					'a' => [
						'href' => [],
					],
				],
			),
			'warning',
		);
		// phpcs:enable
	}

	private static function render_pro_toolbar(): void
	{
		$settings_url = admin_url('admin.php?page=pwl-fintoc-settings');
		$log_url        = admin_url('admin.php?page=pwl-fintoc-webhook-log');

		echo '<div class="pwl-fintoc-dash-pro-toolbar">';
		echo '<span class="pwl-fintoc-dash-pro-toolbar__badge">' . esc_html__('Pro', 'pwl-integracion-fintoc') . '</span>';
		echo '<span class="pwl-fintoc-dash-pro-toolbar__text">' . esc_html__('Webhooks, refunds, and event log are active.', 'pwl-integracion-fintoc') . '</span>';
		echo '<span class="pwl-fintoc-dash-pro-toolbar__links">';
		echo '<a href="' . esc_url($settings_url) . '">' . esc_html__('Webhook settings', 'pwl-integracion-fintoc') . '</a>';
		echo '<span class="pwl-fintoc-dash-pro-toolbar__sep" aria-hidden="true">·</span>';
		echo '<a href="' . esc_url($log_url) . '">' . esc_html__('Full webhook log', 'pwl-integracion-fintoc') . '</a>';
		echo '</span>';
		echo '</div>';
	}

	private static function render_lite_pro_banner(): void
	{
		$url        = DistributionUrls::pro_product_url();
		$author_url = DistributionUrls::author_url();

		/**
		 * Fires before the Lite dashboard Pro promo block.
		 *
		 * @param string $pro_product_url Pro product page URL.
		 * @param string $author_url      Vendor / author site URL.
		 */
		do_action('pwlintegracionfintoc_lite_dashboard_pro_banner_before', $url, $author_url);

		$col_a = [
			__('Order updates from Fintoc when the customer does not return to your site', 'pwl-integracion-fintoc'),
			__('Signed webhooks and a debug event log', 'pwl-integracion-fintoc'),
			__('Refunds from WooCommerce admin', 'pwl-integracion-fintoc'),
		];
		$col_b = [
			__('Clearer setup guidance in settings', 'pwl-integracion-fintoc'),
			__('Async payment states and richer order metadata', 'pwl-integracion-fintoc'),
			__('Operational visibility for your team', 'pwl-integracion-fintoc'),
		];

		$mk_col = static function (array $items): string {
			$html = '<ul class="pwl-fintoc-dash-pro-cta__list">';
			foreach ($items as $t) {
				$html .= '<li><span class="pwl-fintoc-dash-pro-cta__check" aria-hidden="true">✓</span> ' . esc_html($t) . '</li>';
			}
			$html .= '</ul>';

			return $html;
		};

		echo '<section class="pwl-fintoc-dash-pro-cta" aria-labelledby="pwl-fintoc-dash-pro-cta-title">';
		echo '<div class="pwl-fintoc-dash-pro-cta__head">';
		echo '<span class="pwl-fintoc-dash-pro-cta__badge">' . esc_html__('Pro', 'pwl-integracion-fintoc') . '</span>';
		echo '<h2 id="pwl-fintoc-dash-pro-cta-title" class="pwl-fintoc-dash-pro-cta__title">'
			. esc_html__('Get more from Fintoc with Pro', 'pwl-integracion-fintoc')
			. '</h2>';
		echo '</div>';
		echo '<div class="pwl-fintoc-dash-pro-cta__cols">';
		echo $mk_col($col_a); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html inside $mk_col.
		echo $mk_col($col_b);
		echo '</div>';
		echo '<div class="pwl-fintoc-dash-pro-cta__footer">';
		echo '<p class="pwl-fintoc-dash-pro-cta__note">'
			. esc_html__('Plans and trials open in a new tab.', 'pwl-integracion-fintoc')
			. '</p>';
		echo '<p class="pwl-fintoc-dash-pro-cta__actions">';
		echo '<a class="button button-primary pwl-fintoc-dash-pro-cta__btn" href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">'
			. esc_html__('View Pro plans', 'pwl-integracion-fintoc')
			. '</a>';
		echo '<a class="button button-secondary pwl-fintoc-dash-pro-cta__btn pwl-fintoc-dash-pro-cta__btn--secondary" href="' . esc_url($author_url) . '" target="_blank" rel="noopener noreferrer">'
			. esc_html__('Plugin Wordpress LATAM', 'pwl-integracion-fintoc')
			. '</a>';
		echo '</p>';
		echo '</div>';
		echo '</section>';

		/**
		 * Fires after the Lite dashboard Pro promo block.
		 *
		 * @param string $pro_product_url Pro product page URL.
		 * @param string $author_url      Vendor / author site URL.
		 */
		do_action('pwlintegracionfintoc_lite_dashboard_pro_banner_after', $url, $author_url);
	}

	private static function count_paid_fintoc_orders_since(int $days): int
	{
		$days = max(1, $days);
		$now  = time();
		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$start = strtotime('-' . $days . ' days', $now);

		$ids = wc_get_orders(
			[
				'limit'          => -1,
				'return'         => 'ids',
				'status'         => ['completed', 'processing'],
				'payment_method' => 'pwl_fintoc',
				'date_created'   => $start . '...' . $now,
			],
		);

		return is_array($ids) ? count($ids) : 0;
	}

	/**
	 * Panel: recent payment intents (Fintoc API).
	 */
	private static function render_fintoc_api_panel(): void
	{
		$settings_url = admin_url('admin.php?page=pwl-fintoc-settings');

		echo '<section class="pwl-fintoc-dash-panel" aria-labelledby="pwl-fintoc-api-panel-title">';
		echo '<div class="pwl-fintoc-dash-panel__head">';
		echo '<h2 id="pwl-fintoc-api-panel-title" class="pwl-fintoc-dash-panel__title">'
			. esc_html__('Recent payment intents', 'pwl-integracion-fintoc')
			. '</h2>';
		echo '<a class="pwl-fintoc-dash-panel__link" href="' . esc_url($settings_url) . '">'
			. esc_html__('API keys in Settings →', 'pwl-integracion-fintoc')
			. '</a>';
		echo '</div>';
		echo '<div class="pwl-fintoc-dash-panel__body">';

		if (Options::get_secret_key() === '') {
			echo '<div class="pwl-fintoc-dash-empty">';
			echo '<p class="pwl-fintoc-dash-empty__title">' . esc_html__('No API key yet', 'pwl-integracion-fintoc') . '</p>';
			echo '<p class="pwl-fintoc-dash-empty__desc">'
				. esc_html__('Add a secret key to list recent payment intents from your Fintoc account.', 'pwl-integracion-fintoc')
				. '</p>';
			echo '</div>';
			echo '</div></section>';

			return;
		}

		$data = self::get_cached_payment_intents();
		$items = $data['items'];
		$error = $data['error'];

		if ($error !== '') {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Components::notice(esc_html($error), 'warning');
			// phpcs:enable
		}

		if ($items === [] && $error === '') {
			echo '<div class="pwl-fintoc-dash-empty">';
			echo '<p class="pwl-fintoc-dash-empty__title">' . esc_html__('No intents in this window', 'pwl-integracion-fintoc') . '</p>';
			echo '<p class="pwl-fintoc-dash-empty__desc">'
				. esc_html__('Nothing returned for the last 14 days, or no activity yet in this environment.', 'pwl-integracion-fintoc')
				. '</p>';
			echo '</div>';
		} elseif ($items !== []) {
			echo '<p class="pwl-fintoc-dash-panel__hint">'
				. esc_html__(
					'Same Fintoc account may show activity outside WooCommerce.',
					'pwl-integracion-fintoc',
				)
				. '</p>';
			echo '<div class="pwl-fintoc-dash-table-wrap">';
			echo '<table class="widefat striped pwl-fintoc-dash-table"><thead><tr>'
				. '<th>' . esc_html__('Status', 'pwl-integracion-fintoc') . '</th>'
				. '<th>' . esc_html__('Amount', 'pwl-integracion-fintoc') . '</th>'
				. '<th>' . esc_html__('ID', 'pwl-integracion-fintoc') . '</th>'
				. '</tr></thead><tbody>';

			foreach ($items as $row) {
				if (! is_array($row)) {
					continue;
				}
				$id     = isset($row['id']) ? (string) $row['id'] : '';
				$status = isset($row['status']) ? (string) $row['status'] : '';
				$amt    = isset($row['amount']) ? $row['amount'] : '';
				$cur    = isset($row['currency']) ? (string) $row['currency'] : '';
				$disp   = $amt !== '' ? (string) $amt : '—';
				if ($cur !== '') {
					$disp .= ' ' . $cur;
				}

				echo '<tr>';
				echo '<td>' . esc_html($status) . '</td>';
				echo '<td>' . esc_html($disp) . '</td>';
				echo '<td><code class="wads-font-mono" style="font-size:12px;">' . esc_html($id) . '</code></td>';
				echo '</tr>';
			}

			echo '</tbody></table></div>';
		}

		echo '</div></section>';
	}

	/**
	 * @return array{items: list<array<string, mixed>>, error: string}
	 */
	private static function get_cached_payment_intents(): array
	{
		$opts     = Options::get_all();
		$mode_key = ($opts['testmode'] ?? 'yes') === 'yes' ? 'test' : 'live';
		$tkey     = self::TRANSIENT_PI_PREFIX . $mode_key;

		$cached = get_transient($tkey);
		if (is_array($cached) && isset($cached['items'], $cached['error'])) {
			return [
				'items' => is_array($cached['items']) ? $cached['items'] : [],
				'error' => is_string($cached['error']) ? $cached['error'] : '',
			];
		}

		$since = gmdate('Y-m-d\TH:i:s\Z', strtotime('-14 days'));
		$res   = Client::list_payment_intents(
			[
				'since'    => $since,
				'per_page' => 10,
				'page'     => 1,
			],
		);

		$error = '';
		$items = [];
		if (! $res['ok']) {
			$error = Client::parse_error_from_response($res);
			if ($error === '') {
				$error = __('Could not load payment intents from Fintoc.', 'pwl-integracion-fintoc');
			}
		} else {
			$items = self::normalize_payment_intent_list($res['data'] ?? null);
		}

		$out = [
			'items' => $items,
			'error' => $error,
		];
		set_transient($tkey, $out, self::CACHE_TTL);

		return $out;
	}

	private static function render_webhook_panel(): void
	{
		if (! class_exists(\PwlIntegracionFintoc\Integration\Pro\EventLog::class)) {
			echo '<section class="pwl-fintoc-dash-panel" aria-labelledby="pwl-fintoc-wh-panel-title">';
			echo '<div class="pwl-fintoc-dash-panel__head">';
			echo '<h2 id="pwl-fintoc-wh-panel-title" class="pwl-fintoc-dash-panel__title">'
				. esc_html__('Webhook events', 'pwl-integracion-fintoc')
				. '</h2>';
			echo '</div><div class="pwl-fintoc-dash-panel__body">';
			echo '<p class="wads-text-sm">' . esc_html__('Log unavailable.', 'pwl-integracion-fintoc') . '</p>';
			echo '</div></section>';

			return;
		}

		$rows    = \PwlIntegracionFintoc\Integration\Pro\EventLog::recent(self::WEBHOOK_PREVIEW_ROWS);
		$log_url = admin_url('admin.php?page=pwl-fintoc-webhook-log');

		echo '<section class="pwl-fintoc-dash-panel" aria-labelledby="pwl-fintoc-wh-panel-title">';
		echo '<div class="pwl-fintoc-dash-panel__head">';
		echo '<h2 id="pwl-fintoc-wh-panel-title" class="pwl-fintoc-dash-panel__title">'
			. esc_html__('Webhook events', 'pwl-integracion-fintoc')
			. '</h2>';
		echo '<a class="pwl-fintoc-dash-panel__link" href="' . esc_url($log_url) . '">'
			. esc_html__('View full log →', 'pwl-integracion-fintoc')
			. '</a>';
		echo '</div>';
		echo '<div class="pwl-fintoc-dash-panel__body">';

		if ($rows === []) {
			echo '<div class="pwl-fintoc-dash-empty">';
			echo '<p class="pwl-fintoc-dash-empty__title">' . esc_html__('No events yet', 'pwl-integracion-fintoc') . '</p>';
			echo '<p class="pwl-fintoc-dash-empty__desc">'
				. esc_html__('When Fintoc posts to your endpoint, verified events appear here.', 'pwl-integracion-fintoc')
				. '</p>';
			echo '</div>';
		} else {
			echo '<div class="pwl-fintoc-dash-table-wrap">';
			echo '<table class="widefat striped pwl-fintoc-dash-table"><thead><tr>'
				. '<th>' . esc_html__('Time', 'pwl-integracion-fintoc') . '</th>'
				. '<th>' . esc_html__('Type', 'pwl-integracion-fintoc') . '</th>'
				. '<th>' . esc_html__('Event ID', 'pwl-integracion-fintoc') . '</th>'
				. '</tr></thead><tbody>';

			foreach ($rows as $r) {
				echo '<tr>';
				echo '<td>' . esc_html((string) ($r['created_at'] ?? '')) . '</td>';
				echo '<td>' . esc_html((string) ($r['event_type'] ?? '')) . '</td>';
				echo '<td><code class="wads-font-mono" style="font-size:12px;">' . esc_html((string) ($r['event_id'] ?? '')) . '</code></td>';
				echo '</tr>';
			}

			echo '</tbody></table></div>';
		}

		echo '</div></section>';
	}

	/**
	 * @param mixed $data Decoded JSON from GET /payment_intents
	 * @return list<array<string, mixed>>
	 */
	private static function normalize_payment_intent_list($data): array
	{
		if (! is_array($data)) {
			return [];
		}
		if (isset($data['data']) && is_array($data['data'])) {
			$out = [];
			foreach ($data['data'] as $row) {
				if (is_array($row)) {
					$out[] = $row;
				}
			}

			return $out;
		}
		$out = [];
		foreach ($data as $row) {
			if (is_array($row) && isset($row['id'])) {
				$out[] = $row;
			}
		}

		return $out;
	}
}
