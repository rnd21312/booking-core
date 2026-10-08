<?php
/**
 * Image payloads for the public API (responsive srcset included).
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Services;

defined( 'ABSPATH' ) || exit;

final class Media {

	/**
	 * @return array{id: int, url: string, srcset: string, width: int, height: int, alt: string}|null
	 */
	public static function image( int $attachment_id, string $size = 'large' ): ?array {
		if ( $attachment_id <= 0 ) {
			return null;
		}

		$src = wp_get_attachment_image_src( $attachment_id, $size );
		if ( ! $src ) {
			return null;
		}

		return array(
			'id'     => $attachment_id,
			'url'    => (string) $src[0],
			'srcset' => (string) wp_get_attachment_image_srcset( $attachment_id, $size ),
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
			'alt'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		);
	}
}
