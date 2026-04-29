<?php

namespace S4D\Thawani;

defined( 'ABSPATH' ) || exit;

final class Installer {

	private const DB_VERSION_OPTION = 'wc_thawani_db_version';
	private const DB_VERSION        = '1.0.0';

	public static function activate(): void {
		self::create_tables();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	public static function maybe_upgrade(): void {
		if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}
		self::create_tables();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->base_prefix . 'thawani_invoice_map';
	}

	private static function create_tables(): void {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		// Schema kept identical to the legacy plugin so existing rows remain queryable.
		// dbDelta() is avoided because it misparses the reserved-word column `key` as an
		// index declaration and emits warnings during activation.
		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			tiid int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
			invoiceid int(11) NOT NULL DEFAULT 0,
			thawani_invoiceid int(11) NOT NULL DEFAULT 0,
			thawani_payment_id varchar(255) DEFAULT NULL,
			thawani_amount double(8,2) NOT NULL DEFAULT '0.00',
			`key` varchar(255) NOT NULL,
			is_active int(11) NOT NULL DEFAULT 1,
			created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (tiid)
		) {$charset};";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $sql );
	}
}
