<?php
/**
 * Unit-test bootstrap. Domain classes are pure PHP, so no WordPress is loaded.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'STZ_CORE_DIR', dirname( __DIR__ ) . '/' );

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'Suntourz\\Core\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$file = STZ_CORE_DIR . 'src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
