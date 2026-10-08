<?php
/**
 * Plugin bootstrap: wires every module to WordPress hooks.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core;

use Suntourz\Core\Admin\Menu;
use Suntourz\Core\Admin\TourEditorScreen;
use Suntourz\Core\Cli\DemoCommand;
use Suntourz\Core\Rest\AdminController;
use Suntourz\Core\Services\BookingAdminService;
use Suntourz\Core\Services\DemoImporter;
use Suntourz\Core\Install\Activator;
use Suntourz\Core\Install\Roles;
use Suntourz\Core\Install\Schema;
use Suntourz\Core\PostTypes\TourMeta;
use Suntourz\Core\PostTypes\TourPostType;
use Suntourz\Core\Repository\DepartureRepository;
use Suntourz\Core\PostTypes\TermMeta;
use Suntourz\Core\PostTypes\TripRequestPostType;
use Suntourz\Core\Repository\BookingRepository;
use Suntourz\Core\Rest\AdminTourController;
use Suntourz\Core\Rest\BookingsController;
use Suntourz\Core\Services\BookingService;
use Suntourz\Core\Support\Turnstile;
use Suntourz\Core\Rest\ContentController;
use Suntourz\Core\Rest\HealthController;
use Suntourz\Core\Rest\QuoteController;
use Suntourz\Core\Rest\ReviewsController;
use Suntourz\Core\Rest\ToursController;
use Suntourz\Core\Rest\TripRequestsController;
use Suntourz\Core\Services\ArticleService;
use Suntourz\Core\Services\QuoteService;
use Suntourz\Core\Services\ReviewService;
use Suntourz\Core\Services\SiteContent;
use Suntourz\Core\Services\TermCatalog;
use Suntourz\Core\Services\TourCatalog;
use Suntourz\Core\Services\TourEditorService;
use Suntourz\Core\Services\TripRequestService;
use Suntourz\Core\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static bool $booted = false;

	private static ?Container $container = null;

	/**
	 * Services for the theme and other plugins. Null before plugins_loaded.
	 */
	public static function container(): ?Container {
		return self::$container;
	}

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action(
			'init',
			static function (): void {
				load_plugin_textdomain( 'suntourz', false, dirname( plugin_basename( STZ_CORE_FILE ) ) . '/languages' );
			},
			1
		);

		// Cheap version compare; runs migrations only when the schema is behind.
		Schema::maybe_upgrade();
		// Self-heal: roles/caps are DB data, so repair them if activation did not run or they were reset.
		if ( Roles::needs_repair() ) {
			Roles::add();
		}

		$settings   = new Settings();
		$departures = new DepartureRepository( $settings );
		$reviews    = new ReviewService( $settings );
		$catalog    = new TourCatalog( $departures, $settings, $reviews );
		$quotes     = new QuoteService( $departures, $settings );
		$terms      = new TermCatalog();
		$articles   = new ArticleService();
		$trips      = new TripRequestService( $settings );
		$content    = new SiteContent( $settings );
		$turnstile  = new Turnstile( $settings );
		$bookings   = new BookingService( $settings, $departures, new BookingRepository(), $quotes, $turnstile );

		$demo       = new DemoImporter();
		$booking_admin = new BookingAdminService( $settings );

		self::$container = new Container( $settings, $departures, $catalog, $quotes, $reviews, $terms, $articles, $content, $turnstile );

		$post_type = new TourPostType();
		$meta      = new TourMeta();
		add_action( 'init', array( $post_type, 'register' ) );
		add_action( 'init', array( $meta, 'register' ) );
		add_action( 'init', array( Activator::class, 'maybe_flush_rewrite' ), 99 );

		$reviews->hooks();
		( new TripRequestPostType() )->hooks();
		( new TermMeta() )->hooks();

		add_action( 'rest_api_init', array( new HealthController(), 'register_routes' ) );
		add_action( 'rest_api_init', array( new ToursController( $catalog ), 'register_routes' ) );
		add_action( 'rest_api_init', array( new QuoteController( $quotes ), 'register_routes' ) );
		add_action( 'rest_api_init', array( new BookingsController( $bookings ), 'register_routes' ) );
		add_action( 'rest_api_init', array( new ReviewsController( $reviews ), 'register_routes' ) );
		add_action( 'rest_api_init', array( new ContentController( $terms, $articles ), 'register_routes' ) );
		add_action( 'rest_api_init', array( new TripRequestsController( $trips, $settings ), 'register_routes' ) );
		add_action( 'rest_api_init', array( new AdminTourController( new TourEditorService( $departures, $settings ) ), 'register_routes' ) );

		add_action( 'rest_api_init', array( new AdminController( $booking_admin, $settings, $content, $demo ), 'register_routes' ) );

		if ( is_admin() ) {
			( new TourEditorScreen( $settings ) )->hooks();
			( new Menu( $booking_admin, $settings, $demo ) )->hooks();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'stz demo', new DemoCommand( $demo ) );
		}

		/**
		 * Fires once the core plugin is ready. Later milestones hook in from here.
		 *
		 * @param Settings $settings Plugin settings.
		 */
		do_action( 'stz_core_loaded', $settings );
	}
}
