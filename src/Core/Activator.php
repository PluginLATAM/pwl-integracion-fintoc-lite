<?php

namespace PwlIntegracionFintoc\Core;

defined('ABSPATH') || exit;

class Activator
{
	private const DB_VERSION = '1';

	public static function activate(): void
	{
		self::install_tables();
		update_option('pwl_fintoc_db_version', self::DB_VERSION);
	}

	public static function maybe_upgrade(): void
	{
		$v = get_option('pwl_fintoc_db_version', '');
		if ($v === self::DB_VERSION) {
			return;
		}
		self::install_tables();
		update_option('pwl_fintoc_db_version', self::DB_VERSION);
	}

	private static function install_tables(): void
	{
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = $wpdb->prefix . 'pwl_fintoc_events';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id varchar(191) NOT NULL,
			event_type varchar(191) NOT NULL,
			payload longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_id (event_id)
		) {$charset};";

		dbDelta($sql);
	}
}
