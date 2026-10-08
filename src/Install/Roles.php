<?php
/**
 * Custom capability and role.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Install;

defined( 'ABSPATH' ) || exit;

final class Roles {

	public const CAP_MANAGE_BOOKINGS  = 'stz_manage_bookings';
	public const ROLE_BOOKING_MANAGER = 'stz_booking_manager';

	/**
	 * Capabilities of the trip-request post type (capability_type stz_trip_request / stz_trip_requests).
	 *
	 * @return string[]
	 */
	public static function trip_request_caps(): array {
		return array(
			'edit_stz_trip_requests',
			'edit_others_stz_trip_requests',
			'publish_stz_trip_requests',
			'read_private_stz_trip_requests',
			'delete_stz_trip_requests',
			'delete_private_stz_trip_requests',
			'delete_published_stz_trip_requests',
			'delete_others_stz_trip_requests',
			'edit_private_stz_trip_requests',
			'edit_published_stz_trip_requests',
		);
	}

	/**
	 * @return string[] Every capability the booking-related screens need.
	 */
	public static function all_caps(): array {
		return array_merge( array( self::CAP_MANAGE_BOOKINGS ), self::trip_request_caps() );
	}

	/**
	 * Idempotent: safe to call on every activation/upgrade.
	 */
	public static function add(): void {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::all_caps() as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		$manager = get_role( self::ROLE_BOOKING_MANAGER );
		if ( ! $manager ) {
			// Role names are stored in the DB; WP translates them via translate_user_role().
			$manager = add_role( self::ROLE_BOOKING_MANAGER, 'Booking manager', array( 'read' => true ) );
		}
		if ( $manager ) {
			foreach ( self::all_caps() as $cap ) {
				$manager->add_cap( $cap );
			}
		}
	}

	/**
	 * Whether the administrator role is missing any of our capabilities (used to self-heal).
	 */
	public static function needs_repair(): bool {
		$admin = get_role( 'administrator' );

		return ! get_role( self::ROLE_BOOKING_MANAGER ) || ( $admin && ! $admin->has_cap( 'edit_stz_trip_requests' ) );
	}

	public static function remove(): void {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::all_caps() as $cap ) {
				$admin->remove_cap( $cap );
			}
		}

		remove_role( self::ROLE_BOOKING_MANAGER );
	}
}
