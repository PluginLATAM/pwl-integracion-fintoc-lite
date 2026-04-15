<?php
/**
 * Uninstall — PWL Integración Fintoc
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('pwl_fintoc_settings');
delete_option('pwl_fintoc_db_version');

global $wpdb;
$table = $wpdb->prefix . 'pwl_fintoc_events';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query("DROP TABLE IF EXISTS `{$table}`");
