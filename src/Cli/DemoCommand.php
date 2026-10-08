<?php
/**
 * WP-CLI: wp stz demo import | remove
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Cli;

use Suntourz\Core\Services\DemoImporter;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

final class DemoCommand {

	public function __construct( private DemoImporter $demo ) {}

	/**
	 * Imports the demo tours, bookings, pages and menus.
	 *
	 * @param string[] $args       Positional arguments.
	 * @param string[] $assoc_args Named arguments.
	 */
	public function import( array $args = array(), array $assoc_args = array() ): void {
		$result = $this->demo->import();
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( sprintf( 'Imported %d tours, %d bookings, %d posts/pages and %d terms.', $result['tours'], $result['bookings'], $result['posts'], $result['terms'] ) );
	}

	/**
	 * Removes only what the demo import created.
	 *
	 * @param string[] $args       Positional arguments.
	 * @param string[] $assoc_args Named arguments.
	 */
	public function remove( array $args = array(), array $assoc_args = array() ): void {
		$this->demo->remove() ? WP_CLI::success( 'Demo data removed.' ) : WP_CLI::warning( 'There is no demo data to remove.' );
	}
}
