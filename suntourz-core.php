<?php
/**
 * Plugin Name:       Suntourz Core
 * Plugin URI:        https://github.com/rnd21312/suntourz-core
 * Description:       Tours, departures, booking requests, REST API and admin screens for the Suntourz theme.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            theodore-sooske (Telegram)
 * Author URI:        https://t.me/theodore-sooske
 * License:           GPL-2.0-or-later
 * Text Domain:       suntourz
 * Domain Path:       /languages
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'STZ_CORE_VERSION', '0.1.0' );
define( 'STZ_CORE_FILE', __FILE__ );
define( 'STZ_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'STZ_CORE_URL', plugin_dir_url( __FILE__ ) );

// Zero-dependency PSR-4 autoloader so the zip works without Composer.
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'Suntourz\\Core\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) );
		$file     = STZ_CORE_DIR . 'src/' . $relative . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( \Suntourz\Core\Install\Activator::class, 'activate' ) );
add_action( 'plugins_loaded', array( \Suntourz\Core\Plugin::class, 'boot' ) );
