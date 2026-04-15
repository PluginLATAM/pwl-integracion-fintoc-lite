<?php

namespace PwlIntegracionFintoc\Integration;

use PwlIntegracionFintoc\Api\Client;
use PwlIntegracionFintoc\Integration\Gateway\Gateway_Fintoc;
use PwlIntegracionFintoc\Order\OrderSync;

defined('ABSPATH') || exit;

/**
 * Lite: confirm payment on order-received via GET /checkout_sessions/{id}.
 */
final class CheckoutReturn
{
	public function register_hooks(): void
	{
		add_action('template_redirect', [$this, 'clear_stale_notices_before_thankyou'], 1);
		add_action('woocommerce_thankyou', [$this, 'on_thankyou'], 10, 1);
		add_action('template_redirect', [$this, 'on_cancel'], 5);
	}

	/**
	 * Remove session notices (e.g. stale "payment cancelled") before they print on order-received.
	 * WooCommerce keeps notices until displayed; a cancel return can persist until the success page.
	 */
	public function clear_stale_notices_before_thankyou(): void
	{
		if (!function_exists('is_order_received_page') || !is_order_received_page()) {
			return;
		}

		$order_id = absint(get_query_var('order-received', 0));
		if ($order_id <= 0) {
			return;
		}

		$order = wc_get_order($order_id);
		if (!$order || $order->get_payment_method() !== Gateway_Fintoc::ID) {
			return;
		}

		wc_clear_notices();
	}

	/**
	 * Refresh session state from Fintoc when the customer lands on the thank-you page.
	 */
	public function on_thankyou($order_id): void
	{
		$order_id = absint($order_id);
		if ($order_id <= 0) {
			return;
		}

		$order = wc_get_order($order_id);
		if (!$order || $order->get_payment_method() !== Gateway_Fintoc::ID) {
			return;
		}

		$sid = (string) $order->get_meta('_pwl_fintoc_checkout_session_id');
		if ($sid === '') {
			return;
		}

		$res = Client::retrieve_checkout_session($sid);
		if (!$res['ok'] || !is_array($res['data'])) {
			$order->add_order_note(
				__('Fintoc: could not refresh checkout session status (API error).', 'pwl-integracion-fintoc'),
			);
			$order->save();

			return;
		}

		OrderSync::apply_session_to_order($order, $res['data']);
	}

	/**
	 * User returned via cancel_url from Fintoc checkout.
	 */
	public function on_cancel(): void
	{
		if (!function_exists('is_checkout') || !is_checkout()) {
			return;
		}

		// Never treat order-received (thank-you) as a cancel return.
		if (function_exists('is_order_received_page') && is_order_received_page()) {
			return;
		}

		if (empty($_GET['pwl_fintoc_cancel']) || $_GET['pwl_fintoc_cancel'] !== '1') {
			return;
		}

		$order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
		$key      = isset($_GET['key']) ? wc_clean(wp_unslash((string) $_GET['key'])) : '';

		if ($order_id <= 0 || $key === '') {
			return;
		}

		$order = wc_get_order($order_id);
		if (!$order || !hash_equals($order->get_order_key(), $key)) {
			return;
		}

		if ($order->get_payment_method() !== Gateway_Fintoc::ID) {
			return;
		}

		if ($order->is_paid()) {
			return;
		}

		// Payment may have succeeded at Fintoc while redirect pointed here; sync before showing "cancelled".
		$sid = (string) $order->get_meta('_pwl_fintoc_checkout_session_id');
		if ($sid !== '') {
			$res = Client::retrieve_checkout_session($sid);
			if ($res['ok'] && is_array($res['data'])) {
				OrderSync::apply_session_to_order($order, $res['data']);
				$order = wc_get_order($order_id);
				if ($order && $order->is_paid()) {
					return;
				}
			}
		}

		$order->add_order_note(__('Customer returned from Fintoc without completing payment.', 'pwl-integracion-fintoc'));
		$order->save();

		wc_add_notice(
			__('Payment was cancelled. You can try again or choose another payment method.', 'pwl-integracion-fintoc'),
			'notice',
		);
	}
}
