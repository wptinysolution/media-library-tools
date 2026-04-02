<?php

namespace TinySolutions\mlt\Controllers\Admin;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * Sub menu class
 *
 * @author Mostafa <mostafa.soufi@hotmail.com>
 */
class SubMenu {
	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Parent Menu Page Slug
	 */
	const MENU_PAGE_SLUG = 'upload.php';

	/**
	 * Menu capability
	 */
	const MENU_CAPABILITY = 'upload_files';

	/**
	 * Autoload method
	 *
	 * @return void
	 */
	private function __construct() {
		add_action( 'admin_menu', [ $this, 'register_sub_menu' ] );
	}

	/**
	 * Register submenu
	 *
	 * @return void
	 */
	public function register_sub_menu() {

		$tab_title = apply_filters( 'tsmlt/add/get-pro/submenu/label', esc_html__( 'Get license', 'media-library-tools' ) );

		$title = '<span class="tsmlt-submenu" style="color: #6BBE66;"> <span class="dashicons-icons" style="transform: rotateX(180deg) rotate(180deg);font-size: 18px;"></span> ' . $tab_title . '</span>';

		$menu_link_part = admin_url( 'upload.php?page=media-library-tools' );
		add_submenu_page(
			self::MENU_PAGE_SLUG,
			esc_html__( 'Media Tools Settings', 'media-library-tools' ),
			'<span class="tsmlt-is-submenu" >' . esc_html__( 'Media Tools Settings', 'media-library-tools' ) . '</span>',
			self::MENU_CAPABILITY,
			'media-library-tools',
			[ $this, 'wp_media_page_callback' ]
		);
		add_submenu_page(
			self::MENU_PAGE_SLUG,
			$tab_title,
			$title,
			self::MENU_CAPABILITY,
			'tsmlt-get-pro',
			[ $this, 'pro_pages' ]
		);

		do_action( 'tsmlt/add/more/submenu', self::MENU_PAGE_SLUG, self::MENU_CAPABILITY );
	}

	/**
	 * Render submenu
	 *
	 * @return void
	 */
	public function wp_media_page_callback() {
		echo '<div id="media_root" style="margin-left: -20px;"></div>';
	}


	/**
	 * @return void
	 */
	public function pro_pages() {
		?>
		<div class="wrap tsmlt-license-wrap">
			<div class="tsmlt-pro-page-wrapper">
				<div id="tsmlt-pro-page-wrapper">
					<div class="tsmlt-pro-card">
						<ul class="tsmlt-pro-price-list">
							<li class="tsmlt-pro-card-header">
								Premium Plan
							</li>
							<li class="tsmlt-pro-feature-item"><span class="dashicons dashicons-yes-alt"></span> All Free Features Included</li>
							<li class="tsmlt-pro-feature-item"><span class="dashicons dashicons-yes-alt"></span> AI-Powered Content Generation</li>
							<li class="tsmlt-pro-feature-item"><span class="dashicons dashicons-yes-alt"></span> Bulk Edit by Post Title (Alt, Caption, Description)</li>
							<li class="tsmlt-pro-feature-item"><span class="dashicons dashicons-yes-alt"></span> Bulk Rename by Post Title / WooCommerce SKU</li>
							<li class="tsmlt-pro-feature-item"><span class="dashicons dashicons-yes-alt"></span> Auto Rename on Upload</li>
							<li class="tsmlt-pro-feature-item"><span class="dashicons dashicons-yes-alt"></span> Media CSV Export / Import</li>
							<li class="tsmlt-pro-feature-item"><span class="dashicons dashicons-yes-alt"></span> Register Custom Image Sizes</li>
							<li class="tsmlt-pro-feature-item"><span class="dashicons dashicons-yes-alt"></span> Merge Duplicate Files</li>
							<li class="tsmlt-pro-feature-item"><span class="dashicons dashicons-yes-alt"></span> Rubbish File Delete &amp; Restore</li>
							<li class="tsmlt-pro-feature-item"><span class="dashicons dashicons-yes-alt"></span> Find Where Images Are Used</li>
							<li class="tsmlt-pro-card-footer">
								<a class="tsmlt-pro-btn tsmlt-pro-btn--primary" target="_blank" href="https://checkout.freemius.com/plugin/13159/plan/22377/licenses/5/">Buy Now <span class="dashicons dashicons-arrow-right-alt"></span></a>
								<a class="tsmlt-pro-btn tsmlt-pro-btn--outline" target="_blank" href="https://www.wptinysolutions.com/tiny-products/media-library-tools/">Visit Our Website</a>
							</li>
						</ul>
					</div>
					<div class="tsmlt-pro-card">
						<section class="tsmlt-pro-guarantee">
							<img class="tsmlt-pro-guarantee__image" src="<?php echo esc_url( tsmlt()->get_assets_uri( 'images/pngtree-gold-premium-quality-100-money-back-guaranteed-2.jpg' ) ); ?>" alt="">
							<h1 class="tsmlt-pro-guarantee__title">
								30-Days Money Back Guarantee
							</h1>
							<p class="tsmlt-pro-guarantee__text">
								You are fully protected by our 100% Money Back Guarantee. If during the next 30 days you experience an issue that makes the plugin unusable and we are unable to resolve it, we'll happily consider offering a full refund of your money.
								<span class="tsmlt-pro-guarantee__note"> Please note that if you change your mind without any reason and want to seek a refund, it will not be processed in accordance with our policy.</span>
							</p>
						</section>
					</div>
				</div>

				<div id="tsmlt-pro-page-footer" class="tsmlt-pro-page-footer">
					<div class="tsmlt-pro-banner">
						<span class="tsmlt-pro-banner__highlight">Are you enjoying the free version? Have you got some valuable feedback to share? Have you encountered a bug and found a solution? If so, we might have a special <span class="tsmlt-pro-banner__discount"> discount </span> waiting for you!</span>
						Contact us via email to receive assistance and get the offer: <a class="tsmlt-pro-banner__link" target="_blank" href="https://help.wptinysolutions.com/"><strong>https://help.wptinysolutions.com/</strong></a>
					</div>

					<div class="tsmlt-pro-faq-container">
						<div class="tsmlt-pro-faq">
							<h2 class="tsmlt-pro-faq__title">Frequently Asked Questions</h2>
							<ul class="tsmlt-pro-faq__list">
								<li class="tsmlt-pro-faq__item">
									<h3 class="tsmlt-pro-faq__question">Is there a setup fee?</h3>
									<p class="tsmlt-pro-faq__answer">No. There are no setup fees on any of our plans.</p>
								</li>
								<li class="tsmlt-pro-faq__item">
									<h3 class="tsmlt-pro-faq__question">Can I cancel my account at any time?</h3>
									<p class="tsmlt-pro-faq__answer">Yes, if you ever decide that Media library Tools Pro isn't the best plugin for your business, simply cancel your account from your Account panel.</p>
								</li>
								<li class="tsmlt-pro-faq__item">
									<h3 class="tsmlt-pro-faq__question">What's the time span for your contracts?</h3>
									<p class="tsmlt-pro-faq__answer">All plans are year-to-year unless you purchase a lifetime plan.</p>
								</li>
								<li class="tsmlt-pro-faq__item">
									<h3 class="tsmlt-pro-faq__question">Do you offer a renewals discount?</h3>
									<p class="tsmlt-pro-faq__answer">Yes, you get 10% discount for all annual plan automatic renewals. The renewal price will never be increased so long as the subscription is not cancelled.</p>
								</li>
								<li class="tsmlt-pro-faq__item">
									<h3 class="tsmlt-pro-faq__question">What payment methods are accepted?</h3>
									<p class="tsmlt-pro-faq__answer">We accept all major credit cards including Visa, Mastercard, American Express, as well as PayPal payments.</p>
								</li>
								<li class="tsmlt-pro-faq__item">
									<h3 class="tsmlt-pro-faq__question">Do you offer refunds?</h3>
									<p class="tsmlt-pro-faq__answer">Yes we do! We stand behind the quality of our product and will refund 100% of your money if you experience an issue that makes the plugin unusable and we are unable to resolve it.</p>
								</li>
								<li class="tsmlt-pro-faq__item">
									<h3 class="tsmlt-pro-faq__question">Do you have any restrictions on refunds?</h3>
									<p class="tsmlt-pro-faq__answer tsmlt-pro-faq__answer--highlight">Please note that if you change your mind without any reason and want to seek a refund, it will not be processed in accordance with our policy.</p>
								</li>
								<li class="tsmlt-pro-faq__item">
									<h3 class="tsmlt-pro-faq__question">Do I get updates for the premium plugin?</h3>
									<p class="tsmlt-pro-faq__answer">Yes! Automatic updates to our premium plugin are available free of charge as long as you stay our paying customer.</p>
								</li>
								<li class="tsmlt-pro-faq__item">
									<h3 class="tsmlt-pro-faq__question">Do you offer support if I need help?</h3>
									<p class="tsmlt-pro-faq__answer">Yes! Top-notch customer support is key for a quality product, so we'll do our very best to resolve any issues you encounter via our support page.</p>
								</li>
								<li class="tsmlt-pro-faq__item">
									<h3 class="tsmlt-pro-faq__question">I have other pre-sale questions, can you help?</h3>
									<p class="tsmlt-pro-faq__answer">Yes! You can ask us any question through our <a href="https://help.wptinysolutions.com/">Our Website</a>.</p>
								</li>
							</ul>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}



