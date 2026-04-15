<?php

namespace PwlIntegracionFintoc\Integration\Gateway;

use PwlIntegracionFintoc\Api\Client;
use PwlIntegracionFintoc\Order\OrderSync;
use PwlIntegracionFintoc\Settings\Options;

defined('ABSPATH') || exit;

/**
 * Shared WooCommerce gateway: Fintoc-hosted checkout (Lite and Pro extend this).
 */
abstract class AbstractGateway_Fintoc extends \WC_Payment_Gateway
{
	public const ID = 'pwl_fintoc';

	public function __construct()
	{
		$this->id                 = self::ID;
		$this->icon               = '';
		$this->has_fields         = false;
		$this->method_title       = __('Fintoc', 'pwl-integracion-fintoc');
		$this->method_description = __(
			'Hosted checkout on Fintoc (available methods depend on your account).',
			'pwl-integracion-fintoc',
		);
		$this->supports           = $this->define_supports();

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->enabled     = $this->get_option('enabled');

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			[$this, 'process_admin_options'],
		);
	}

	/**
	 * @return array<int, string>
	 */
	abstract protected function define_supports(): array;

	public function init_form_fields(): void
	{
		$this->form_fields = [
			'pwl_api_keys' => [
				'title'       => __('API secret & payout account', 'pwl-integracion-fintoc'),
				'type'        => 'title',
				'description' => sprintf(
					/* translators: %s: URL to plugin settings screen */
					__(
						'Keys and bank details: %s. Here: enable the method and checkout labels only.',
						'pwl-integracion-fintoc',
					),
					'<a href="' . esc_url(admin_url('admin.php?page=pwl-integracion-fintoc')) . '">' . esc_html__('PWL Fintoc integration', 'pwl-integracion-fintoc') . '</a>',
				),
			],
			'enabled'     => [
				'title'   => __('Enable/Disable', 'pwl-integracion-fintoc'),
				'type'    => 'checkbox',
				'label'   => __('Enable Fintoc', 'pwl-integracion-fintoc'),
				'default' => 'no',
			],
			'title'       => [
				'title'       => __('Title', 'pwl-integracion-fintoc'),
				'type'        => 'text',
				'description' => __('Shown at checkout.', 'pwl-integracion-fintoc'),
				'default'     => __('Pay with Fintoc', 'pwl-integracion-fintoc'),
				'desc_tip'    => true,
			],
			'description' => [
				'title'       => __('Description', 'pwl-integracion-fintoc'),
				'type'        => 'textarea',
				'description' => __('Optional text under the title at checkout.', 'pwl-integracion-fintoc'),
				'default'     => '',
			],
		];
	}

	public function is_available(): bool
	{
		if (!parent::is_available()) {
			return false;
		}

		$key = Options::get_secret_key();

		return $key !== '' && $this->recipient_configured();
	}

	private function recipient_configured(): bool
	{
		if (!Options::sends_recipient_from_wordpress()) {
			return true;
		}

		$o = Options::get_all();

		return $o['recipient_holder_id'] !== ''
			&& $o['recipient_number'] !== ''
			&& $o['recipient_institution_id'] !== '';
	}

	/**
	 * WooCommerce Store API (Blocks checkout) merges this with array_merge(); booleans are invalid.
	 *
	 * @return array{result: string, redirect: string}
	 */
	protected function failure_payment_result(): array
	{
		return [
			'result'   => 'failure',
			'redirect' => '',
		];
	}

	/**
	 * Log Fintoc API failure for merchants (WooCommerce → Status → Logs: pwl-fintoc).
	 *
	 * @param array{ok: bool, code: int, data: mixed, error?: string} $response
	 */
	protected function log_checkout_session_failure(string $context, array $response): void
	{
		if (!function_exists('wc_get_logger')) {
			return;
		}

		$detail = Client::parse_error_from_response($response);
		if ($detail === '' && array_key_exists('error', $response) && is_string($response['error']) && $response['error'] !== '') {
			$detail = $response['error'];
		}
		if ($detail === '' && isset($response['data'])) {
			$data = $response['data'];
			$detail = is_scalar($data) ? (string) $data : wp_json_encode($data);
		}

		$code = isset($response['code']) ? (int) $response['code'] : 0;
		wc_get_logger()->error(
			sprintf('[%s] HTTP %d %s', $context, $code, $detail),
			['source' => 'pwl-fintoc'],
		);
	}

	/**
	 * @param int|string $order_id
	 * @return array{result: string, redirect: string}
	 */
	public function process_payment($order_id)
	{
		$order = wc_get_order($order_id);
		if (!$order) {
			return $this->failure_payment_result();
		}

		$currency = strtoupper($order->get_currency());
		if (!in_array($currency, ['CLP', 'MXN'], true)) {
			wc_add_notice(
				__('Fintoc only supports CLP and MXN for this integration.', 'pwl-integracion-fintoc'),
				'error',
			);

			return $this->failure_payment_result();
		}

		$amount = self::order_amount_minor_units($order, $currency);
		if ($amount <= 0) {
			wc_add_notice(__('Invalid order amount.', 'pwl-integracion-fintoc'), 'error');

			return $this->failure_payment_result();
		}

		$order_key = $order->get_order_key();
		$metadata  = [
			'woo_order_id' => (string) $order->get_id(),
			'order_key'    => (string) $order_key,
			'site_url'     => (string) home_url('/'),
		];

		$pm_types = ['bank_transfer'];

		$success_url = $order->get_checkout_order_received_url();

		$cancel_url = add_query_arg(
			[
				'pwl_fintoc_cancel' => '1',
				'order_id'          => $order->get_id(),
				'key'               => $order_key,
			],
			wc_get_checkout_url(),
		);

		$billing_email = $order->get_billing_email();
		$customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
		$customer      = [
			'email' => $billing_email,
			'name'  => $customer_name,
		];

		$payload = [
			'amount'               => $amount,
			'currency'             => $currency,
			'customer_email'       => $billing_email,
			'success_url'          => $success_url,
			'cancel_url'           => $cancel_url,
			'metadata'             => $metadata,
			'customer'             => $customer,
			'payment_method_types' => $pm_types,
		];

		if (Options::sends_recipient_from_wordpress()) {
			$o         = Options::get_all();
			$recipient = [
				'holder_id'      => preg_replace('/\D+/', '', (string) $o['recipient_holder_id']),
				'number'         => preg_replace('/\D+/', '', (string) $o['recipient_number']),
				'type'           => in_array($o['recipient_type'], ['checking_account', 'sight_account'], true)
					? $o['recipient_type']
					: 'checking_account',
				'institution_id' => sanitize_text_field((string) $o['recipient_institution_id']),
			];
			$payload['payment_method_options'] = [
				'bank_transfer' => [
					'recipient_account' => $recipient,
				],
			];
		}

		$response = Client::create_checkout_session($payload);

		if (!$response['ok'] || !is_array($response['data'])) {
			$this->log_checkout_session_failure('create_checkout_session', $response);
			wc_add_notice(
				__('Could not start Fintoc payment. Please try again or choose another payment method.', 'pwl-integracion-fintoc'),
				'error',
			);

			return $this->failure_payment_result();
		}

		$data = $response['data'];
		$sid  = isset($data['id']) ? (string) $data['id'] : '';
		$url  = isset($data['redirect_url']) ? (string) $data['redirect_url'] : '';

		if ($sid === '' || $url === '') {
			$this->log_checkout_session_failure('create_checkout_session_missing_redirect', $response);
			wc_add_notice(
				__('Could not start Fintoc payment. Please try again or choose another payment method.', 'pwl-integracion-fintoc'),
				'error',
			);

			return $this->failure_payment_result();
		}

		$order->update_meta_data('_pwl_fintoc_checkout_session_id', $sid);
		$order->set_transaction_id($sid);
		$order->update_status(
			'pending',
			__('Awaiting Fintoc payment.', 'pwl-integracion-fintoc'),
		);
		$order->save();

		return [
			'result'   => 'success',
			'redirect' => $url,
		];
	}

	/**
	 * Convert WC order total to Fintoc integer amount.
	 */
	public static function order_amount_minor_units(\WC_Order $order, string $currency): int
	{
		return self::minor_from_float((float) $order->get_total(), $currency);
	}

	protected static function minor_from_float(float $amount, string $currency): int
	{
		if ($currency === 'CLP') {
			return (int) round($amount);
		}

		return (int) round($amount * 100);
	}
}
