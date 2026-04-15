<?php

namespace PwlIntegracionFintoc\Integration\Gateway;

defined('ABSPATH') || exit;

/**
 * Lite gateway: products only (no refunds UI).
 */
final class Gateway_Fintoc extends AbstractGateway_Fintoc
{
	/**
	 * @return array<int, string>
	 */
	protected function define_supports(): array
	{
		return ['products'];
	}
}
