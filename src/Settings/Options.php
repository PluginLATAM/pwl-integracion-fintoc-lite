<?php

namespace PwlIntegracionFintoc\Settings;

defined('ABSPATH') || exit;

/**
 * Centralized plugin options (API keys, recipient account, webhook secret).
 */
final class Options
{
	public const OPTION_KEY = 'pwl_fintoc_settings';

	/** @return array<string, mixed> */
	public static function get_all(): array
	{
		$defaults = [
			'testmode'                   => 'yes',
			'test_secret_key'            => '',
			'live_secret_key'            => '',
			// yes = send RUT/account/bank from WP; no = payout account only in Fintoc (default).
			'recipient_from_wordpress'   => 'no',
			'recipient_holder_id'        => '',
			'recipient_number'           => '',
			'recipient_type'             => 'checking_account',
			'recipient_institution_id'   => '',
			'webhook_secret'             => '',
		];

		$stored = get_option(self::OPTION_KEY, []);
		if (!is_array($stored)) {
			$stored = [];
		}

		// Legacy key fintoc_collection_preset (inverted semantics). Map once; then drop the old key.
		if (array_key_exists('fintoc_collection_preset', $stored)) {
			if (!array_key_exists('recipient_from_wordpress', $stored)) {
				$stored['recipient_from_wordpress'] = (($stored['fintoc_collection_preset'] ?? 'no') === 'yes') ? 'no' : 'yes';
			}
			unset($stored['fintoc_collection_preset']);
			update_option(self::OPTION_KEY, $stored, false);
		}

		return array_merge($defaults, $stored);
	}

	public static function get_secret_key(): string
	{
		$o = self::get_all();
		$test = ($o['testmode'] ?? 'yes') === 'yes';

		return $test
			? (string) ($o['test_secret_key'] ?? '')
			: (string) ($o['live_secret_key'] ?? '');
	}

	public static function is_test_mode(): bool
	{
		return (self::get_all()['testmode'] ?? 'yes') === 'yes';
	}

	/**
	 * Whether holder ID, account number, and bank from plugin settings are sent on checkout (Chile bank transfer).
	 * If false, the payout account is expected to be configured only in Fintoc (no recipient_account in API).
	 */
	public static function sends_recipient_from_wordpress(): bool
	{
		return (self::get_all()['recipient_from_wordpress'] ?? 'no') === 'yes';
	}

	/**
	 * Chile bank / wallet institution_id values (Fintoc Bank ID).
	 *
	 * @see https://docs.fintoc.com/reference/chile-institution-codes
	 *
	 * @return array<string, string> institution_id => label
	 */
	public static function chile_institution_choices(): array
	{
		return [
			'cl_banco_estado'         => __('Banco Estado', 'pwl-integracion-fintoc'),
			'cl_banco_bci'            => __('Banco BCI', 'pwl-integracion-fintoc'),
			'cl_banco_bice'           => __('Banco BICE', 'pwl-integracion-fintoc'),
			'cl_banco_de_chile'       => __('Banco de Chile - Edwards - Citi', 'pwl-integracion-fintoc'),
			'cl_banco_falabella'      => __('Banco Falabella', 'pwl-integracion-fintoc'),
			'cl_banco_itau'           => __('Banco Itaú', 'pwl-integracion-fintoc'),
			'cl_banco_ripley'         => __('Banco Ripley', 'pwl-integracion-fintoc'),
			'cl_banco_santander'      => __('Banco Santander', 'pwl-integracion-fintoc'),
			'cl_banco_consorcio'      => __('Banco Consorcio', 'pwl-integracion-fintoc'),
			'cl_banco_scotiabank'     => __('Scotiabank', 'pwl-integracion-fintoc'),
			'cl_mercado_pago'         => __('Mercado Pago', 'pwl-integracion-fintoc'),
			'cl_mach'                 => __('Mach', 'pwl-integracion-fintoc'),
			'cl_tenpo'                => __('Tenpo', 'pwl-integracion-fintoc'),
			'cl_banco_security'       => __('Banco Security', 'pwl-integracion-fintoc'),
			'cl_tapp_caja_los_andes'  => __('Tapp', 'pwl-integracion-fintoc'),
			'cl_banco_internacional'  => __('Banco Internacional', 'pwl-integracion-fintoc'),
			'cl_banco_coopeuch'       => __('Coopeuch - Dale', 'pwl-integracion-fintoc'),
			'cl_copec_pay'            => __('Copec Pay', 'pwl-integracion-fintoc'),
			'cl_prepago_los_heroes'   => __('Prepago Los Heroes', 'pwl-integracion-fintoc'),
			'cl_banco_bbva'           => __('BBVA', 'pwl-integracion-fintoc'),
			'cl_banco_hsbc'           => __('HSBC', 'pwl-integracion-fintoc'),
		];
	}

	public static function get_webhook_secret(): string
	{
		if (
			defined('PWL_FINTOC_EDITION') && PWL_FINTOC_EDITION === 'pro'
			&& class_exists(\PwlIntegracionFintoc\Integration\Pro\ProFeatures::class)
			&& ! \PwlIntegracionFintoc\Integration\Pro\ProFeatures::is_pro_license_active()
		) {
			return '';
		}

		return (string) (self::get_all()['webhook_secret'] ?? '');
	}

	/** @param array<string, mixed> $values */
	public static function update(array $values): void
	{
		$current = self::get_all();
		foreach ($values as $k => $v) {
			$current[$k] = $v;
		}
		update_option(self::OPTION_KEY, $current, false);
	}
}
