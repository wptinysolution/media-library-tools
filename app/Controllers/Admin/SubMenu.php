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
	const MENU_CAPABILITY = 'manage_options';

    /**
     * Autoload method
     * @return void
     */
    private function __construct() {
        add_action( 'admin_menu', array( $this, 'register_sub_menu') );
    }

    /**
     * Register submenu
     * @return void
     */
    public function register_sub_menu() {
        add_submenu_page(
	        self::MENU_PAGE_SLUG,
            esc_html__('Media Tools', 'tsmlt-media-tools'),
            esc_html__('Media Tools', 'tsmlt-media-tools'),
	        self::MENU_CAPABILITY,
            'tsmlt-media-tools',
            array($this, 'wp_media_page_callback')
        );
		if( ! tsmlt()->has_pro() ){
			add_submenu_page(
				self::MENU_PAGE_SLUG,
				esc_html__( 'License', 'tsmlt-media-tools' ),
				'<span style="color: #6BBE66;">' . esc_html__( 'Go Pro - Media Tools', 'tsmlt-media-tools' ) . '</span>',
				self::MENU_CAPABILITY,
				'tsmlt-get-pro',
				[ $this, 'pro_pages' ]
			);
		}

		do_action( 'tsmlt/add/more/submenu', self::MENU_PAGE_SLUG, self::MENU_CAPABILITY  );

    }

    /**
     * Render submenu
     * @return void
     */
    public function wp_media_page_callback() {
        echo '<div class="wrap"><div id="media_root"></div></div>';
    }


	/**
	 * @return void
	 */
	public function pro_pages() { ?>
		<div class="wrap tsmlt-license-wrap">
            <style>
                .media_page_tsmlt-get-pro #wpwrap {
                    background: #f9faff;
                }

                #tsmlt-pro-key-wrapper{
                    display: flex;
                    gap: 15px;
                    justify-content: center;
                    flex-wrap: wrap;
                }


                #tsmlt-pro-key-wrapper .columns {
                    width: 450px;
                    margin-top: 100px;
                    background: #fff;
                    border-radius: 8px;
                }
                #tsmlt-pro-key-wrapper .price li.footer * {
                    flex: 1;
                }
                #tsmlt-pro-key-wrapper .price {
                    margin: 0;
                    padding: 0;
                    padding-bottom: 20px;
                }
                #tsmlt-pro-key-wrapper .price .header {
                    padding: 25px 15px;
                    font-size: 25px;
                    display: block;
                    font-weight: 700;
                    background: #1677ff;
                    color: #fff;
                    line-height: 1.4;
                    margin-bottom: 35px;
                }
                #tsmlt-pro-key-wrapper .price .header span {
                    color: #fff;
                    font-size: 15px;
                }
                #tsmlt-pro-key-wrapper .price  li.footer {
                    margin-top: 25px;
                    margin-bottom: 20px;
                }
                #tsmlt-pro-key-wrapper .price li {
                    padding: 10px 30px;
                    display: flex;
                    gap: 10px;
                    font-weight: 600;
                    font-size: 16px;
                }

                #tsmlt-pro-key-wrapper .price li  span{
                    color: #1677ff;
                }

                #tsmlt-pro-key-wrapper #purchase {
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
                }

                #tsmlt-pro-key-wrapper #licenses ,
                #tsmlt-pro-key-wrapper #billing_cycle {
                    padding: 5px 25px 5px 15px;
                    border-radius: 8px;
                    border-color: #1677ff;
                }
                #tsmlt-pro-key-wrapper .price .header .price-for {
                    display: none;
                }

                #tsmlt-pro-key-wrapper .price .header .price-for.active-plan{
                    display: flex;
                    flex-direction: column;
                    gap: 5px;
                }

                #tsmlt-pro-key-wrapper .price .header .price-for span{
                    display: none;
                }

                #tsmlt-pro-key-wrapper .price .header .price-for.active-plan .active-cycle{
                    display: flex;
                }

                @media only screen and (max-width: 600px) {
                    #tsmlt-pro-key-wrapper .columns {
                        width: 100%;
                    }
                }
            </style>
			<div id="tsmlt-pro-key-wrapper" class="tsmlt-pro-page-wrapper">

                <div class="columns">
                    <ul class="price">
                        <li class="header">
                            PRO
                            <div style="border-bottom: 1px solid rgb(255 255 255 / 31%);margin: 15px 0;"></div>
                            <div class="price-for website-1 active-plan" >
                                <span class="annual active-cycle"> $24.99 / Annual </span>
                                <span class="lifetime"> $74.99 / Lifetime </span>
                            </div>
                            <div class="price-for website-5" >
                                <span class="annual"> $74.99 / Annual </span>
                                <span class="lifetime"> $224.99 / Lifetime </span>
                            </div>
                            <div class="price-for website-10" >
                                <span class="annual"> $124.99 / Annual </span>
                                <span class="lifetime"> $384.99 / Lifetime </span>
                            </div>
                            <div class="price-for website-50">
                                <span class="annual"> $194.99 / Annual </span>
                                <span class="lifetime"> $594.99 / Lifetime </span>
                            </div>
                        </li>
                        <li class="item"> <span class="dashicons dashicons-yes-alt"></span> Bulk Rename Media Files</li>
                        <li class="item"> <span class="dashicons dashicons-yes-alt"></span> Ignore Important Files</li>
                        <li class="item"> <span class="dashicons dashicons-yes-alt"></span> Bulk Ignore Important Files </li>
                        <li class="item"> <span class="dashicons dashicons-yes-alt"></span> Delete Rubbish/Junk/Unnecessary Files</li>
                        <li class="item"> <span class="dashicons dashicons-yes-alt"></span> Bulk Delete Rubbish/Junk/Unnecessary Files</li>
                        <li class="footer">
                            <select id="licenses">
                                <option value="1" selected="selected">Single Site License</option>
                                <option value="5">5-Site License</option>
                                <option value="10">10-Site License</option>
                                <option value="50">50-Site License</option>
                            </select>
                            <select id="billing_cycle">
                                <option value="annual" selected="selected">Annual</option>
                                <option value="lifetime">Lifetime</option>
                            </select>
                            <button id="purchase">Buy Now</button>
                        </li>
                    </ul>

                </div>

                <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
                <script src="https://checkout.freemius.com/checkout.min.js"></script>
                <script>

                        $('#licenses').on( 'change', function ( e ) {
                            var active = $( this ).val();
                            var cycle = $('#billing_cycle').val();
                            $('.price-for').removeClass( 'active-plan' );
                            $('.price-for.website-' + active).addClass("active-plan");

                            $('.price-for').find( 'span' ).removeClass("active-cycle");
                            $('.price-for').find( 'span.' + cycle ).addClass("active-cycle");

                        } );

                        $('#billing_cycle').on( 'change', function ( e ) {
                            var active = $( '#licenses' ).val();
                            var cycle = $( this ).val();
                            $('.price-for').removeClass( 'active-plan' );
                            $('.price-for.website-' + active).addClass("active-plan");

                            $('.price-for').find( 'span' ).removeClass("active-cycle");
                            $('.price-for').find( 'span.' + cycle ).addClass("active-cycle");
                        } );

                        var handler = FS.Checkout.configure({
                            plugin_id:  '13159',
                            plan_id:    '22377',
                            public_key: 'pk_494675841e14feaa76ea20efa09ca',
                            image:      'https://ps.w.org/media-library-tools/assets/icon-128x128.png?rev=2876093'
                        });
                        $('#purchase').on('click', function (e) {
                            handler.open({
                                name     : 'Media library Tools Pro',
                                licenses : $('#licenses').val(),
                                billing_cycle: $('#billing_cycle').val(),
                                // You can consume the response for after purchase logic.
                                purchaseCompleted  : function (response) {
                                    // The logic here will be executed immediately after the purchase confirmation.                                // alert(response.user.email);
                                },
                                success  : function (response) {
                                    // The logic here will be executed after the customer closes the checkout, after a successful purchase.                                // alert(response.user.email);
                                }
                            });
                            e.preventDefault();
                        });
                </script>
			</div>
		</div>
		<?php
	}



}
