<?php
/**
 * Service container exposed to the theme (the theme renders; it never queries tables itself).
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core;

use Suntourz\Core\Repository\DepartureRepository;
use Suntourz\Core\Services\ArticleService;
use Suntourz\Core\Services\QuoteService;
use Suntourz\Core\Services\ReviewService;
use Suntourz\Core\Services\SiteContent;
use Suntourz\Core\Services\TermCatalog;
use Suntourz\Core\Services\TourCatalog;
use Suntourz\Core\Settings\Settings;
use Suntourz\Core\Support\Turnstile;

defined( 'ABSPATH' ) || exit;

final class Container {

	public function __construct(
		public readonly Settings $settings,
		public readonly DepartureRepository $departures,
		public readonly TourCatalog $catalog,
		public readonly QuoteService $quotes,
		public readonly ReviewService $reviews,
		public readonly TermCatalog $terms,
		public readonly ArticleService $articles,
		public readonly SiteContent $content,
		public readonly Turnstile $turnstile
	) {}
}
