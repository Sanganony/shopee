<?php
/**
 * Plugin Name: Shipping Viet Nam WooCommerce
 * Description: Plugin hỗ trợ toàn diện giao vận tại Việt Nam cho WooCommerce. Khách hàng chủ động chọn đơn vị giao vận và các gói giao vận ( Nhanh, Chuẩn, Tiết Kiệm ) tuỳ theo hầu bao của mình, việc này tạo sự tin tưởng cho người mua vì công khai chi phí ship giúp tăng tỉ lệ đặt hàng cho quản trị shop. Quản trị shop dễ dàng đăng vận đơn lên các đơn vị giao vận tuỳ theo lựa chọn của khách hàng khi đặt hàng chỉ với 1 Click, cùng với đó là tra cứu trạng thái vận đơn ngay từ trang quản trị.
 * Version: 2.0.6
 * Author: Hoàng Quốc Long - 0976 892 757 - longbsvnu@gmail.com
 * Author URI: https://hoangquoclong.com
 * License: GNU General Public License v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Shipping Vietnam Woocommerce plugin, Copyright 2018 Hoang Quoc Long
 * Shipping Vietnam Woocommerce is distributed under the terms of the GNU GPL
 *
 * Requires at least: 4.1
 * Tested up to: 5.0.1
 * Text Domain: svw
 * Domain Path: /languages/
 *
 * @package Shipping Viet Nam Woocommerce
 * @subpackage Shipping Viet Nam Woocommerce
 */

define( 'SVW_DIR', plugin_dir_url(__FILE__) );
define( 'SVW_DIR_PATH', plugin_dir_path(__FILE__) );
define( 'SVW_API_GHN_URL', 'https://console.ghn.vn' ); //'https://console.ghn.vn'  http://api.serverapi.host);
define( 'SVW_API_GHTK_URL', 'https://services.giaohangtietkiem.vn' );
define( 'SVW_CITIES', file_get_contents( SVW_DIR.'assets/json/cities.json') );
define( 'SVW_DISTRICTS', file_get_contents( SVW_DIR.'assets/json/districts.json') );
define( 'SVW_WARDS', file_get_contents( SVW_DIR.'assets/json/wards.json') );


add_action( 'plugins_loaded', array( 'Shipping_Vietnam_Woocommerce', 'plugins_loaded' ) );
add_action( 'after_setup_theme', array( 'Shipping_Vietnam_Woocommerce', 'after_setup_theme' ), 5 );

class Shipping_Vietnam_Woocommerce {

	function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_script' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_script' ) );
        add_action( 'woocommerce_shipping_methods', array( $this, 'register_shipping_methods' ) );
        add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'add_shipping_packages' ) );

		include_once SVW_DIR_PATH.'/class/shipping-custom-fields.php';
        include_once SVW_DIR_PATH.'/class/shipping-custom-fields-order.php';
        include_once SVW_DIR_PATH.'/class/shipping-method-ghn.php';
        include_once SVW_DIR_PATH.'/class/shipping-method-ghtk.php';
        include_once SVW_DIR_PATH.'/class/ajax.php';
        include_once SVW_DIR_PATH.'/class/ultility.php';
	}

    function wp_enqueue_script() {
        if ( is_checkout() ) {
            wp_enqueue_script( 'svw', SVW_DIR.'assets/js/svw.js', array('jquery'), false, true );
            wp_localize_script( 'svw', 'svw', array(
                'ajax' => array(
                    'url' => admin_url( 'admin-ajax.php' )
                )
            ));
        }
    }

    function admin_enqueue_script() {
        $screen       = get_current_screen();
        $screen_id    = $screen ? $screen->id : '';

        wp_enqueue_style( 'svw-admin', SVW_DIR.'assets/css/admin.css', false, '1.0.0' );

        if ( $screen_id == 'woocommerce_page_wc-settings' || $screen_id == 'shop_order' ) {
            wp_enqueue_script( 'svw-admin', SVW_DIR.'assets/js/admin.js', array('jquery'), false, true );
            wp_localize_script( 'svw-admin', 'svw_admin_params', array(
                'ajax' => array(
                    'url' => admin_url( 'admin-ajax.php' )
                )
            ));
        }
    }

    function register_shipping_methods( $methods ) {
        $methods['svw_ghn'] = 'SVW_Shipping_Method_Ghn';
        $methods['svw_ghtk'] = 'SVW_Shipping_Method_Ghtk';

        return $methods;
    }

    function add_shipping_packages( $packages ) {
        $city_id = WC()->session->get( 'city_id' );
        $district_id = WC()->session->get( 'district_id' );
        if ( $district_id ) {
            $packages[0]['destination']['district'] = $district_id;
        } else {
            $packages[0]['destination']['district'] = get_user_meta( get_current_user_id(), 'billing_svw_district', true );
        }

        if ( $city_id ) {
            $packages[0]['destination']['city'] = $city_id;
        } else {
            $packages[0]['destination']['city'] = get_user_meta( get_current_user_id(), 'billing_svw_city', true );
        }

        return $packages;
    }

	public static function plugins_loaded() {
		load_plugin_textdomain( 'svw', false, SVW_DIR_PATH . '/languages/' );
	}

	public static function after_setup_theme() {
		new Shipping_Vietnam_Woocommerce();
	}

}