<?php
/**
 * `stz_tour` post type and its taxonomies. Slugs/names are permanent — the full product reuses them.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\PostTypes;

defined( 'ABSPATH' ) || exit;

final class TourPostType {

	public const POST_TYPE   = 'stz_tour';
	public const DESTINATION = 'stz_destination';
	public const STYLE       = 'stz_style';

	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'               => __( 'Tours', 'suntourz' ),
					'singular_name'      => __( 'Tour', 'suntourz' ),
					'add_new_item'       => __( 'Add new tour', 'suntourz' ),
					'edit_item'          => __( 'Edit tour', 'suntourz' ),
					'new_item'           => __( 'New tour', 'suntourz' ),
					'view_item'          => __( 'View tour', 'suntourz' ),
					'search_items'       => __( 'Search tours', 'suntourz' ),
					'not_found'          => __( 'No tours found', 'suntourz' ),
					'not_found_in_trash' => __( 'No tours found in Trash', 'suntourz' ),
					'all_items'          => __( 'All tours', 'suntourz' ),
				),
				'public'            => true,
				'has_archive'       => 'tours',
				'rewrite'           => array(
					'slug'       => 'tours',
					'with_front' => false,
				),
				'show_in_rest'      => true,
				'rest_base'         => 'tours',
				'show_in_menu'      => 'stz-bookings', // Grouped under the Suntourz menu.
				// custom-fields is required for post meta to be exposed to the REST API.
				'supports'          => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
				'taxonomies'        => array( self::DESTINATION, self::STYLE ),
			)
		);

		register_taxonomy(
			self::DESTINATION,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Destinations', 'suntourz' ),
					'singular_name' => __( 'Destination', 'suntourz' ),
					'add_new_item'  => __( 'Add new destination', 'suntourz' ),
					'edit_item'     => __( 'Edit destination', 'suntourz' ),
					'search_items'  => __( 'Search destinations', 'suntourz' ),
					'parent_item'   => __( 'Parent destination', 'suntourz' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array(
					'slug'         => 'destinations',
					'with_front'   => false,
					'hierarchical' => true,
				),
			)
		);

		register_taxonomy(
			self::STYLE,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Travel styles', 'suntourz' ),
					'singular_name' => __( 'Travel style', 'suntourz' ),
					'add_new_item'  => __( 'Add new travel style', 'suntourz' ),
					'edit_item'     => __( 'Edit travel style', 'suntourz' ),
					'search_items'  => __( 'Search travel styles', 'suntourz' ),
				),
				'hierarchical'      => false,
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array(
					'slug'       => 'travel-style',
					'with_front' => false,
				),
			)
		);
	}
}
