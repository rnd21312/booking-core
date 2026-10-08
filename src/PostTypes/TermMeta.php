<?php
/**
 * Extra fields for destinations and travel styles (editorial cards on the home page).
 * Adds the fields to the add/edit term screens and stores them as term meta.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\PostTypes;

use WP_Term;

defined( 'ABSPATH' ) || exit;

final class TermMeta {

	public const NONCE = 'stz_term_meta';

	/**
	 * Taxonomy => field key => [label, type, help].
	 *
	 * @return array<string, array<string, array{string, string, string}>>
	 */
	public static function fields(): array {
		return array(
			TourPostType::DESTINATION => array(
				'stz_tagline'      => array( __( 'Tagline', 'suntourz' ), 'text', __( 'One short sentence shown on the card.', 'suntourz' ) ),
				'stz_eyebrow'      => array( __( 'Small label', 'suntourz' ), 'text', __( 'e.g. "Andaman Ocean Hub".', 'suntourz' ) ),
				'stz_vibe'         => array( __( 'Vibe', 'suntourz' ), 'text', __( 'e.g. "Glamorous Coastal Luxury".', 'suntourz' ) ),
				'stz_best_season'  => array( __( 'Best season', 'suntourz' ), 'text', __( 'e.g. "November to April".', 'suntourz' ) ),
				'stz_ideal_stay'   => array( __( 'Ideal stay', 'suntourz' ), 'text', __( 'e.g. "4 – 7 Days".', 'suntourz' ) ),
				'stz_highlights'   => array( __( 'Highlights', 'suntourz' ), 'textarea', __( 'One highlight per line.', 'suntourz' ) ),
				'stz_order'        => array( __( 'Order', 'suntourz' ), 'number', __( 'Lower numbers come first (the first six appear on the home page).', 'suntourz' ) ),
			),
			TourPostType::STYLE       => array(
				'stz_category'     => array( __( 'Category label', 'suntourz' ), 'text', __( 'e.g. "Marine & Islands".', 'suntourz' ) ),
				'stz_location'     => array( __( 'Where', 'suntourz' ), 'text', __( 'e.g. "Phuket, Phi Phi & Krabi".', 'suntourz' ) ),
				'stz_duration'     => array( __( 'Typical length', 'suntourz' ), 'text', __( 'e.g. "Full Day / Multi-Day".', 'suntourz' ) ),
				'stz_order'        => array( __( 'Order', 'suntourz' ), 'number', __( 'Lower numbers come first.', 'suntourz' ) ),
			),
		);
	}

	public function hooks(): void {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

		// Taxonomy names are listed literally: fields() holds translated labels and must not run before `init`.
		foreach ( array( TourPostType::DESTINATION, TourPostType::STYLE ) as $taxonomy ) {
			add_action( "{$taxonomy}_add_form_fields", array( $this, 'render_add' ) );
			add_action( "{$taxonomy}_edit_form_fields", array( $this, 'render_edit' ) );
			add_action( "created_{$taxonomy}", array( $this, 'save' ) );
			add_action( "edited_{$taxonomy}", array( $this, 'save' ) );
		}
	}

	public function register(): void {
		foreach ( self::fields() as $taxonomy => $fields ) { // Runs on `init`, so translating labels is safe.
			foreach ( array_keys( $fields ) as $key ) {
				register_term_meta(
					$taxonomy,
					$key,
					array(
						'type'              => 'stz_order' === $key ? 'integer' : 'string',
						'single'            => true,
						'show_in_rest'      => false,
						// Closure, not 'intval': WordPress passes 4 arguments and internal functions reject extras.
						'sanitize_callback' => 'stz_highlights' === $key
							? 'sanitize_textarea_field'
							: ( 'stz_order' === $key ? static fn ( mixed $value ): int => (int) $value : 'sanitize_text_field' ),
					)
				);
			}

			foreach ( array( 'stz_image_id' => 'absint', 'stz_image_url' => 'esc_url_raw' ) as $key => $sanitize ) {
				register_term_meta(
					$taxonomy,
					$key,
					array(
						'type'              => 'stz_image_id' === $key ? 'integer' : 'string',
						'single'            => true,
						'show_in_rest'      => false,
						'sanitize_callback' => $sanitize,
					)
				);
			}
		}
	}

	public function enqueue( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'edit-tags.php', 'term.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! isset( self::fields()[ $screen->taxonomy ] ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'stz-term-image', STZ_CORE_URL . 'assets/term-image.js', array(), STZ_CORE_VERSION, true );
	}

	public function render_add( string $taxonomy ): void {
		wp_nonce_field( self::NONCE, self::NONCE );

		$this->image_field( 0, 0, '', false );
		foreach ( self::fields()[ $taxonomy ] ?? array() as $key => [ $label, $type, $help ] ) {
			echo '<div class="form-field"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';
			$this->input( $key, $type, '' );
			echo '<p>' . esc_html( $help ) . '</p></div>';
		}
	}

	public function render_edit( WP_Term $term ): void {
		wp_nonce_field( self::NONCE, self::NONCE );

		$this->image_field(
			(int) get_term_meta( $term->term_id, 'stz_image_id', true ),
			$term->term_id,
			(string) get_term_meta( $term->term_id, 'stz_image_url', true ),
			true
		);

		foreach ( self::fields()[ $term->taxonomy ] ?? array() as $key => [ $label, $type, $help ] ) {
			$value = get_term_meta( $term->term_id, $key, true );
			echo '<tr class="form-field"><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
			$this->input( $key, $type, is_scalar( $value ) ? (string) $value : '' );
			echo '<p class="description">' . esc_html( $help ) . '</p></td></tr>';
		}
	}

	private function input( string $key, string $type, string $value ): void {
		if ( 'textarea' === $type ) {
			printf( '<textarea name="%1$s" id="%1$s" rows="4" cols="40">%2$s</textarea>', esc_attr( $key ), esc_textarea( $value ) );
			return;
		}

		printf( '<input type="%1$s" name="%2$s" id="%2$s" value="%3$s" class="regular-text">', 'number' === $type ? 'number' : 'text', esc_attr( $key ), esc_attr( $value ) );
	}

	private function image_field( int $image_id, int $term_id, string $url, bool $table_row ): void {
		$preview = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'medium' ) : $url;
		$open    = $table_row ? '<tr class="form-field"><th scope="row"><label>' . esc_html__( 'Image', 'suntourz' ) . '</label></th><td>' : '<div class="form-field"><label>' . esc_html__( 'Image', 'suntourz' ) . '</label>';
		$close   = $table_row ? '</td></tr>' : '</div>';

		echo $open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, escaped markup above.
		?>
		<div class="stz-term-image">
			<input type="hidden" name="stz_image_id" value="<?php echo esc_attr( (string) $image_id ); ?>" data-stz-image-id>
			<img src="<?php echo esc_url( $preview ); ?>" alt="" style="max-width:200px;height:auto;display:<?php echo '' === $preview ? 'none' : 'block'; ?>;margin-bottom:8px" data-stz-image-preview>
			<button type="button" class="button" data-stz-image-pick><?php esc_html_e( 'Choose image', 'suntourz' ); ?></button>
			<button type="button" class="button-link" data-stz-image-clear><?php esc_html_e( 'Remove', 'suntourz' ); ?></button>
			<p class="description"><?php esc_html_e( 'Or paste an image URL (used when no image is chosen):', 'suntourz' ); ?></p>
			<input type="url" name="stz_image_url" value="<?php echo esc_attr( $url ); ?>" class="regular-text" placeholder="https://">
		</div>
		<?php
		echo $close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function save( int $term_id ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		$term = get_term( $term_id );
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		foreach ( array_keys( self::fields()[ $term->taxonomy ] ?? array() ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_term_meta( $term_id, $key, wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_callback registered.
			}
		}

		update_term_meta( $term_id, 'stz_image_id', isset( $_POST['stz_image_id'] ) ? absint( $_POST['stz_image_id'] ) : 0 );
		update_term_meta( $term_id, 'stz_image_url', isset( $_POST['stz_image_url'] ) ? esc_url_raw( wp_unslash( $_POST['stz_image_url'] ) ) : '' );
	}
}
