<?php
/**
 * Database tables and versioned migrations.
 *
 * New columns/tables are added only by bumping VERSION and extending install() (dbDelta is
 * idempotent). Never rename existing tables or columns.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Install;

defined( 'ABSPATH' ) || exit;

final class Schema {

	public const VERSION        = 1;
	public const VERSION_OPTION = 'stz_db_version';

	public static function departures(): string {
		global $wpdb;

		return $wpdb->prefix . 'stz_departures';
	}

	public static function bookings(): string {
		global $wpdb;

		return $wpdb->prefix . 'stz_bookings';
	}

	public static function booking_log(): string {
		global $wpdb;

		return $wpdb->prefix . 'stz_booking_log';
	}

	/**
	 * Runs install() when the stored schema version is behind.
	 */
	public static function maybe_upgrade(): void {
		if ( (int) get_option( self::VERSION_OPTION, 0 ) < self::VERSION ) {
			self::install();
		}
	}

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$dep     = self::departures();
		$book    = self::bookings();
		$log     = self::booking_log();

		dbDelta(
			"CREATE TABLE {$dep} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  tour_id BIGINT(20) UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  capacity INT(11) UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('draft','open','closed','cancelled') NOT NULL DEFAULT 'draft',
  price_overrides LONGTEXT NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  KEY tour_start (tour_id,start_date)
) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$book} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(16) NOT NULL,
  tour_id BIGINT(20) UNSIGNED NOT NULL,
  departure_id BIGINT(20) UNSIGNED NOT NULL,
  plan_id VARCHAR(40) NOT NULL,
  pax SMALLINT(5) UNSIGNED NOT NULL DEFAULT 1,
  extras LONGTEXT NULL,
  total_amount BIGINT(20) NOT NULL DEFAULT 0,
  currency VARCHAR(8) NOT NULL DEFAULT 'THB',
  customer_name VARCHAR(190) NOT NULL,
  customer_email VARCHAR(190) NOT NULL,
  customer_phone VARCHAR(40) NOT NULL,
  contact_channel ENUM('phone','whatsapp','line','email') NOT NULL DEFAULT 'phone',
  contact_handle VARCHAR(190) NULL,
  message TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  admin_note TEXT NULL,
  amount_paid BIGINT(20) NOT NULL DEFAULT 0,
  payment_method VARCHAR(40) NULL,
  locale VARCHAR(20) NULL,
  ip_hash CHAR(64) NULL,
  user_id BIGINT(20) UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY code (code),
  KEY status (status),
  KEY departure_id (departure_id),
  KEY tour_id (tour_id),
  KEY created_at (created_at)
) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$log} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id BIGINT(20) UNSIGNED NOT NULL,
  from_status VARCHAR(20) NULL,
  to_status VARCHAR(20) NOT NULL,
  user_id BIGINT(20) UNSIGNED NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  KEY booking_id (booking_id)
) {$charset};"
		);

		update_option( self::VERSION_OPTION, self::VERSION, true );
	}
}
