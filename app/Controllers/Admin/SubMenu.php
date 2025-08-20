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

		wp_enqueue_style( 'tsmlt-settings-style' );

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
		// add_submenu_page(
		// self::MENU_PAGE_SLUG,
		// esc_html__( 'Media Settings', 'media-library-tools' ),
		// esc_html__( 'Media Settings', 'media-library-tools' ),
		// self::MENU_CAPABILITY,
		// $menu_link_part . '#/'
		// );
		add_submenu_page(
			self::MENU_PAGE_SLUG,
			esc_html__( 'Media Table', 'media-library-tools' ),
			'<span class="tsmlt-is-submenu" ><span class="dashicons dashicons-arrow-right-alt" ></span>' . esc_html__( 'Media Table', 'media-library-tools' ) . '</span>',
			self::MENU_CAPABILITY,
			$menu_link_part . '#/mediaTable'
		);
		add_submenu_page(
			self::MENU_PAGE_SLUG,
			esc_html__( 'Media Rename', 'media-library-tools' ),
			'<span class="tsmlt-is-submenu" ><span class="dashicons dashicons-arrow-right-alt" ></span>' . esc_html__( 'Media Rename', 'media-library-tools' ) . '</span>',
			self::MENU_CAPABILITY,
			$menu_link_part . '#/mediaRename'
		);
		add_submenu_page(
			self::MENU_PAGE_SLUG,
			esc_html__( 'CSV Export/Import', 'media-library-tools' ),
			'<span class="tsmlt-is-submenu" ><span class="dashicons dashicons-arrow-right-alt" ></span>' . esc_html__( 'Export Import', 'media-library-tools' ) . '</span>',
			self::MENU_CAPABILITY,
			$menu_link_part . '#/exportImport'
		);
		add_submenu_page(
			self::MENU_PAGE_SLUG,
			esc_html__( 'Rubbish files', 'media-library-tools' ),
			'<span class="tsmlt-is-submenu" ><span class="dashicons dashicons-arrow-right-alt" ></span>' . esc_html__( 'Rubbish files', 'media-library-tools' ) . '</span>',
			self::MENU_CAPABILITY,
			$menu_link_part . '#/rubbishFile'
		);
		add_submenu_page(
			self::MENU_PAGE_SLUG,
			esc_html__( 'Image Size', 'media-library-tools' ),
			'<span class="tsmlt-is-submenu" ><span class="dashicons dashicons-arrow-right-alt" ></span>' . esc_html__( 'Image Size', 'media-library-tools' ) . '</span>',
			self::MENU_CAPABILITY,
			$menu_link_part . '#/imageSize'
		);
		add_submenu_page(
			self::MENU_PAGE_SLUG,
			esc_html__( 'Useful Plugins', 'media-library-tools' ),
			'<span class="tsmlt-is-submenu" ><span class="dashicons dashicons-arrow-right-alt" ></span>' . esc_html__( 'Useful Plugins ', 'media-library-tools' ) . '</span>',
			self::MENU_CAPABILITY,
			$menu_link_part . '#/plugins'
		);
		add_submenu_page(
			self::MENU_PAGE_SLUG,
			esc_html__( 'Get Support', 'media-library-tools' ),
			'<span class="tsmlt-is-submenu" ><span class="dashicons dashicons-arrow-right-alt" ></span>' . esc_html__( 'Get Support', 'media-library-tools' ) . '</span>',
			self::MENU_CAPABILITY,
			$menu_link_part . '#/support'
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
		echo '<div class="wrap"><div id="media_root"></div></div>';
	}


	/**
	 * @return void
	 */
	public function pro_pages() {
		?>
		<div class="wrap tsmlt-license-wrap">
			<?php
				wp_enqueue_style( 'freemius-pricing', 'https://wcss.freemius.com/wordpress/pages/pricing.css?v=180' );
				wp_enqueue_style( 'freemius-css', 'https://wcss.freemius.com/wordpress/common.css?v=180' );
			?>
			<style>
				.current .tsmlt-submenu,
				.current .dashicons{
					color: #1677ff !important;
				}
				.media_page_tsmlt-get-pro #wpwrap {
					background: #f9faff;
				}

				#tsmlt-pro-page-wrapper{
					display: flex;
					gap: 15px;
					justify-content: center;
					flex-wrap: wrap;
				}

				.tsmlt-pro-page-header #header {
					width: 915px;
					margin-left: auto;
					margin-right: auto;
					position: relative;
					border: 0;
				}

				#tsmlt-pro-page-wrapper .columns {
					width: 450px;
					background: #fff;
					border-radius: 8px;
				}
				#tsmlt-pro-page-wrapper .price li.footer * {
					flex: 1;
				}
				#tsmlt-pro-page-wrapper .price {
					margin: 0;
					padding: 0;
					padding-bottom: 20px;
				}
				#tsmlt-pro-page-wrapper .price .header {
					padding: 25px 15px;
					font-size: 30px;
					display: block;
					font-weight: 700;
					background: #1677ff;
					color: #fff;
					line-height: 1.4;
					margin-bottom: 35px;
				}
				#tsmlt-pro-page-wrapper .price .header span {
					color: #fff;
					font-size: 20px;
				}
				#tsmlt-pro-page-wrapper .price  li.footer {
					margin-top: 20px;
					margin-bottom: 10px;
				}
				#tsmlt-pro-page-wrapper .price li {
					padding: 10px 30px;
					display: flex;
					gap: 10px;
					font-size: 16px;
					line-height: 1.4;
				}

				#tsmlt-pro-page-wrapper .price li span{
					color: #1677ff;
				}

				#tsmlt-pro-page-wrapper .price li a:hover span,
				#tsmlt-pro-page-wrapper .price li a:hover{
					text-decoration: none;
					color: #FE0467 !important;
				}

				#tsmlt-pro-page-wrapper #purchase {
					color: #fff;
					background-color: #1677ff;
					box-shadow: 0 2px 0 rgba(5, 145, 255, 0.1);
					font-size: 16px;
					height: 40px;
					padding: 6.428571428571429px 15px;
					border-radius: 8px;
					cursor: pointer;
					border: 0;
					line-height: 1;
					min-width: 100px;
				}
				#tsmlt-pro-page-wrapper #purchase:hover{
					background-color: #FE0467;
				}
				
				#tsmlt-pro-page-wrapper #billing_cycle {
					padding: 5px 25px 5px 15px;
					border-radius: 8px;
					border-color: #1677ff;
					height: 40px;
					font-weight: 400;
				}
				#tsmlt-pro-page-wrapper .price .header .price-for {
					display: none;
				}

				#tsmlt-pro-page-wrapper .price .header .price-for.active-plan{
					display: flex;
					flex-direction: column;
					gap: 5px;
				}

				#tsmlt-pro-page-wrapper .price .header .price-for > span{
					display: none;
				}

				#tsmlt-pro-page-wrapper .price .header .price-for.active-plan .active-cycle{
					display: flex;
				}

				.tsmlt-pro-page-footer div#faq {
					background: #fff;
					border-radius: 8px;
				}
				.tsmlt-pro-page-footer {
					padding-top: 30px;
				}

				@media only screen and (max-width: 600px) {
					#tsmlt-pro-page-wrapper .columns {
						width: 100%;
					}
				}
			</style>

			<div class="tsmlt-pro-page-wrapper" >
				<div class="tsmlt-pro-page-header" >
					<header id="header" class="card clearfix" >
						<div class="product-header">
							<div class="product-icon">
								<img src="https://www.wptinysolutions.com/wp-content/uploads/2024/06/media-library-tools-con-256x256-1.gif" alt="">
							</div>
							<div class="product-header-body" style="padding-top: 0;">
								<h1 class="page-title">Plans and Pricing</h1>
								<h2 class="plugin-title">Media library Tools Pro</h2>
								<h3>Choose your plan and upgrade in minutes!</h3>
							</div>
						</div>
					</header>
				</div>
				<div id="tsmlt-pro-page-wrapper" >
					<div class="columns">
						<ul class="price">
							<li class="header">
								Premium Plan
							</li>
							<li class="item"> <span class="dashicons dashicons-yes-alt"></span> All free Features Included</li>
							<li class="item"> <span class="dashicons dashicons-yes-alt"></span> Media file CSV Export Import</li>
							<li class="item"> <span class="dashicons dashicons-yes-alt"></span> Register/Add new custom image sizes as needed.</li>
							<li class="item"> <span class="dashicons dashicons-yes-alt"></span> Bulk Renaming Based on Associated Post Title</li>
							<li class="item"> <span class="dashicons dashicons-yes-alt"></span> Renaming File Prior to Uploading Based on Attached Posts Title</li>
							<li class="item"> <span class="dashicons dashicons-yes-alt"></span> Auto Rename By Custom Text</li>
							<li class="item"> <span class="dashicons dashicons-yes-alt"></span> Bulk Add Alt Text, Caption, and Description Based on Associated Post Title</li>
							<li class="item"> <span class="dashicons dashicons-yes-alt"></span> Find And Bulk Delete Unnecessary / Rubbish File</li>
							<li class="item"> <span class="dashicons dashicons-yes-alt"></span> Rename Based on Woo Product SKU </li>
							<li class="item"> <span class="dashicons dashicons-yes-alt"></span> Search Used Images (Attached Post) </li>
							<li class="footer-text" style="display: flex;justify-content: space-between;">
								<div class="footer text">
									<a style="margin-top:30px;color: #1677ff;display: flex;align-items: center;gap: 5px;font-weight: 600;" target="_blank" href="https://checkout.freemius.com/plugin/13159/plan/22377/licenses/5/"> Buy Now <span class="dashicons dashicons-arrow-right-alt"></span></a>
								</div>
								<div class="footer text">
									<a style="margin-top:30px;color: #1677ff;display: flex;align-items: center;gap: 5px;font-weight: 600;" target="_blank" href="https://www.wptinysolutions.com/tiny-products/media-library-tools/"> Visit Our Website</a>
								</div>

							</li>
						</ul>
						
					</div>
					<div  class="columns" >
						<section id="money_back_guarantee" style="margin: 0;height: 100%;box-sizing: border-box;">
							<img style="max-width: 100%;" src="<?php echo tsmlt()->get_assets_uri( 'images/pngtree-gold-premium-quality-100-money-back-guaranteed-2.jpg' ); ?>" alt="">
							<h1 style="font-size: 20px;">
								<b class="stars"> <i class="last">⋆</i> <i class="middle">⋆</i>  <i class="first">⋆</i> </b>
								<span>30-Days Money Back Guarantee</span>
								<b class="stars">
									<i class="first">⋆</i>
									<i class="middle">⋆</i>
									<i class="last">⋆</i>
								</b>
							</h1>
							<p>
								You are fully protected by our 100% Money Back Guarantee. If during the next 30 days you experience an issue that makes the plugin unusable and we are unable to resolve it, we'll happily consider offering a full refund of your money.
								<span style="color: #6bc406;"> Please note that if you change your mind without any reason and want to seek a refund, it will not be processed in accordance with our policy.</span>
							</p>
						</section>
					</div>
				</div>

				<div id="tsmlt-pro-page-footer" class="tsmlt-pro-page-footer" >
					<div class="container" style="max-width: 915px;margin-bottom: 20px;font-size: 20px;margin: 50px auto;line-height: 1.4;">
						<span style="color: #6bc406;">Are you enjoying the free version? Have you got some valuable feedback to share? Have you encountered a bug and found a solution? If so, we might have a special <span style="color: red; font-weight: bold;"> discount </span> waiting for you!</span>
						Contact us via email to receive assistance and get the offer: <a style="color: #1677ff;font-weight: 600;" target="_blank" href="https://help.wptinysolutions.com/"><strong>  https://help.wptinysolutions.com/ </strong></a>
					</div>

					<div class="container" style="max-width: 915px;">

						<div id="faq" style="max-width: 915px;margin: 0;" >
							<h2 style="margin-bottom: 30px;margin-top: 10px; line-height: 1.2;">Frequently Asked Questions</h2>
								<ul>
									<li>
										<h3>Is there a setup fee?</h3>
										<p>No. There are no setup fees on any of our plans.</p>
									</li>
									<li>
										<h3>Can I cancel my account at any time?</h3>
										<p>Yes, if you ever decide that Media library Tools Pro isn't the best plugin for your business, simply cancel your account from your Account panel.</p>
									</li>
									<li>
										<h3>What's the time span for your contracts?</h3>
										<p>All plans are year-to-year unless you purchase a lifetime plan.</p>
									</li>
									<li>
										<h3>Do you offer a renewals discount?</h3>
										<p>Yes, you get 10% discount for all annual plan automatic renewals. The renewal price will never be increased so long as the subscription is not cancelled.</p>
									</li>
									<li>
										<h3>What payment methods are accepted?</h3>
										<p>We accept all major credit cards including Visa, Mastercard, American Express, as well as PayPal payments.</p>
									</li>
									<li>
										<h3>Do you offer refunds?</h3>
										<p>Yes we do! We stand behind the quality of our product and will refund 100% of your money if you experience an issue that makes the plugin unusable and we are unable to resolve it.</p>
									</li>
									<li>
										<h3>Do you have any restrictions on refunds?</h3>
										<p style="color: #6bc406;"> Please note that if you change your mind without any reason and want to seek a refund, it will not be processed in accordance with our policy.</span>
										</p>
									</li>
									<li>
										<h3>Do I get updates for the premium plugin?</h3>
										<p>Yes! Automatic updates to our premium plugin are available free of charge as long as you stay our paying customer.</p>
									</li>
									<li>
										<h3>Do you offer support if I need help?</h3>
										<p>Yes! Top-notch customer support is key for a quality product, so we'll do our very best to resolve any issues you encounter via our support page.</p>
									</li>
									<li>
										<h3>I have other pre-sale questions, can you help?</h3>
										<p>Yes! You can ask us any question through our <a class="contact-link" data-subject="pre_sale_question" href="https://help.wptinysolutions.com/">Our Website</a>.</p>
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



