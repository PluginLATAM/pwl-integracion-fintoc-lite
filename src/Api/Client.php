<?php

namespace PwlIntegracionFintoc\Api;

use PwlIntegracionFintoc\Settings\Options;

defined('ABSPATH') || exit;

/**
 * Fintoc REST client using wp_remote_request.
 *
 * Checkout sessions use API v2 per Fintoc docs; other routes stay on v1 unless Fintoc moves them.
 *
 * @link https://docs.fintoc.com/reference/authentication
 * @link https://docs.fintoc.com/docs/quickstart-payments
 */
final class Client
{
	private const API_V1 = 'https://api.fintoc.com/v1';

	private const API_V2 = 'https://api.fintoc.com/v2';

	/**
	 * @param array<string, mixed> $body
	 * @return array{ok: bool, code: int, data: mixed, error?: string}
	 */
	public static function post(string $path, array $body, string $base = self::API_V1): array
	{
		return self::request('POST', $path, $body, $base);
	}

	/**
	 * @return array{ok: bool, code: int, data: mixed, error?: string}
	 */
	public static function get(string $path, string $base = self::API_V1): array
	{
		return self::request('GET', $path, [], $base);
	}

	/**
	 * @param array<string, mixed> $body
	 * @return array{ok: bool, code: int, data: mixed, error?: string}
	 */
	private static function request(string $method, string $path, array $body, string $base): array
	{
		$key = Options::get_secret_key();
		if ($key === '') {
			return [
				'ok'    => false,
				'code'  => 0,
				'data'  => null,
				'error' => 'missing_api_key',
			];
		}

		// Secret Key only — never the Public Key (pk_). See Fintoc Authentication docs.
		$url = $base . $path;
		$args = [
			'method'      => $method,
			'timeout'     => 60,
			'redirection' => 0,
			'sslverify'   => true,
			'headers'     => [
				'Authorization' => $key,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			],
		];

		if ($method === 'POST') {
			$args['body'] = wp_json_encode($body);
		}

		$response = wp_remote_request($url, $args);

		if (is_wp_error($response)) {
			return [
				'ok'    => false,
				'code'  => 0,
				'data'  => null,
				'error' => $response->get_error_message(),
			];
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$raw  = (string) wp_remote_retrieve_body($response);
		$data = json_decode($raw, true);

		return [
			'ok'   => $code >= 200 && $code < 300,
			'code' => $code,
			'data' => is_array($data) ? $data : $raw,
		];
	}

	/**
	 * Human-readable message from a failed Fintoc JSON body (shape varies by endpoint).
	 *
	 * @param mixed $data Decoded JSON or raw string
	 */
	public static function parse_error_message($data): string
	{
		if (is_string($data) && $data !== '') {
			return $data;
		}
		if (!is_array($data)) {
			return '';
		}
		if (!empty($data['error_message']) && is_string($data['error_message'])) {
			return $data['error_message'];
		}
		if (!empty($data['message']) && is_string($data['message'])) {
			return $data['message'];
		}
		if (isset($data['error']) && is_string($data['error'])) {
			return $data['error'];
		}
		if (isset($data['error']) && is_array($data['error'])) {
			$e = $data['error'];
			if (!empty($e['message']) && is_string($e['message'])) {
				return $e['message'];
			}
		}

		return '';
	}

	/**
	 * @param array{ok: bool, code: int, data: mixed, error?: string} $response
	 */
	public static function parse_error_from_response(array $response): string
	{
		if (!empty($response['error']) && is_string($response['error']) && $response['error'] !== 'missing_api_key') {
			return $response['error'];
		}
		if (empty($response['ok'])) {
			return self::parse_error_message($response['data'] ?? null);
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $payload Checkout session create payload
	 * @return array{ok: bool, code: int, data: mixed, error?: string}
	 */
	public static function create_checkout_session(array $payload): array
	{
		return self::post('/checkout_sessions', $payload, self::API_V2);
	}

	/**
	 * @return array{ok: bool, code: int, data: mixed, error?: string}
	 */
	public static function retrieve_checkout_session(string $session_id): array
	{
		return self::get('/checkout_sessions/' . rawurlencode($session_id), self::API_V2);
	}

	/**
	 * @return array{ok: bool, code: int, data: mixed, error?: string}
	 */
	public static function create_refund(string $payment_intent_id, ?int $amount = null): array
	{
		$body = [
			'resource_id'   => $payment_intent_id,
			'resource_type' => 'payment_intent',
		];
		if (null !== $amount && $amount > 0) {
			$body['amount'] = $amount;
		}

		return self::post('/refunds', $body, self::API_V1);
	}
}
