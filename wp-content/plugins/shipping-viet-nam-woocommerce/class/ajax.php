<?php

if ( !class_exists( 'SVW_Ajax' ) ) {
	class SVW_Ajax {

		function __construct() {
			add_action( 'wp_ajax_update_checkout_district', array( $this, 'update_checkout_district' ) );
			add_action( 'wp_ajax_nopriv_update_checkout_district', array( $this, 'update_checkout_district' ) );

			add_action( 'wp_ajax_admin_update_shipping_method_district', array( $this, 'admin_update_shipping_method_district' ) );
			add_action( 'wp_ajax_nopriv_admin_update_shipping_method_district', array( $this, 'admin_update_shipping_method_district' ) );

			add_action( 'wp_ajax_update_checkout_ward', array( $this, 'update_checkout_ward' ) );
			add_action( 'wp_ajax_nopriv_update_checkout_ward', array( $this, 'update_checkout_ward' ) );

			add_action( 'wp_ajax_admin_update_shipping_method_ward', array( $this, 'admin_update_shipping_method_ward' ) );
			add_action( 'wp_ajax_nopriv_admin_update_shipping_method_ward', array( $this, 'admin_update_shipping_method_ward' ) );

			add_action( 'wp_ajax_create_order_ghn', array( $this, 'create_order_ghn' ) );
			add_action( 'wp_ajax_nopriv_create_order_ghn', array( $this, 'create_order_ghn' ) );

			add_action( 'wp_ajax_create_order_ghtk', array( $this, 'create_order_ghtk' ) );
			add_action( 'wp_ajax_nopriv_create_order_ghtk', array( $this, 'create_order_ghtk' ) );

			add_action( 'wp_ajax_get_status_order_ghn', array( $this, 'get_status_order_ghn' ) );
			add_action( 'wp_ajax_nopriv_get_status_order_ghn', array( $this, 'get_status_order_ghn' ) );

			add_action( 'wp_ajax_get_status_order_ghtk', array( $this, 'get_status_order_ghtk' ) );
			add_action( 'wp_ajax_nopriv_get_status_order_ghtk', array( $this, 'get_status_order_ghtk' ) );

			add_action( 'wp_ajax_cancel_order_ghn', array( $this, 'cancel_order_ghn' ) );
			add_action( 'wp_ajax_nopriv_cancel_order_ghn', array( $this, 'cancel_order_ghn' ) );
		}

		function update_checkout_district() {
			if ( isset( $_POST['city_id'] ) ) {
				$city_id          = $_POST['city_id'];
				WC()->session->set( 'city_id', $city_id );
				SVW_Ultility::show_districts_option_by_city_id( $city_id );
			}
			die();
		}

		function admin_update_shipping_method_district() {
			if ( isset( $_POST['city_id'] ) ) {
				$city_id          = $_POST['city_id'];
				SVW_Ultility::show_districts_option_by_city_id( $city_id );
			}
			die();
		}

		function update_checkout_ward() {
			if ( isset( $_POST['district_id'] ) ) {
				$district_id          = $_POST['district_id'];
				WC()->session->set( 'district_id', $district_id );
				SVW_Ultility::show_wards_option_by_district_id( $district_id );
			}
			die();
		}

		function admin_update_shipping_method_ward() {
			if ( isset( $_POST['district_id'] ) ) {
				$district_id          = $_POST['district_id'];
				SVW_Ultility::show_wards_option_by_district_id( $district_id );
			}
			die();
		}

		function create_order_ghn() {
            $weight_unit = get_option('woocommerce_weight_unit');
            if ( $weight_unit == 'g' ) {
                $total_weight = $_POST['total_weight']*1000;
            } else {
                $total_weight = $_POST['total_weight'];
            }
									                            
			$info_order = array (
				'token'                => $_POST['sender_token'],
				'FromDistrictID'       => (int) $_POST['sender_district'],
				'FromWardCodeoptional' => $_POST['sender_ward'],
				'ToDistrictID'         => (int) $_POST['recipient_district'],
				'ToWardCodeoptional'   => $_POST['recipient_ward'],
				'ClientContactName'    => $_POST['sender_name'],
				'ClientContactPhone'   => $_POST['sender_phone'],
				'ClientAddress'        => $_POST['sender_address'],
				'CustomerName'         => $_POST['recipient_name'],
				'CustomerPhone'        => $_POST['recipient_phone'],
				'ShippingAddress'      => $_POST['recipient_address'],
				'NoteCode'             => $_POST['recipient_note'],
				'ServiceID'            => (int) $_POST['service_id'],
				'Weight'               => $total_weight,
				'Length'               => 1,
				'Width'                => 1,
				'Height'               => 1,
				'PaymentTypeID'        => 2,
				'CoDAmount'            => (int) $_POST['cod_fee'],
				'CouponCode'           => $_POST['coupon'],
				'Note'                 => $_POST['recipient_note_extra']
	        );

			$response_service = wp_remote_post( SVW_API_GHN_URL."/api/v1/apiv3/CreateOrder", array(
				'method'  => 'POST',
				'timeout' => 5000,
				'body'    => json_encode( $info_order ),
				'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
	            )
	        );

	        if ( is_wp_error( $response_service ) ) {
	            $error_message = $response_service->get_error_message();
	            echo "Lỗi: $error_message";
	        } else {
	            $code = json_decode( $response_service['body'] )->code;
	            if ( $code ) {
	            	$data = json_decode( $response_service['body'] )->data;
	            	echo 'Đăng đơn hàng thành công! Mã đơn hàng: '.$data->OrderCode;
	            	update_post_meta( $_POST['order_id'], '_ghn_code', $data->OrderCode );
	            } else {
	            	echo json_decode( $response_service['body'] )->msg;
	            }
	        }

			die();
		}

		function create_order_ghtk() {
			$products    = array();
			$order       = wc_get_order( $_POST['order_id'] );
			$weight_unit = get_option('woocommerce_weight_unit'); 
			foreach ( $order->get_items() as $item_id => $item_data ) {
				$product      = $item_data->get_product();
				$total_weight = $product->get_weight()*$item_data->get_quantity();
				if ( $weight_unit == 'g' ) {
	                $total_weight = $total_weight/1000;
	            } else {
	                $total_weight = $total_weight;
	            }


				$products[] = array(
					'name'     => $product->get_name(),
					'weight'   => $total_weight,
					'quantity' => $item_data->get_quantity(),
				);
			}

			$info_order = array(
				'products' => $products,
				'order' => array (
					'id'            => (int) $_POST['order_id'],
					'pick_name'     => $_POST['sender_name'],
					'pick_address'  => $_POST['sender_address'],
					'pick_province' => $_POST['sender_city'],
					'pick_district' => $_POST['sender_district'],
					'pick_ward'     => $_POST['sender_ward'],
					'pick_tel'      => $_POST['sender_phone'],
					'tel'           => $_POST['recipient_phone'],
					'name'          => $_POST['recipient_name'],
					'address'       => $_POST['recipient_address'],
					'province'      => $_POST['recipient_city'],
					'district'      => $_POST['recipient_district'],
					'ward'          => $_POST['recipient_ward'],
					'is_freeship'   => 1,
					'pick_money'    => $_POST['cod_fee'],
					'note'          => $_POST['recipient_note_extra'],
			    )
			);

			$response_service = wp_remote_post( SVW_API_GHTK_URL."/services/shipment/order", array(
				'method'  => 'POST',
				'timeout' => 5000,
				'body'    => json_encode( $info_order ),
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8', 'Token' => $_POST['sender_token'] ),
	            )
	        );
	        if ( is_wp_error( $response_service ) ) {
	            $error_message = $response_service->get_error_message();
	            echo "Lỗi: $error_message";
	        } else {
	            $success = json_decode( $response_service['body'] )->success;
	            if ( $success ) {
	            	$order = json_decode( $response_service['body'] )->order;
	            	echo 'Đăng đơn hàng thành công! Mã đơn hàng: '.$order->label;
	            	update_post_meta( $_POST['order_id'], '_ghtk_code', $order->label );
	            } else {
	            	echo json_decode( $response_service['body'] )->message;
	            }
	        }

			die();
		}

		function get_status_order_ghn() {
			$ghn_code = $_POST['ghn_code'];
			$token    = $_POST['token'];
			$info_order = array (
				'token'     => $token,
				'OrderCode' => $ghn_code
	        );

			$response_service = wp_remote_post( SVW_API_GHN_URL."/api/v1/apiv3/OrderInfo", array(
				'method'  => 'POST',
				'body'    => json_encode( $info_order ),
				'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
	            )
	        );

	        if ( is_wp_error( $response_service ) ) {
	            $error_message = $response_service->get_error_message();
	            echo "Lỗi: $error_message";
	        } else {
	            $code = json_decode( $response_service['body'] )->code;
	            if ( $code ) {
	            	$data = json_decode( $response_service['body'] )->data;
	            	echo wp_kses_post( $data->CurrentStatus );
	            	if ( $data->CurrentStatus == 'Cancel' ) {
	            		delete_post_meta( $order_id, '_ghn_code' );	
	            	}
	            } else {
	            	esc_html_e( 'Đơn hàng không tồn tại hoặc đã bị xoá trên hệ thống', 'svw' );
	            }
	        }
			
			die();
		}

		function get_status_order_ghtk() {
			$ghtk_code = $_POST['ghtk_code'];
			$token     = $_POST['token'];
			$response_status = wp_remote_post( SVW_API_GHTK_URL."/services/shipment/v2/".$ghtk_code, array(
				'method'  => 'POST',
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8', 'Token' => $token ),
	            )
	        );

	        if ( is_wp_error( $response_status ) ) {
	            $error_message = $response_status->get_error_message();
	            echo "Lỗi: $error_message";
	        } else {
	            $order = json_decode( $response_status['body'] )->order;
	            if ( $order ) {
	            	echo wp_kses_post( $order->status_text );
	            } else {
	            	esc_html_e( 'Đơn hàng không tồn tại hoặc đã bị xoá trên hệ thống', 'svw' );
	            }
	        }
			
			die();
		}

		function cancel_order_ghn() {
			$ghn_code = $_POST['ghn_code'];
			$token    = $_POST['token'];
			$order_id = $_POST['order_id'];
			$info_order = array (
				'token'     => $token,
				'OrderCode' => $ghn_code
	        );

			$response_service = wp_remote_post( "http://api.serverapi.host/api/v1/apiv3/CancelOrder", array(
				'method'  => 'POST',
				'body'    => json_encode( $info_order ),
				'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
	            )
	        );

	        if ( is_wp_error( $response_service ) ) {
	            $error_message = $response_service->get_error_message();
	            echo "Lỗi: $error_message";
	        } else {
	            $code = json_decode( $response_service['body'] )->code;
	            if ( $code ) {
	            	$data = json_decode( $response_service['body'] )->data;
	            	delete_post_meta( $order_id, '_ghn_code' );
	            } else {
	            	esc_html_e( 'Đơn hàng đã huỷ', 'svw' );
	            }
	        }
			
			die();
		}

	}

	new SVW_Ajax();
}