<?php
/**
 * Mounts the React tour editor inside the stz_tour edit screen (classic editor + one big meta box).
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Admin;

use Suntourz\Core\PostTypes\TourPostType;
use Suntourz\Core\Settings\Settings;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class TourEditorScreen {

	public function __construct( private Settings $settings ) {}

	public function hooks(): void {
		// The classic editor keeps the screen simple: description + one editor panel with the rest.
		add_filter(
			'use_block_editor_for_post_type',
			static fn ( bool $use, string $post_type ): bool => TourPostType::POST_TYPE === $post_type ? false : $use,
			10,
			2
		);

		add_action( 'add_meta_boxes_' . TourPostType::POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function add_meta_box(): void {
		// Raw custom fields would let editors bypass validation.
		remove_meta_box( 'postcustom', TourPostType::POST_TYPE, 'normal' );

		add_meta_box(
			'stz-tour-editor',
			__( 'Tour details, pricing & departures', 'suntourz' ),
			array( $this, 'render' ),
			TourPostType::POST_TYPE,
			'normal',
			'high'
		);
	}

	public function render( WP_Post $post ): void {
		$config = array(
			'tourId'   => $post->ID,
			'restUrl'  => esc_url_raw( rest_url( 'stz/v1/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'currency' => $this->settings->currency(),
			'locale'   => get_user_locale(),
			'today'    => wp_date( 'Y-m-d' ),
		);

		printf(
			'<script type="application/json" id="stz-tour-editor-config">%s</script>',
			wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HEX-escaped JSON.
		);
		echo '<div id="stz-tour-editor-root" class="stz-admin"><p>' . esc_html__( 'Loading tour editor…', 'suntourz' ) . '</p></div>';
	}

	public function enqueue( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || TourPostType::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media(); // Gallery picker (wp.media).
		ViteAssets::enqueue( 'tour-editor' );
	}
}
