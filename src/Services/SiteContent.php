<?php
/**
 * Editable site copy: shipped defaults (data/content-defaults.json) merged with the
 * overrides saved in Settings → Home content. Lists replace, objects merge.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Services;

use Suntourz\Core\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class SiteContent {

	/** @var array<string, mixed>|null */
	private ?array $defaults = null;

	public function __construct( private Settings $settings ) {}

	/**
	 * Complete content tree.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$saved = $this->settings->get( 'home_content', array() );

		return $this->merge( $this->defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Only the parts every page needs (header, footer, support modal, planner options).
	 *
	 * @return array<string, mixed>
	 */
	public function shared(): array {
		return array_intersect_key( $this->all(), array_flip( array( 'brand', 'planner', 'support' ) ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		if ( null === $this->defaults ) {
			$file           = STZ_CORE_DIR . 'data/content-defaults.json';
			$decoded        = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
			$this->defaults = is_array( $decoded ) ? $decoded : array();
		}

		return $this->defaults;
	}

	/**
	 * @param array<string, mixed> $base     Defaults.
	 * @param array<string, mixed> $override Saved values.
	 * @return array<string, mixed>
	 */
	private function merge( array $base, array $override ): array {
		foreach ( $override as $key => $value ) {
			$is_object = is_array( $value ) && ! array_is_list( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) && ! array_is_list( $base[ $key ] );

			if ( $is_object ) {
				$base[ $key ] = $this->merge( $base[ $key ], $value );
			} elseif ( null !== $value && '' !== $value ) {
				$base[ $key ] = $value;
			}
		}

		return $base;
	}
}
