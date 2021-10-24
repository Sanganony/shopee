<?php

if ( class_exists( 'WC_Shipping_Method' ) ) {
    class SVW_Shipping_Method_Ghn extends WC_Shipping_Method {
        /**
         * Constructor for your shipping class
         *
         * @access public
         *
         * @return void
         */
        public function __construct() {
            $this->id                 = 'svw_shipping_ghn';
            $this->method_title       = esc_html__( 'Giao Hàng Nhanh', 'svw' );
            $this->method_description = esc_html__( 'Kích hoạt tính năng ship hàng qua GHN', 'svw' );
            $this->enabled            = $this->get_option( 'enabled' );
            $this->title              = $this->get_option( 'title' );
            $this->sender_city        = $this->get_option( 'sender_city' );
            $this->sender_district    = $this->get_option( 'sender_district' );
            $this->sender_ward        = $this->get_option( 'sender_ward' );
            $this->sender_token       = $this->get_option( 'sender_token' );

            $this->init();
        }

        /**
         * Init your settings
         *
         * @access public
         * @return void
         */
        function init() {
            // Load the settings API
            $this->init_form_fields();
            $this->init_settings();

            // Save settings in admin if you have any defined
            add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        /**
         * Checking is gateway enabled or not
         *
         * @return boolean [description]
         */
        public function is_method_enabled() {
            return $this->enabled == 'yes';
        }

        public function get_sender_city() {
            return $this->sender_city;
        }

        public function get_sender_district() {
            return $this->sender_district;
        }

        public function get_sender_ward() {
            return $this->sender_ward;
        }

        /**
         * Initialise Gateway Settings Form Fields
         *
         * @access public
         * @return void
         */
        function init_form_fields() {
            
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => esc_html__( 'Kích hoạt ship qua GHN', 'svw' ),
                    'type'    => 'checkbox',
                    'label'   => esc_html__( 'Kích hoạt', 'svw' ),
                    'default' => 'no'
                ),
                'title' => array(
                    'title'       => esc_html__( 'Tiêu đề', 'svw' ),
                    'type'        => 'text',
                    'description' => esc_html__( 'Tiêu đề hiển thị khi khách hàng thanh toán.', 'svw' ),
                    'default'     => esc_html__( 'GHN', 'svw' ),
                    'desc_tip'    => true,
                ),
                'sender_name' => array(
                    'title'       => esc_html__( 'Tên người gửi hàng', 'svw' ),
                    'type'        => 'text',
                    'description' => '',
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'sender_address' => array(
                    'title'       => esc_html__( 'Địa chỉ', 'svw' ),
                    'type'        => 'text',
                    'description' => '',
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'sender_phone' => array(
                    'title'       => esc_html__( 'Số điện thoại người gửi hàng', 'svw' ),
                    'type'        => 'text',
                    'description' => '',
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'sender_city' => array(
                    'title'       => esc_html__( 'Tỉnh/ Thành Phố', 'svw' ),
                    'type'        => 'select',
                    'options'     => SVW_Ultility::get_cities_array(),
                    'description' => '',
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'sender_district' => array(
                    'title'       => esc_html__( 'Quận/Huyện', 'svw' ),
                    'type'        => 'select',
                    'description' => '',
                    'options'     => SVW_Ultility::get_districts_array_by_city_id( $this->get_sender_city() ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'sender_ward' => array(
                    'title'       => esc_html__( 'Xã/ Phường', 'svw' ),
                    'type'        => 'select',
                    'description' => '',
                    'options'     => SVW_Ultility::get_wards_array_by_district_id( $this->get_sender_district() ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'sender_token' => array(
                    'title'       => esc_html__( 'Token Giao Hàng Nhanh', 'svw' ),
                    'type'        => 'text',
                    'description' => '',
                    'default'     => '',
                    'desc_tip'    => true,
                ),
            );
        }

        /**
         * calculate_shipping function.
         *
         * @access public
         *
         * @param mixed $package
         *
         * @return void
         */
        public function calculate_shipping( $package = array() ) {

            $products       = $package['contents'];
            $FromDistrictID = $this->sender_district;
            $ToDistrictID   = $package['destination']['district'];
            $amount         = 0.0;

            if ( ! $this->is_method_enabled() ) {
                return;
            }

            if ( $products ) {
                $this->calculate_shipping_fee( $products, $FromDistrictID, $ToDistrictID );
            }
            
        }

        /**
         * Calculate shipping per seller
         *
         * @param  array $products
         * @param  array $destination
         *
         * @return float
         */
        public function calculate_shipping_fee( $products, $FromDistrictID, $ToDistrictID  ) {
            $total_weight   = 0 ;
            $product_weight = 0;

            foreach ( $products as $product ) {
                $product_data = wc_get_product( $product['product_id'] )->get_data() ;
                $weight       = $product_data['weight'];
                if ( $product['quantity'] > 1 && $weight > 0 ) {
                    $product_weight = $weight * $product['quantity'];
                } else {
                    $product_weight = $weight;
                }
                $total_weight = $total_weight + $product_weight;
            }

            $weight_unit = get_option('woocommerce_weight_unit'); 

            if ( $weight_unit == 'g' ) {
                $total_weight = $total_weight;
            } else {
                $total_weight = $total_weight*1000;
            }

            $service = array (
                'token'          => $this->sender_token,
                'Weight'         => (int) $total_weight,
                'FromDistrictID' => (int) $FromDistrictID,
                'ToDistrictID'   => (int) $ToDistrictID
            );

            $response_service = wp_remote_post( SVW_API_GHN_URL."/api/v1/apiv3/FindAvailableServices", array(
                'method'  => 'POST',
                'body'    => json_encode( $service ),
                'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
                )
            );

            if ( is_wp_error( $response_service ) ) {
                $error_message = $response_service->get_error_message();
                echo "Something went wrong: $error_message";
            } else {
                $data = json_decode( $response_service['body'] )->data;
            }

            if ( isset( $data ) && !is_wp_error( $data ) && $data ) {
                foreach ( $data as $value ) {
                    if ( isset( $value->Name ) && isset( $value->Name ) && isset( $value->ServiceFee ) ) {
                        $time = strtotime( $value->ExpectedDeliveryTime );
                        $rate = array(
                            'id'    => $this->id.'_'.$value->ServiceID,
                            'label' => $this->title.' - '.$value->Name.' ('. date( 'd/m/Y', $time ).')',
                            'cost'  => $value->ServiceFee
                        );
                        $this->add_rate( $rate );
                    }
                }
            } else {
                $rate = array(
                    'id'    => 'shipping_fee',
                    'label' => 'Phí Ship',
                    'cost'  => 0
                );
                $this->add_rate( $rate );
            }

        }
    }
}