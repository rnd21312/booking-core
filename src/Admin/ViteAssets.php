<?php
/**
 * Enqueues the wp-admin React apps (built by frontend/vite.admin.config.ts into dist/).
 * Dev mode: define( 'STZ_VITE_DEV', true ) loads them from the Vite dev server (default http://localhost:5174).
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Admin;

defined( 'ABSPATH' ) || exit;

final class ViteAssets {

	/** Entry name → source path inside frontend/. */
	private const ENTRIES = array(
		'tour-editor' => 'src/admin/tour-editor/main.tsx',
		'bookings'    => 'src/admin/bookings/main.tsx',
		'settings'    => 'src/admin/settings/main.tsx',
	);

	private static function is_dev(): bool {
		return defined( 'STZ_VITE_DEV' ) && STZ_VITE_DEV;
	}

	private static function dev_url(): string {
		return rtrim( defined( 'STZ_VITE_ADMIN_URL' ) ? (string) STZ_VITE_ADMIN_URL : 'http://localhost:5174', '/' );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function manifest(): array {
		static $manifest = null;

		if ( null === $manifest ) {
			$manifest = array();
			$file     = STZ_CORE_DIR . 'dist/.vite/manifest.json';

			if ( is_readable( $file ) ) {
				$decoded = json_decode( (string) file_get_contents( $file ), true );
				if ( is_array( $decoded ) ) {
					$manifest = $decoded;
				}
			}
		}

		return $manifest;
	}

	/**
	 * CSS files of a chunk and of everything it statically imports.
	 *
	 * @param array<string, array<string, mixed>> $manifest Vite manifest.
	 * @param array<string, bool>                 $seen     Visited chunks.
	 * @return string[]
	 */
	private static function collect_css( array $manifest, string $key, array &$seen = array() ): array {
		if ( isset( $seen[ $key ] ) || ! isset( $manifest[ $key ] ) ) {
			return array();
		}
		$seen[ $key ] = true;

		$css = (array) ( $manifest[ $key ]['css'] ?? array() );
		foreach ( (array) ( $manifest[ $key ]['imports'] ?? array() ) as $import ) {
			$css = array_merge( $css, self::collect_css( $manifest, (string) $import, $seen ) );
		}

		return $css;
	}

	/**
	 * Enqueues one admin app. Call from an admin_enqueue_scripts callback.
	 */
	public static function enqueue( string $entry ): void {
		$source = self::ENTRIES[ $entry ] ?? null;
		if ( null === $source ) {
			return;
		}

		if ( self::is_dev() ) {
			add_action(
				'admin_print_footer_scripts',
				static function () use ( $source ): void {
					$url = self::dev_url();
					?>
<script type="module">
import RefreshRuntime from "<?php echo esc_url( $url ); ?>/@react-refresh";
RefreshRuntime.injectIntoGlobalHook(window);
window.$RefreshReg$ = () => {};
window.$RefreshSig$ = () => (type) => type;
window.__vite_plugin_react_preamble_installed__ = true;
</script>
<script type="module" src="<?php echo esc_url( $url . '/@vite/client' ); ?>"></script>
<script type="module" src="<?php echo esc_url( $url . '/' . $source ); ?>"></script>
					<?php
				},
				1
			);
			return;
		}

		$manifest = self::manifest();
		$chunk    = $manifest[ $source ] ?? null;
		if ( null === $chunk ) {
			add_action(
				'admin_notices',
				static function (): void {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__( 'Suntourz admin assets are missing. Run "npm run build" in the frontend folder.', 'suntourz' )
					);
				}
			);
			return;
		}

		// Shared stylesheets live on imported chunks, not on the entry itself.
		foreach ( array_values( array_unique( self::collect_css( $manifest, $source ) ) ) as $i => $css ) {
			wp_enqueue_style( "stz-admin-{$entry}-{$i}", STZ_CORE_URL . 'dist/' . $css, array(), STZ_CORE_VERSION );
		}

		// No ?ver= on modules: chunks import each other by bare URL (see theme's inc/vite.php).
		wp_enqueue_script_module( "stz-admin-{$entry}", STZ_CORE_URL . 'dist/' . $chunk['file'], array(), null );
	}
}
