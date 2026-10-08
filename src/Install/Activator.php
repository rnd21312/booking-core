<?php
/**
 * Activation: tables, role/cap, rewrite flush.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Install;

defined( 'ABSPATH' ) || exit;

final class Activator {

	public const FLUSH_OPTION = 'stz_flush_rewrite';

	public static function activate(): void {
		Schema::install();
		Roles::add();

		// Post types register on `init`; flush after they exist (see Plugin::boot()).
		update_option( self::FLUSH_OPTION, 1, false );
	}

	/**
	 * Flushes rewrite rules once, after the post types are registered.
	 */
	public static function maybe_flush_rewrite(): void {
		if ( get_option( self::FLUSH_OPTION ) ) {
			delete_option( self::FLUSH_OPTION );
			flush_rewrite_rules( false );
		}
	}
}
