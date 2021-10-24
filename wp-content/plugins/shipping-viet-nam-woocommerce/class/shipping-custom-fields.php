<?php

if ( !class_exists( 'SVW_Custom_Fields' ) ) {
	class SVW_Custom_Fields {

		function __construct() {
			// Add thêm field khi checkout, sắp xếp lại thứ tự fields và ẩn một số field không sử dụng.
			add_filter( 'woocommerce_checkout_fields', array( $this, 'svw_woocommerce_checkout_fields' ), 99 );
			// Đổi label của field nếu cần.
			// add_filter( 'woocommerce_default_address_fields' ,  array( $this, 'svw_woocommerce_default_address_fields' ) );
			// Add thêm field lưu quận/huyện và xã phường của customer.
			add_filter( 'woocommerce_customer_meta_fields', array( $this, 'svw_woocommerce_customer_meta_fields' ) );
		}

		function svw_woocommerce_checkout_fields( $fields ) {

			$city_args = array(
				'label'   => esc_html__( 'Tỉnh/ Thành Phố', 'svw' ),
				'type'    => 'select',
				'options' => SVW_Ultility::get_cities_array(),
				'input_class' => array(
					'wc-enhanced-select svw-select-city',
				),
				'priority' => 90,
				'default'  => '333',
			);
			$fields['shipping']['shipping_city'] = $city_args;
			$fields['billing']['billing_city']   = $city_args; 

			$district_args = array(
				'label'    => esc_html__( 'Quận/ Huyện', 'svw' ),
				'type'     => 'select',
				'required' => true,
				'options'  => SVW_Ultility::get_districts_array_by_city_id( get_user_meta( get_current_user_id(), 'billing_city', true ) ),
				'input_class' => array(
					'wc-enhanced-select svw-select-district',
				),
				'class' => array (
					0 => 'form-row-wide',
					// 1 => 'address-field',
					// 2 => 'update_totals_on_change',
				),
				'priority' => 90
			);

			$fields['shipping']['shipping_svw_district'] = $district_args;
			$fields['billing']['billing_svw_district']   = $district_args;

			$ward_args = array(
				'label'    => esc_html__( 'Xã/ Phường', 'svw' ),
				'type'     => 'select',
				'required' => true,
				'options'  => SVW_Ultility::get_wards_array_by_district_id( get_user_meta( get_current_user_id(), 'billing_svw_district', true ) ),
				'input_class' => array(
					'wc-enhanced-select svw-select-ward',
				),
				'class' => array (
					0 => 'form-row-wide',
					// 1 => 'address-field',
					2 => 'update_totals_on_change',
				),
				'priority' => 100
			);

			$fields['shipping']['shipping_svw_ward'] = $ward_args;
			$fields['billing']['billing_svw_ward']   = $ward_args;
			
			unset($fields['shipping']['shipping_postcode']);
			unset($fields['billing']['billing_postcode']);

			unset($fields['shipping']['shipping_last_name']);
			unset($fields['billing']['billing_last_name']);

			$fields['shipping']['shipping_phone']['priority'] = 30;
			$fields['billing']['billing_phone']['priority']   = 30;

			$fields['shipping']['shipping_email']['priority'] = 40;
			$fields['billing']['billing_email']['priority']   = 40;

			$fields['shipping']['shipping_country']['priority'] = 50;
			$fields['billing']['billing_country']['priority']   = 50;

			$fields['shipping']['shipping_address_1']['priority'] = 1000;
			$fields['billing']['billing_address_1']['priority']   = 1000;

			return $fields;
		}

		function svw_woocommerce_default_address_fields( $fields ) {
		    $fields['city']['label'] = esc_html__( 'Tỉnh/ Thành Phố', 'svw' );

			return $fields;
		}

		function svw_woocommerce_customer_meta_fields( $show_fields ) {
		    $show_fields['billing']['fields']['billing_svw_district'] = array(
				'label'       => esc_html__( 'Quận/ Huyện', 'svw' ),
				'description' => '',
			);
			$show_fields['billing']['fields']['billing_svw_ward'] = array(
				'label'       => esc_html__( 'Xã/ Phường', 'svw' ),
				'description' => '',
			);

			$show_fields['shipping']['fields']['shipping_svw_district'] = array(
				'label'       => esc_html__( 'Quận/ Huyện', 'svw' ),
				'description' => '',
			);
			$show_fields['shipping']['fields']['shipping_svw_ward'] = array(
				'label'       => esc_html__( 'Xã/ Phường', 'svw' ),
				'description' => '',
			);

			return $show_fields;
		}

	}

	new SVW_Custom_Fields();
}