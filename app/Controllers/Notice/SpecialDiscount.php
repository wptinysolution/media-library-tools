<?php
/**
 * Special Offer.
 *
 * @package RadiusTheme\SB
 */

namespace TinySolutions\mlt\Controllers\Notice;

use TinySolutions\mlt\Traits\SingletonTrait;
use TinySolutions\mlt\Abs\Discount;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Black Friday Offer.
 */
class SpecialDiscount extends Discount {

	/**
	 * Singleton Trait.
	 */
	use SingletonTrait;

	/**
	 * @return array
	 */
	public function the_options(): array {
		return [
			'option_name'             => 'tsmlt_special_offer_2025',
			'prev_option_name'        => 'tsmlt_special_offer_2024',
			'start_date'              => '19 October 2025',
			'end_date'                => '05 November 2025',
			'minimum_activation_days' => 365,
			'maximum_activation_days' => 400,
			'notice_for'              => '🎉 Claim Your Free 1-Year Pro License!',
			'download_link'           => 'https://www.wptinysolutions.com/get-mlt-pro-version-for-free/',
			'download_button_text'    => 'Get My 1-Year Pro License',
			'notice_message'          => "This offer will close in 3 days. Don't miss this limited-time opportunity!",
		];
	}
}
