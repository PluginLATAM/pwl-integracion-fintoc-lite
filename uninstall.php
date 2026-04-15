<?php
/**
 * Uninstall — PWL Integración Fintoc
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('pwl_fintoc_settings');
delete_option('pwl_fintoc_db_version');

global $wpdb;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- local name; value is $wpdb->prefix + fixed plugin suffix.
$table = $wpdb->prefix . 'pwl_fintoc_events';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- uninstall only; $table is prefix + literal suffix pwl_fintoc_events (no user input).
$wpdb->query("DROP TABLE IF EXISTS `{$table}`");
