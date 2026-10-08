<?php
/**
 * Demo content: 3 complete tours (images, itineraries, plans, extras, departures relative to today),
 * sample bookings in every status, pages, menus, destinations, styles and guide articles.
 * Everything created is recorded so "Remove demo data" deletes exactly that and nothing else.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Services;

use Suntourz\Core\Install\Schema;
use Suntourz\Core\PostTypes\TourMeta;
use Suntourz\Core\PostTypes\TourPostType;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class DemoImporter {

	public const MANIFEST = 'stz_demo_manifest';

	/** @var array<string, array<int, int>> */
	private array $made = array(
		'posts'    => array(),
		'terms'    => array(),
		'bookings' => array(),
		'menus'    => array(),
		'media'    => array(),
	);

	public function has_demo(): bool {
		return is_array( get_option( self::MANIFEST ) );
	}

	/**
	 * @return array<string, int>|WP_Error Counts of what was created.
	 */
	public function import(): array|WP_Error {
		if ( $this->has_demo() ) {
			return new WP_Error( 'stz_demo_exists', __( 'Demo data is already imported. Remove it first to import again.', 'suntourz' ), array( 'status' => 409 ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$destinations = $this->destinations();
		$styles       = $this->styles();

		$bangkok = $this->tour(
			array(
				'title'    => 'Bangkok & Ayutthaya Culture',
				'excerpt'  => 'Temples, river palaces and street food across Bangkok and the ancient capital of Ayutthaya.',
				'days'     => 4,
				'dest'     => 'Bangkok',
				'styles'   => array( 'Culture', 'Thai Food & Culture' ),
				'image'    => 'bangkok.jpg',
				'featured' => true,
				'offer'    => false,
				'extra_hl' => array( 'Sunrise at Wat Arun', 'Private long-tail ride on the Chao Phraya', 'Ayutthaya temples by bicycle' ),
				'days_plan' => array(
					array( 'Arrival & river dinner', 'Private transfer to your riverside hotel and a candlelit dinner cruise.', array( array( '10:00', 'transfer', 'Airport pickup' ), array( '19:00', 'meal', 'Dinner cruise' ) ) ),
					array( 'Grand Palace & Wat Pho', 'Beat the crowds at the palace, then a traditional massage lesson.', array( array( '08:00', 'activity', 'Grand Palace with a guide' ), array( '13:00', 'meal', 'Lunch in Chinatown' ) ) ),
					array( 'Ayutthaya day trip', 'Ruined temples, floating markets and a sunset boat back.', array( array( '07:30', 'transfer', 'Private van to Ayutthaya' ), array( '16:30', 'activity', 'Sunset boat ride' ) ) ),
					array( 'Free morning & departure', 'Last-minute shopping and a private airport transfer.', array( array( '12:00', 'transfer', 'Airport drop-off' ) ) ),
				),
			),
			$destinations,
			$styles
		);
		$chiang = $this->tour(
			array(
				'title'    => 'Chiang Mai Jungle Adventure',
				'excerpt'  => 'Rainforest ridges, ethical elephant sanctuaries and the old city of Chiang Mai.',
				'days'     => 5,
				'dest'     => 'Chiang Mai',
				'styles'   => array( 'Adventure', 'Nature' ),
				'image'    => 'chiangmai.jpg',
				'featured' => true,
				'offer'    => false,
				'extra_hl' => array( 'Half-day with an ethical elephant sanctuary', 'Doi Inthanon summit trek', 'Night Bazaar food safari' ),
				'days_plan' => array(
					array( 'Arrival in Chiang Mai', 'Check in to your boutique hotel in the old city.', array( array( '11:00', 'transfer', 'Airport pickup' ) ) ),
					array( 'Old city & temples', 'Doi Suthep at dawn and a hands-on Northern Thai cooking class.', array( array( '06:30', 'activity', 'Doi Suthep sunrise' ), array( '14:00', 'activity', 'Cooking class' ) ) ),
					array( 'Elephant sanctuary', 'Feed, walk and bathe rescued elephants — no riding.', array( array( '08:00', 'activity', 'Sanctuary visit' ), array( '19:00', 'free', 'Night Bazaar at leisure' ) ) ),
					array( 'Doi Inthanon trek', 'Waterfalls, cloud forest and hill-tribe village lunch.', array( array( '07:00', 'activity', 'Guided trek' ) ) ),
					array( 'Departure', 'Slow morning, then transfer to the airport.', array( array( '12:00', 'transfer', 'Airport drop-off' ) ) ),
				),
			),
			$destinations,
			$styles
		);
		$phuket = $this->tour(
			array(
				'title'    => 'Phuket & Phi Phi Island Hopping',
				'excerpt'  => 'Private charters through Phang Nga Bay and the emerald lagoons of Phi Phi.',
				'days'     => 6,
				'dest'     => 'Phuket',
				'styles'   => array( 'Island Hopping', 'Luxury Resorts' ),
				'image'    => 'phuket.jpg',
				'featured' => true,
				'offer'    => true,
				'extra_hl' => array( 'Sunrise at Maya Bay before the boats arrive', 'Private catamaran through Phang Nga Bay', 'Snorkeling in Pi Leh Lagoon' ),
				'days_plan' => array(
					array( 'Arrival in Phuket', 'Transfer to your clifftop pool villa.', array( array( '12:00', 'transfer', 'Airport pickup' ), array( '18:30', 'meal', 'Sunset dinner' ) ) ),
					array( 'Phang Nga Bay', 'Private catamaran, sea-kayak caves and a beach picnic.', array( array( '08:00', 'activity', 'Catamaran charter' ) ) ),
					array( 'Free day', 'Spa, beach club or old-town stroll.', array( array( '10:00', 'free', 'At leisure' ) ) ),
					array( 'Phi Phi by speedboat', 'Maya Bay at opening time and snorkeling in Pi Leh.', array( array( '07:30', 'activity', 'Speedboat to Phi Phi' ) ) ),
					array( 'Wellness day', 'Herbal compress massage and a cooking masterclass.', array( array( '11:00', 'activity', 'Spa ritual' ) ) ),
					array( 'Departure', 'Private transfer to the airport.', array( array( '12:00', 'transfer', 'Airport drop-off' ) ) ),
				),
			),
			$destinations,
			$styles
		);

		// Departures relative to today.
		$b1 = $this->departure( $bangkok, 10, 3, 10, 'open' );
		$b2 = $this->departure( $bangkok, 40, 3, 12, 'open' );
		$this->departure( $bangkok, 70, 3, 12, 'open' );
		$c1 = $this->departure( $chiang, 7, 4, 10, 'open', array( 'couple' => array( 'sale_price' => 2500000 ) ) );
		$this->departure( $chiang, 35, 4, 10, 'open' );
		$this->departure( $chiang, 60, 4, 10, 'closed' );
		$p1 = $this->departure( $phuket, 20, 5, 8, 'open' );
		$p2 = $this->departure( $phuket, 50, 5, 12, 'open' );
		$this->departure( $phuket, 80, 5, 12, 'open' );

		// Six sample bookings, one per status (Bangkok +10d ends with exactly 3 seats left).
		$this->booking( $bangkok, $b1, 'couple', 2, 'new', 'Anna Schmidt', 'anna@example.com', '+49 151 2345678', 'whatsapp', '+49 151 2345678', 'We are celebrating our anniversary.' );
		$this->booking( $bangkok, $b1, 'couple', 2, 'contacted', 'Liam Walker', 'liam@example.com', '+44 7911 123456', 'phone', '', '' );
		$this->booking( $bangkok, $b1, 'family', 3, 'confirmed', 'Sofia Rossi', 'sofia@example.com', '+39 333 1234567', 'email', '', 'Vegetarian meals please.', 0, '' );
		$this->booking( $chiang, $c1, 'couple', 2, 'paid', 'Noah Kim', 'noah@example.com', '+82 10 1234 5678', 'line', 'noah_kim', '', 2500000, 'bank_transfer' );
		$this->booking( $phuket, $p1, 'family', 8, 'confirmed', 'Mia Johansson', 'mia@example.com', '+46 70 123 45 67', 'phone', '', 'Two children aged 6 and 9.', 0, '' );
		$this->booking( $phuket, $p2, 'single', 1, 'cancelled', 'Omar Haddad', 'omar@example.com', '+971 50 123 4567', 'whatsapp', '+971501234567', 'Change of plans.' );

		$this->articles();
		$this->pages_and_menus();

		update_option( self::MANIFEST, $this->made, false );

		return array(
			'tours'    => 3,
			'bookings' => count( $this->made['bookings'] ),
			'posts'    => count( $this->made['posts'] ),
			'terms'    => count( $this->made['terms'] ),
		);
	}

	/**
	 * Deletes exactly what import() created.
	 */
	public function remove(): bool {
		global $wpdb;

		$manifest = get_option( self::MANIFEST );
		if ( ! is_array( $manifest ) ) {
			return false;
		}

		$booking_ids = array_map( 'absint', (array) ( $manifest['bookings'] ?? array() ) );
		if ( array() !== $booking_ids ) {
			$in = implode( ',', $booking_ids );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids are absint-cast.
			$wpdb->query( 'DELETE FROM ' . Schema::booking_log() . " WHERE booking_id IN ({$in})" );
			$wpdb->query( 'DELETE FROM ' . Schema::bookings() . " WHERE id IN ({$in})" );
			// phpcs:enable
		}

		// Departures belong to demo tours.
		foreach ( (array) ( $manifest['posts'] ?? array() ) as $post_id ) {
			if ( TourPostType::POST_TYPE === get_post_type( (int) $post_id ) ) {
				$wpdb->delete( Schema::departures(), array( 'tour_id' => (int) $post_id ) );
			}
		}
		foreach ( get_comments( array( 'type' => ReviewService::TYPE, 'post__in' => array_map( 'absint', (array) ( $manifest['posts'] ?? array() ) ) ?: array( 0 ) ) ) as $comment ) {
			wp_delete_comment( (int) $comment->comment_ID, true );
		}

		foreach ( (array) ( $manifest['posts'] ?? array() ) as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}
		foreach ( (array) ( $manifest['media'] ?? array() ) as $attachment_id ) {
			wp_delete_attachment( (int) $attachment_id, true );
		}
		foreach ( (array) ( $manifest['terms'] ?? array() ) as $pair ) {
			if ( is_array( $pair ) ) {
				wp_delete_term( (int) $pair[0], (string) $pair[1] );
			}
		}
		foreach ( (array) ( $manifest['menus'] ?? array() ) as $menu_id ) {
			wp_delete_nav_menu( (int) $menu_id );
		}

		delete_option( self::MANIFEST );
		flush_rewrite_rules( false );

		return true;
	}

	/* ---------------------------------------------------------------- builders */

	/**
	 * @return array<string, int>
	 */
	private function destinations(): array {
		$u    = static fn ( string $id ): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w=1200&q=80";
		$rows = array(
			array( 'Phuket', 'Tropical beaches, luxury resorts and island adventures.', 'Andaman Ocean Hub', 'Glamorous Coastal Luxury', 'November to April', '4 – 7 Days', "Kata Rocks & Surin Beach\nPhang Nga Bay Karsts\nOld Phuket Town mansions\nPrivate Catamaran Charters", 'photo-1589394815804-964ed0be2eb5', 'Thailand’s largest and most glamorous island blends luxury clifftop villas with powdery white-sand beaches and effortless access to Phang Nga Bay.' ),
			array( 'Bangkok', 'A vibrant mix of culture, nightlife, food and modern city life.', 'City of Angels', 'Dynamic Cultural Metropolis', 'November to February', '3 – 5 Days', "Chao Phraya Riverfront Palaces\nWat Pho & Wat Arun\nMichelin Star Street Food\nSkyline Rooftop Mixology", 'photo-1508009603885-50cf7c579365', 'An electrifying metropolis where sacred gilded temples sit alongside skyline rooftop bars, canal communities and world-acclaimed food.' ),
			array( 'Krabi', 'Dramatic limestone cliffs, turquoise waters and unforgettable islands.', 'Limestone Archipelago', 'Dramatic Natural Wonder', 'November to April', '4 – 6 Days', "Railay Beach & Phra Nang Cave\nKoh Hong Hidden Lagoon\nAo Thalane Sea Kayaking", 'photo-1552465011-b4e21bf6e79a', 'Famous for sheer karst cliffs rising from jade-colored seas — an Eden for adventurers and nature lovers.' ),
			array( 'Pattaya', 'Beach life, entertainment and vibrant coastal energy.', 'Eastern Seaboard & Na Jomtien', 'Coastal Sophistication & Energy', 'November to March', '2 – 4 Days', "Sanctuary of Truth\nNa Jomtien Secluded Villas\nKoh Larn Private Beaches", 'photo-1507525428034-b723cf961d3e', 'Two hours from Bangkok, Pattaya’s southern coast offers design villas, private marinas and sophisticated ocean clubs.' ),
			array( 'Koh Samui', 'Luxury resorts, tropical beaches and a slower island rhythm.', 'Gulf Sanctuary', 'Serene Tropical Sanctuary', 'December to August', '5 – 8 Days', "Ang Thong Marine Park\nFisherman’s Village\nHillside Infinity Pools", 'photo-1537956965359-7573183d1f57', 'Nestled in the Gulf of Thailand, Samui is defined by palm-fringed coastlines and ultra-boutique hillside retreats.' ),
			array( 'Phi Phi Islands', 'Iconic turquoise waters and some of Thailand’s most spectacular scenery.', 'Emerald Waters', 'Breathtaking Island Paradise', 'November to April', '2 – 4 Days', "Maya Bay Sunrise Access\nPi Leh Lagoon\nBamboo Island Coral Reefs", 'photo-1506929562872-bb421503ef21', 'Celebrated for dramatic vertical cliffs sheltering Maya Bay and glowing turquoise lagoons.' ),
		);

		$ids = array();
		foreach ( $rows as $order => $d ) {
			$id = $this->term( $d[0], TourPostType::DESTINATION, $d[8] );
			update_term_meta( $id, 'stz_tagline', $d[1] );
			update_term_meta( $id, 'stz_eyebrow', $d[2] );
			update_term_meta( $id, 'stz_vibe', $d[3] );
			update_term_meta( $id, 'stz_best_season', $d[4] );
			update_term_meta( $id, 'stz_ideal_stay', $d[5] );
			update_term_meta( $id, 'stz_highlights', $d[6] );
			update_term_meta( $id, 'stz_order', $order + 1 );
			update_term_meta( $id, 'stz_image_url', $u( $d[7] ) );
			$ids[ $d[0] ] = $id;
		}
		$ids['Chiang Mai'] = $this->term( 'Chiang Mai', TourPostType::DESTINATION, 'Temples, night markets and rainforest in the cool north.' );

		return $ids;
	}

	/**
	 * @return array<string, int>
	 */
	private function styles(): array {
		$u    = static fn ( string $id ): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w=1000&q=80";
		$rows = array(
			array( 'Island Hopping', 'Marine & Islands', 'Private wooden long-tails and catamarans navigating hidden limestone lagoons and secret snorkeling bays.', 'Full Day / Multi-Day', 'Phuket, Phi Phi & Krabi', 'photo-1506929562872-bb421503ef21' ),
			array( 'Luxury Resorts', 'Stays & Hospitality', 'Cliffside private infinity pools and open-air jungle pavilions with dedicated villa butlers.', 'Bespoke Stays', 'Phuket, Samui & Krabi', 'photo-1540555700478-4be289fbecef' ),
			array( 'Thai Food & Culture', 'Culinary & Heritage', 'Masterclasses with Royal Thai chefs, morning market discoveries and twilight street food safaris.', 'Half Day / Evening', 'Bangkok, Chiang Mai & Phuket', 'photo-1559314809-0d155014e29e' ),
			array( 'Adventure', 'Active Expeditions', 'Sea kayaking through tidal caves, climbing Railay cliffs and jungle ridge trekking.', 'Half Day / Full Day', 'Krabi & Phang Nga Bay', 'photo-1552465011-b4e21bf6e79a' ),
			array( 'Wellness & Spa', 'Holistic Rejuvenation', 'Herbal compress rituals, beachfront sound healing and restorative mindfulness retreats.', '2 to 4 Hours / Daily', 'Koh Samui & Phuket', 'photo-1544161515-4ab6ce6db874' ),
			array( 'Culture', 'Heritage & Cities', 'Temples, royal palaces and living traditions explored with resident curators.', 'Full Day', 'Bangkok & Chiang Mai', 'photo-1528181304800-259b08848526' ),
			array( 'Nature', 'Wildlife & Sanctuaries', 'Ethical elephant sanctuaries, protected marine reserves and ancient rainforest canopies.', 'Full Day', 'Chiang Mai, Khao Sok & Krabi', 'photo-1585970480901-90d6bb2a48b5' ),
			array( 'Private Experiences', 'Bespoke Luxury', 'Dawn monk blessings, scenic helicopter flights and candlelit dinners on uninhabited sandbars.', 'Custom Itineraries', 'All Destinations', 'photo-1512343879784-a960bf40e7f2' ),
		);

		$ids = array();
		foreach ( $rows as $order => $s ) {
			$id = $this->term( $s[0], TourPostType::STYLE, $s[2] );
			update_term_meta( $id, 'stz_category', $s[1] );
			update_term_meta( $id, 'stz_duration', $s[3] );
			update_term_meta( $id, 'stz_location', $s[4] );
			update_term_meta( $id, 'stz_order', $order + 1 );
			update_term_meta( $id, 'stz_image_url', $u( $s[5] ) );
			$ids[ $s[0] ] = $id;
		}

		return $ids;
	}

	private function term( string $name, string $taxonomy, string $description ): int {
		$term = wp_insert_term( $name, $taxonomy, array( 'description' => $description ) );
		if ( is_wp_error( $term ) ) {
			$existing = get_term_by( 'name', $name, $taxonomy );

			return $existing ? (int) $existing->term_id : 0;
		}
		$this->made['terms'][] = array( (int) $term['term_id'], $taxonomy );

		return (int) $term['term_id'];
	}

	/**
	 * @param array<string, mixed> $t            Tour definition.
	 * @param array<string, int>   $destinations Destination term ids by name.
	 * @param array<string, int>   $styles       Style term ids by name.
	 */
	private function tour( array $t, array $destinations, array $styles ): int {
		$id = (int) wp_insert_post(
			array(
				'post_type'    => TourPostType::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $t['title'],
				'post_excerpt' => $t['excerpt'],
				'post_content' => '<p>' . $t['excerpt'] . '</p><p>Every itinerary is orchestrated as an unbroken narrative — private transfers, curated stays and well-timed excursions that avoid the crowds.</p>',
			)
		);
		$this->made['posts'][] = $id;

		wp_set_object_terms( $id, array( $destinations[ $t['dest'] ] ?? 0 ), TourPostType::DESTINATION );
		wp_set_object_terms( $id, array_values( array_filter( array_map( static fn ( string $n ): int => $styles[ $n ] ?? 0, $t['styles'] ) ) ), TourPostType::STYLE );

		$thumb = $this->attach( (string) $t['image'], (string) $t['title'] );
		if ( $thumb ) {
			set_post_thumbnail( $id, $thumb );
			update_post_meta( $id, TourMeta::GALLERY, array( $thumb ) );
		}

		$itinerary = array();
		foreach ( $t['days_plan'] as $i => $day ) {
			$itinerary[] = array(
				'day'         => $i + 1,
				'title'       => $day[0],
				'description' => $day[1],
				'items'       => array_map( static fn ( array $item ): array => array( 'time' => $item[0], 'type' => $item[1], 'title' => $item[2] ), $day[2] ),
			);
		}

		update_post_meta( $id, TourMeta::DURATION, $t['days'] );
		update_post_meta( $id, TourMeta::HIGHLIGHTS, $t['extra_hl'] );
		update_post_meta( $id, TourMeta::INCLUDES, array( 'Private airport and hotel transfers', 'Boutique accommodation with daily breakfast', 'All entrance fees and permits', 'Dedicated English-speaking guide', '24/7 concierge on WhatsApp' ) );
		update_post_meta( $id, TourMeta::EXCLUDES, array( 'International flights', 'Travel insurance', 'Personal expenses and tips' ) );
		update_post_meta( $id, TourMeta::ITINERARY, $itinerary );
		update_post_meta( $id, TourMeta::MEETING_POINT, array( 'name' => 'Hotel lobby', 'address' => $t['dest'] . ', Thailand', 'lat' => null, 'lng' => null ) );
		update_post_meta(
			$id,
			TourMeta::PRICING,
			array(
				'currency' => 'THB',
				'plans'    => array(
					array( 'id' => 'single', 'label' => 'Single', 'pax' => 1, 'price' => 1800000, 'sale_price' => null ),
					array( 'id' => 'couple', 'label' => 'Couple', 'pax' => 2, 'price' => 3200000, 'sale_price' => 2900000 ),
					array( 'id' => 'family', 'label' => 'Family', 'pax' => 4, 'price' => 5600000, 'sale_price' => null ),
					array( 'id' => 'family_plus', 'label' => 'Family+', 'pax' => 5, 'price' => 6600000, 'sale_price' => null, 'extra_person_price' => 1100000, 'max_pax' => 8 ),
				),
			)
		);
		update_post_meta(
			$id,
			TourMeta::EXTRAS,
			array(
				array( 'id' => 'transfer', 'label' => 'Airport transfer', 'price' => 150000, 'unit' => 'per_booking', 'max_qty' => 2 ),
				array( 'id' => 'room_upgrade', 'label' => 'Room upgrade', 'price' => 350000, 'unit' => 'per_booking', 'max_qty' => 1 ),
				array( 'id' => 'diving', 'label' => 'Diving add-on', 'price' => 250000, 'unit' => 'per_person', 'max_qty' => null ),
			)
		);
		update_post_meta( $id, TourMeta::FEATURED, (bool) $t['featured'] );
		update_post_meta( $id, TourMeta::SPECIAL_OFFER, (bool) $t['offer'] );
		update_post_meta( $id, TourMeta::DEMO, true );

		return $id;
	}

	/**
	 * @param array<string, array<string, mixed>>|null $overrides Price overrides per plan.
	 */
	private function departure( int $tour_id, int $start_in_days, int $nights, int $capacity, string $status, ?array $overrides = null ): int {
		global $wpdb;

		$now = current_time( 'mysql', true );
		$wpdb->insert(
			Schema::departures(),
			array(
				'tour_id'         => $tour_id,
				'start_date'      => wp_date( 'Y-m-d', strtotime( "+{$start_in_days} days" ) ),
				'end_date'        => wp_date( 'Y-m-d', strtotime( '+' . ( $start_in_days + $nights ) . ' days' ) ),
				'capacity'        => $capacity,
				'status'          => $status,
				'price_overrides' => null === $overrides ? null : wp_json_encode( $overrides ),
				'created_at'      => $now,
				'updated_at'      => $now,
			)
		);

		return (int) $wpdb->insert_id;
	}

	private function booking( int $tour_id, int $departure_id, string $plan_id, int $pax, string $status, string $name, string $email, string $phone, string $channel, string $handle, string $message, int $paid = 0, string $method = '' ): void {
		global $wpdb;

		$prices = array( 'single' => 1800000, 'couple' => 2900000, 'family' => 5600000 );
		$now    = current_time( 'mysql', true );
		$code   = \Suntourz\Core\Domain\BookingCode::generate();

		$wpdb->insert(
			Schema::bookings(),
			array(
				'code'            => $code,
				'tour_id'         => $tour_id,
				'departure_id'    => $departure_id,
				'plan_id'         => $plan_id,
				'pax'             => $pax,
				'extras'          => '[]',
				'total_amount'    => $prices[ $plan_id ] ?? 0,
				'currency'        => 'THB',
				'customer_name'   => $name,
				'customer_email'  => $email,
				'customer_phone'  => $phone,
				'contact_channel' => $channel,
				'contact_handle'  => $handle,
				'message'         => $message,
				'status'          => $status,
				'admin_note'      => '',
				'amount_paid'     => $paid,
				'payment_method'  => '' === $method ? null : $method,
				'locale'          => 'en_US',
				'created_at'      => $now,
				'updated_at'      => $now,
			)
		);

		$id = (int) $wpdb->insert_id;
		$wpdb->insert( Schema::booking_log(), array( 'booking_id' => $id, 'from_status' => null, 'to_status' => 'new', 'note' => 'Booking request received', 'created_at' => $now ) );
		if ( 'new' !== $status ) {
			$wpdb->insert( Schema::booking_log(), array( 'booking_id' => $id, 'from_status' => 'new', 'to_status' => $status, 'note' => 'Demo data', 'created_at' => $now ) );
		}
		$this->made['bookings'][] = $id;
	}

	private function articles(): void {
		$rows = array(
			array( 'Best Time to Visit Thailand', 'Seasonal Guide', 'Understanding Thailand’s regional microclimates: when to visit the Andaman coast vs. the Gulf of Thailand for calm seas and perfect sunshine.', 'article-1.jpg', array( 'Thailand’s climate is delightfully nuanced rather than uniform. The Andaman coast and the Gulf coast follow different weather systems.', 'For Phuket and Krabi the premier window spans November through April, with calm seas and superb visibility for island hopping.', 'July and August — rainy on the Andaman side — are prime dry months in Samui, making it a summer haven. The green season (May to October) rewards travellers with lush landscapes and far fewer visitors.' ) ),
			array( 'How Much Does a Thailand Trip Cost?', 'Planning & Investment', 'A transparent breakdown of investment for boutique and luxury Thailand travel.', 'article-2.jpg', array( 'Thailand offers one of the best ratios of hospitality and culinary mastery to expenditure anywhere in the world.', 'Boutique journeys typically range from $300 to $650 per person per day including stays, private transfers and curated excursions; ultra-luxury journeys start around $750.', 'Booking through a Thailand specialist ensures boat transfers, park permits and VIP welcomes are integrated without surprises.' ) ),
			array( 'Where to Stay in Bangkok', 'Neighborhood Guide', 'Chao Phraya Riverside serenity versus Sukhumvit cosmopolitan energy.', 'article-3.jpg', array( 'Bangkok is a multi-centered metropolis. Choosing the right neighborhood defines your perception of the capital.', 'The riverside is the gold standard for romantic, timeless luxury; Sukhumvit and Thonglor are the pulse of contemporary Bangkok; the Old Town suits heritage lovers.' ) ),
		);

		foreach ( $rows as $i => $a ) {
			$post_id = (int) wp_insert_post(
				array(
					'post_type'    => 'post',
					'post_status'  => 'publish',
					'post_title'   => $a[0],
					'post_excerpt' => $a[2],
					'post_content' => '<p>' . implode( '</p><p>', $a[4] ) . '</p>',
					'post_date'    => wp_date( 'Y-m-d H:i:s', strtotime( '-' . ( $i + 1 ) . ' week' ) ),
				)
			);
			wp_set_object_terms( $post_id, array( $a[1] ), 'category' );
			$thumb = $this->attach( $a[3], $a[0] );
			if ( $thumb ) {
				set_post_thumbnail( $post_id, $thumb );
			}
			update_post_meta( $post_id, TourMeta::DEMO, true );
			$this->made['posts'][] = $post_id;
		}
	}

	private function page( string $title, string $content = '', string $template = '' ): int {
		$id = (int) wp_insert_post(
			array(
				'post_type'     => 'page',
				'post_status'   => 'publish',
				'post_title'    => $title,
				'post_content'  => $content,
				'page_template' => $template,
			)
		);
		update_post_meta( $id, TourMeta::DEMO, true );
		$this->made['posts'][] = $id;

		return $id;
	}

	/**
	 * Demo copy of the About page (the owner edits it like any WordPress page).
	 */
	private function about_html(): string {
		return '<p class="lead">Suntourz is a boutique Thailand tour operator. We plan fewer trips, in more detail, with people who live and work in the places you visit.</p>'
			. '<h2>Our story</h2>'
			. '<p>We started Suntourz after years of watching visitors rush through Thailand on crowded coaches. We believed the country was better experienced slowly: a private long-tail boat before the day trippers arrive, a cooking class in a family kitchen, a guide who knows which temple is quiet at sunrise.</p>'
			. '<h2>What makes us different</h2>'
			. '<ul><li><strong>Private charters</strong> timed to avoid the crowds</li><li><strong>Handpicked stays</strong> we have visited and would book ourselves</li><li><strong>A dedicated concierge</strong> before, during and after your trip</li><li><strong>Clear pricing</strong>: what you see on the tour page is what you pay</li></ul>'
			. '<h2>How booking works</h2>'
			. '<ol><li>Choose a tour and a departure date, or design your own trip.</li><li>Send your request. Nothing is charged online.</li><li>A specialist contacts you to confirm seats and agree payment.</li><li>Travel with a local concierge on call.</li></ol>'
			. '<blockquote>We would rather send you home with five perfect days than fifteen rushed ones.</blockquote>';
	}

	/**
	 * Demo copy of the Contact page.
	 */
	private function contact_html(): string {
		return '<p class="lead">Talk to a Thailand specialist. We usually reply within 24 hours.</p>'
			. '<h2>Ways to reach us</h2>'
			. '<ul><li><strong>Phone and WhatsApp</strong>: listed in the header and footer of every page.</li><li><strong>Plan My Trip</strong>: tell us your dates, group and budget and we design the route.</li><li><strong>Booking a tour</strong>: choose a departure on the tour page and send your request; we call you to confirm.</li></ul>'
			. '<h2>Before you write</h2>'
			. '<p>It helps to know your preferred month, how many people are travelling and roughly how long you can stay. If you are not sure, leave it blank. We will ask the right questions.</p>';
	}

	private function pages_and_menus(): void {
		$home    = $this->page( 'Home' );
		$blog    = $this->page( 'Blog' );
		$about   = $this->page( 'About Us', $this->about_html() );
		$contact = $this->page( 'Contact', $this->contact_html() );
		$plan    = $this->page( 'Plan My Trip', '', 'template-plan-my-trip.php' );
		$this->page( 'Booking Success', '', 'template-booking-success.php' );
		$terms   = $this->page( 'Terms & Cancellation', '<p>Bookings are requests until confirmed by our team. Cancellations more than 7 days before departure receive a full refund; 3–7 days 50%; 24–72 hours 25%; later or no-show: no refund. Weather cancellations are fully refunded or rescheduled.</p>' );

		// Only replace WordPress's out-of-the-box title/tagline, never one the owner chose.
		if ( in_array( (string) get_option( 'blogname' ), array( 'My WordPress Website', 'WordPress', '' ), true ) ) {
			update_option( 'blogname', 'Suntourz' );
		}
		if ( in_array( (string) get_option( 'blogdescription' ), array( 'Just another WordPress site', '' ), true ) ) {
			update_option( 'blogdescription', 'Thailand Travel Specialists' );
		}

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home );
		update_option( 'page_for_posts', $blog );

		$primary = wp_create_nav_menu( 'Suntourz Primary (demo)' );
		$footer  = wp_create_nav_menu( 'Suntourz Footer (demo)' );
		$items   = static function ( $menu, array $links ): void {
			foreach ( $links as $link ) {
				wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => $link[0], 'menu-item-url' => $link[1], 'menu-item-status' => 'publish', 'menu-item-type' => 'custom' ) );
			}
		};

		if ( ! is_wp_error( $primary ) && ! is_wp_error( $footer ) ) {
			$items(
				$primary,
				array(
					array( 'Home', home_url( '/' ) ),
					array( 'Tours', (string) get_post_type_archive_link( TourPostType::POST_TYPE ) ),
					array( 'Blog & Guide', (string) get_permalink( $blog ) ),
					array( 'About Us', (string) get_permalink( $about ) ),
					array( 'Contact', (string) get_permalink( $contact ) ),
				)
			);
			$items(
				$footer,
				array(
					array( 'Plan My Trip', (string) get_permalink( $plan ) ),
					array( 'Terms & Cancellation', (string) get_permalink( $terms ) ),
					array( 'About Us', (string) get_permalink( $about ) ),
					array( 'Contact', (string) get_permalink( $contact ) ),
				)
			);
			set_theme_mod( 'nav_menu_locations', array( 'primary' => $primary, 'footer' => $footer ) );
			$this->made['menus'] = array( (int) $primary, (int) $footer );
		}
	}

	/**
	 * Copies a bundled placeholder into the media library.
	 */
	private function attach( string $file, string $title ): int {
		$source = STZ_CORE_DIR . 'assets/demo/' . $file;
		if ( ! is_readable( $source ) ) {
			return 0;
		}

		$upload = wp_upload_bits( 'suntourz-demo-' . $file, null, (string) file_get_contents( $source ) );
		if ( ! empty( $upload['error'] ) ) {
			return 0;
		}

		$id = (int) wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => $title,
				'post_status'    => 'inherit',
			),
			$upload['file']
		);
		if ( 0 === $id ) {
			return 0;
		}

		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
		update_post_meta( $id, '_wp_attachment_image_alt', $title );
		$this->made['media'][] = $id;

		return $id;
	}
}
