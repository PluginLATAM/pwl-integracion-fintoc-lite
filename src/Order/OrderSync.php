<?php

namespace PwlIntegracionFintoc\Order;

defined('ABSPATH') || exit;

/**
 * Maps Fintoc Checkout Session / Payment Intent state to WooCommerce order status.
 */
final class OrderSync
{
	/**
	 * @param array<string, mixed> $session Decoded checkout_session object from API
	 */
	public static function apply_session_to_order(\WC_Order $order, array $session): void
	{
		$session_id = isset($session['id']) ? (string) $session['id'] : '';
		if ($session_id !== '') {
			$order->update_meta_data('_pwl_fintoc_checkout_session_id', $session_id);
		}

		$intent = null;
		if (isset($session['payment_resource']['payment_intent']) && is_array($session['payment_resource']['payment_intent'])) {
			$intent = $session['payment_resource']['payment_intent'];
		}

		$pi_id = is_array($intent) && isset($intent['id']) ? (string) $intent['id'] : '';
		if ($pi_id !== '') {
			$order->update_meta_data('_pwl_fintoc_payment_intent_id', $pi_id);
		}

		$session_status = isset($session['status']) ? (string) $session['status'] : '';
		$pi_status      = is_array($intent) && isset($intent['status']) ? (string) $intent['status'] : '';

		if ($pi_status === 'succeeded') {
			if (!$order->is_paid()) {
				$order->payment_complete($pi_id !== '' ? $pi_id : $session_id);
				$order->add_order_note(
					sprintf(
						/* translators: %s: payment intent id */
						__('Fintoc: payment succeeded (payment_intent %s).', 'pwl-integracion-fintoc'),
						$pi_id !== '' ? $pi_id : '—',
					),
				);
			}
			$order->save();
			self::fire_after_session_applied($order, $session);

			return;
		}

		if (
			$pi_status === 'failed'
			|| $session_status === 'expired'
			|| $pi_status === 'rejected'
			|| $pi_status === 'expired'
		) {
			$order->update_status(
				'failed',
				__('Fintoc: payment failed or session expired.', 'pwl-integracion-fintoc'),
			);
			self::fire_after_session_applied($order, $session);

			return;
		}

		if ($pi_status === 'requires_action' || $session_status === 'pending' || $pi_status === 'pending') {
			if (!in_array($order->get_status(), ['processing', 'completed'], true)) {
				$order->update_status(
					'on-hold',
					__('Fintoc: payment pending approval or further action.', 'pwl-integracion-fintoc'),
				);
			}
			self::fire_after_session_applied($order, $session);

			return;
		}

		if ($session_status === 'finished' && $pi_status !== '') {
			$order->add_order_note(
				sprintf(
					/* translators: %s: payment intent status */
					__('Fintoc: session finished with payment status %s.', 'pwl-integracion-fintoc'),
					$pi_status,
				),
			);
			$order->save();
		}

		self::fire_after_session_applied($order, $session);
	}

	/**
	 * Fires after session/intent state is merged into the order. Pro listeners persist a rich snapshot; Lite has none.
	 *
	 * @param array<string, mixed> $session Checkout session or wrapper from apply_payment_intent_event.
	 */
	private static function fire_after_session_applied(\WC_Order $order, array $session): void
	{
		$intent = null;
		if (isset($session['payment_resource']['payment_intent']) && is_array($session['payment_resource']['payment_intent'])) {
			$intent = $session['payment_resource']['payment_intent'];
		}

		/**
		 * After Fintoc checkout session state is applied to the order.
		 *
		 * @param \WC_Order $order WooCommerce order.
		 * @param array<string, mixed> $session Session array (possibly synthetic).
		 * @param array<string, mixed>|null $intent Payment intent if present.
		 */
		do_action('pwlintegracionfintoc_after_session_applied', $order, $session, $intent);
	}

	/**
	 * @param array<string, mixed> $data Event data object (checkout_session payload)
	 */
	public static function apply_checkout_session_data(\WC_Order $order, array $data): void
	{
		self::apply_session_to_order($order, $data);
	}

	/**
	 * @param array<string, mixed> $intent payment_intent object
	 */
	public static function apply_payment_intent_event(\WC_Order $order, array $intent): void
	{
		$wrapped = [
			'id'               => $order->get_meta('_pwl_fintoc_checkout_session_id'),
			'status'           => 'finished',
			'payment_resource' => [
				'payment_intent' => $intent,
			],
		];

		self::apply_session_to_order($order, $wrapped);
	}

	/**
	 * Resolve order from checkout session metadata set at creation time.
	 *
	 * @param array<string, mixed> $session
	 */
	public static function find_order_from_session(array $session): ?\WC_Order
	{
		return self::find_order_from_metadata_field($session['metadata'] ?? null);
	}

	/**
	 * @param mixed $metadata metadata on session or payment_intent
	 */
	public static function find_order_from_metadata_field($metadata): ?\WC_Order
	{
		$meta = $metadata;
		if (is_string($meta)) {
			$decoded = json_decode($meta, true);
			$meta    = is_array($decoded) ? $decoded : [];
		}
		if (!is_array($meta)) {
			$meta = [];
		}

		$order_id = isset($meta['woo_order_id']) ? absint($meta['woo_order_id']) : 0;
		$key      = isset($meta['order_key']) ? (string) $meta['order_key'] : '';

		if ($order_id <= 0) {
			return null;
		}

		if ($key === '') {
			return null;
		}

		$order = wc_get_order($order_id);
		if (!$order) {
			return null;
		}

		if (!hash_equals($order->get_order_key(), $key)) {
			return null;
		}

		return $order;
	}
}
