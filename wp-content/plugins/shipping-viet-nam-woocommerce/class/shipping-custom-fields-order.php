<?php

if ( !class_exists( 'SVW_Custom_Fields_Order' ) ) {
	class SVW_Custom_Fields_Order {

		function __construct() {
			
			// Thêm các fields quận/huyện và xã/phường và truyền id để sử dụng trong format.
			add_filter( 'woocommerce_get_order_address', array( $this, 'svw_woocommerce_get_order_address' ), 3, 999 );

			// Tuỳ chỉnh format thông tin thanh toán và thông tin giao hàng.
			add_filter( 'woocommerce_localisation_address_formats', array( $this, 'svw_woocommerce_localisation_address_formats' ), 999 );
			// Khai cách replace mảng và xử lý dữ liệu các fields mới vào, thay id bằng tên.
			add_filter( 'woocommerce_formatted_address_replacements', array( $this, 'svw_woocommerce_formatted_address_replacements' ), 2, 999 );

			// Khai báo các fields nào được hiển thị, fields nào không và label của chúng khi xem chi tiết 1 order trong admin thông qua giá trị show
			add_filter( 'woocommerce_admin_billing_fields', array( $this, 'svw_woocommerce_admin_billing_fields' ), 999 );
			add_filter( 'woocommerce_admin_shipping_fields', array( $this, 'svw_woocommerce_admin_shipping_fields' ), 999 );

			// Gõ bỏ các fields không sử dụng.
			add_filter( 'woocommerce_order_formatted_billing_address', array( $this, 'svw_woocommerce_order_formatted_billing_address' ), 999 );
			add_filter( 'woocommerce_order_formatted_shipping_address', array( $this, 'svw_woocommerce_order_formatted_shipping_address' ), 999 );

			// Thêm box tạo vận đơn vào sidebar.
			add_action( 'add_meta_boxes', array( $this, 'svw_add_meta_boxes' ), 30 );
		}

		function svw_woocommerce_get_order_address( $array, $type, $order ) {
			$shipping_district_id = get_post_meta( $order->get_id(), '_shipping_svw_district', true );
			$shipping_ward_id     = get_post_meta( $order->get_id(), '_shipping_svw_ward', true );

			$billing_district_id = get_post_meta( $order->get_id(), '_billing_svw_district', true );
			$billing_ward_id     = get_post_meta( $order->get_id(), '_billing_svw_ward', true );

			if ( $type === 'billing' ) {
				$array['svw_district'] = $billing_district_id;
				$array['svw_ward']     = $billing_ward_id;
			} elseif ( $type === 'shipping' ) {
				$array['svw_district'] = $shipping_district_id;
				$array['svw_ward']     = $shipping_ward_id;
			}

			return $array;
		}

		function svw_woocommerce_localisation_address_formats( $array ) {
			$array['default'] = "Họ Tên: {name}\nCông Ty: {company}\nĐịa Chỉ: {address_1}\nXã/Phường: {svw_ward}\nQuận/Huyện: {svw_district}\nTỉnh/Thành Phố: {city}";
			$array['VN'] = "Họ Tên: {name}\nCông Ty: {company}\nĐịa Chỉ: {address_1}\nXã/Phường: {svw_ward}\nQuận/Huyện: {svw_district}\nTỉnh/Thành Phố: {city}";

			return $array;
		}

		function svw_woocommerce_formatted_address_replacements( $array, $args ) {
			$cities    = json_decode( file_get_contents( SVW_DIR.'assets/json/cities.json') );
			$districts = json_decode( file_get_contents( SVW_DIR.'assets/json/districts.json') );
			$wards     = json_decode( file_get_contents( SVW_DIR.'assets/json/wards.json') );

			$city_id     = $args['city'];
			$district_id = $args['svw_district'];
			$ward_id     = $args['svw_ward'];

			if ( isset( $cities->$city_id ) && $cities->$city_id ) {
				$array['{city}'] = $cities->$city_id;
			}
			if ( isset( $districts->$city_id->$district_id ) && $districts->$city_id->$district_id ) {
				$array['{svw_district}'] = $districts->$city_id->$district_id;
			}
			if ( isset( $wards->$district_id->$ward_id ) && $wards->$district_id->$ward_id ) {
				$array['{svw_ward}'] = $wards->$district_id->$ward_id;
			}

			return $array;
		}

		function svw_woocommerce_admin_billing_fields( $array ) {
			$array['company']['show'] = false;

			return $array;
		}

		function svw_woocommerce_admin_shipping_fields( $array ) {
			$array['company']['show'] = false;
			$array['phone']['label']  = esc_html__( 'Điện Thoại', 'svw' );
			$array['phone']['show']   = true;

			return $array;
		}

		function svw_woocommerce_order_formatted_billing_address( $array ) {
			unset($array['address_2']);
			unset($array['state']);
			unset($array['postcode']);
			unset($array['country']);

			return $array;
		}

		function svw_woocommerce_order_formatted_shipping_address( $array ) {
			unset($array['address_2']);
			unset($array['state']);
			unset($array['postcode']);
			unset($array['country']);

			return $array;
		}

		public function svw_add_meta_boxes() {
			$screen    = get_current_screen();
			$screen_id = $screen ? $screen->id : '';

			// Orders.
			foreach ( wc_get_order_types( 'order-meta-boxes' ) as $type ) {
				$order_type_object = get_post_type_object( $type );
				add_meta_box( 'woocommerce-shipping-actions', esc_html__( 'Vận Đơn', 'svw' ), 'SVW_Custom_Fields_Order::output', $type, 'side', 'high' );
			}
		}

		public static function output( $post ) {
			?>
			<ul class="shipping_actions submitbox">

				<?php do_action( 'svw_woocommerce_shippinh_actions_start', $post->ID ); ?>

				<li class="wide" id="actions">
					<?php 
						$order                = wc_get_order( $post->ID );
						$order_data           = $order->get_data();
	
						// $shipping_district_id = get_post_meta( $post->ID, '_shipping_svw_district', true );
						// $shipping_ward_id     = get_post_meta( $post->ID, '_shipping_svw_ward', true );
						
						$billing_district_id = get_post_meta( $post->ID, '_billing_svw_district', true );
						$billing_ward_id     = get_post_meta( $post->ID, '_billing_svw_ward', true );
						$city_id             = (int) $order_data['billing']['city'];
						
						// $cities    = json_decode( file_get_contents( SVW_DIR.'assets/json/cities.json') );
						// $districts = json_decode( file_get_contents( SVW_DIR.'assets/json/districts.json') );

						if ( isset( $billing_district_id ) && isset( $billing_ward_id ) && $billing_district_id && $billing_ward_id ) :						
						$billing_first_name = $order_data['billing']['first_name'];
						$billing_last_name  = $order_data['billing']['last_name'];

						$recipient_name     = $billing_first_name.' '.$billing_last_name;
						$recipient_address  = $order_data['billing']['address_1'];
						$recipient_phone    = $order_data['billing']['phone'];
						$recipient_city     = SVW_Ultility::convert_id_to_name_city( $city_id );
						$recipient_district = SVW_Ultility::convert_id_to_name_district( $city_id, $billing_district_id );
						$recipient_ward     = SVW_Ultility::convert_id_to_name_ward( $billing_district_id, $billing_ward_id );

						$total_weight = 0;

					    foreach( $order->get_items() as $item_id => $product_item ){
							$quantity       = $product_item->get_quantity(); // get quantity
							$product        = $product_item->get_product(); // get the WC_Product object
							$product_weight = $product->get_weight(); // get the product weight
							// Add the line item weight to the total weight calculation
							$total_weight += floatval( $product_weight * $quantity );
					    }

					    foreach( $order->get_items( 'shipping' ) as $item_id => $shipping_item_obj ){
							$order_item_name           = $shipping_item_obj->get_name();
						    $order_item_type           = $shipping_item_obj->get_type();
						    $shipping_method_title     = $shipping_item_obj->get_method_title();
						    $shipping_method_id        = $shipping_item_obj->get_method_id(); // The method ID
						    $shipping_method_total     = $shipping_item_obj->get_total();
						    $shipping_method_total_tax = $shipping_item_obj->get_total_tax();
						    $shipping_method_taxes     = $shipping_item_obj->get_taxes();
						}

						$data = explode( '_', $shipping_method_id );
						$service_id = end( $data );
						// Nếu sử dụng giao hàng nhanh
						
						if ( $service_id != 'ghtk' ) :
							$sender_data_ghn = new SVW_Shipping_Method_Ghn();
							if ( isset( $sender_data_ghn->settings['sender_name'] ) && 
								isset( $sender_data_ghn->settings['sender_address'] ) && 
								isset( $sender_data_ghn->settings['sender_phone'] ) && 
								isset( $sender_data_ghn->settings['sender_city'] ) && 
								isset( $sender_data_ghn->settings['sender_district'] ) && 
								isset( $sender_data_ghn->settings['sender_ward'] ) && 
								isset( $sender_data_ghn->settings['sender_token'] ) && 
								$sender_data_ghn->settings['sender_name'] && 
								$sender_data_ghn->settings['sender_address'] && 
								$sender_data_ghn->settings['sender_phone'] &&
								$sender_data_ghn->settings['sender_city'] &&
								$sender_data_ghn->settings['sender_district'] &&
								$sender_data_ghn->settings['sender_ward'] &&
								$sender_data_ghn->settings['sender_token']
							) :

								$sender_district_id = $sender_data_ghn->settings['sender_district'];
								$sender_ward_id     = $sender_data_ghn->settings['sender_ward'];
								$sender_city_id     = (int) $sender_data_ghn->settings['sender_city'];

								$sender_name     = $sender_data_ghn->settings['sender_name'];
								$sender_address  = $sender_data_ghn->settings['sender_address'];
								$sender_phone    = $sender_data_ghn->settings['sender_phone'];
								$sender_token    = $sender_data_ghn->settings['sender_token'];
								$sender_city     = SVW_Ultility::convert_id_to_name_city( $sender_city_id );
								$sender_district = SVW_Ultility::convert_id_to_name_district( $sender_city_id, $sender_district_id );
								$sender_ward     = SVW_Ultility::convert_id_to_name_ward( $sender_district_id, $sender_ward_id );

								$cod_fee = $order_data['total'] - $shipping_method_total;

								$ghn_code = get_post_meta( $post->ID, '_ghn_code', true );
								if ( !$ghn_code ) :
					?>
									<?php add_thickbox(); ?>
									<div class="svw-create-order">
										<a href="#TB_inline?width=600&height=550&inlineId=svw-modal-create-order-shipping-ghn" title="TẠO VẬN ĐƠN GHN" class="thickbox button turquoise active"><?php esc_html_e('TẠO VẬN ĐƠN GHN', 'svw' ); ?></a>
									</div>
									<div id="svw-modal-create-order-shipping-ghn" style="display:none;">
									    <div id="create_order">
									    	<div class="svw-row">
								                <div class="svw-col-6 sender">
								                    <div class="svw-col-12">
								                        <div class="title"><?php esc_html_e( 'Người gửi', 'svw' ); ?></div>
								                    </div>
								                    <div class="sub-content">
														<div class="svw-row item">
								                            <div class="svw-col-5">
								                                <label><?php esc_html_e( 'Họ Tên:', 'svw' ); ?></label>
								                            </div>
								                            <div class="svw-col-7">
								                                <?php echo wp_kses_post( $sender_name ); ?>
								                            </div>
								                        </div>
								                        <div class="svw-row item">
								                            <div class="svw-col-5">
								                                <label><?php esc_html_e( 'Số Điện Thoại:', 'svw' ); ?></label>
								                            </div>
								                            <div class="svw-col-7">
								                                <?php echo wp_kses_post( $sender_phone ); ?>
								                            </div>
								                        </div>
								                        
								                        <div class="svw-row item">
								                            <div class="svw-col-5">
								                                <label><?php esc_html_e( 'Địa Chỉ:', 'svw' ); ?></label>
								                            </div>
								                            <div class="svw-col-7">
								                                <?php echo wp_kses_post( $sender_address ); ?>
								                            </div>
								                        </div>
								                        <div class="svw-row item">
								                            <div class="svw-col-5">
								                                <label><?php esc_html_e( 'Xã/Phường:', 'svw' ); ?></label>
								                            </div>
								                            <div class="svw-col-7">
																<?php echo wp_kses_post( $sender_ward ); ?>
								                            </div>
								                        </div>
								                        <div class="svw-row item">
								                            <div class="svw-col-5">
								                                <label><?php esc_html_e( 'Quận/ Huyện:', 'svw' ); ?></label>
								                            </div>
								                            <div class="svw-col-7">
								                                <?php echo wp_kses_post( $sender_district ); ?>
								                            </div>
								                        </div>
								                        <div class="svw-row item">
								                            <div class="svw-col-5">
								                                <label><?php esc_html_e( 'Tỉnh/Thành Phố:', 'svw' ); ?></label>
								                            </div>
								                            <div class="svw-col-7">
								                                <?php echo wp_kses_post( $sender_city ); ?>
								                            </div>
								                        </div>
								                    </div>
								                </div>
								                <div class="svw-col-6 recipient">
								                    <div class="svw-col-12">
								                        <div class="title"><?php esc_html_e( 'Người nhận', 'svw' ); ?></div>
								                        <i class="fa fa-angle-up"></i>
								                    </div>
								                    <div class="sub-content">
								                        <div class="svw-row item">
								                            <div class="svw-col-5">
								                                <label><?php esc_html_e( 'Họ Tên:', 'svw' ); ?></label>
								                            </div>
								                            <div class="svw-col-7">
								                                <?php echo wp_kses_post( $billing_first_name.' '.$billing_last_name ); ?>
								                            </div>
								                        </div>
								                        <div class="svw-row item">
								                            <div class="svw-col-5">
								                                <label><?php esc_html_e( 'Số Điện Thoại:', 'svw' ); ?></label>
								                            </div>
								                            <div class="svw-col-7">
								                                <?php echo wp_kses_post( $recipient_phone ); ?>
								                            </div>
								                        </div>
								                        <div class="svw-row item">
								                            <div class="svw-col-5">
								                                <label><?php esc_html_e( 'Địa Chỉ:', 'svw' ); ?></label>
								                            </div>
								                           	<div class="svw-col-7">
								                                <?php echo wp_kses_post( $recipient_address ); ?>
								                            </div>
								                        </div>
								                        <div class="svw-row item">
								                            <div class="svw-col-5">
								                                <label><?php esc_html_e( 'Xã/ Phường:', 'svw' ); ?></label>
								                            </div>
								                            <div class="svw-col-7">
								                                <?php echo wp_kses_post( $recipient_ward ); ?>
								                            </div>
								                        </div>
								                        <div class="svw-row item">
								                            <div class="svw-col-5">
								                                <label><?php esc_html_e( 'Quận/ Huyện:', 'svw' ); ?></label>
								                            </div>
								                            <div class="svw-col-7">
								                                <?php echo wp_kses_post( $recipient_district ); ?>
								                            </div>
								                        </div>
								                       	<div class="svw-row item">
								                            <div class="svw-col-5">
								                                <label><?php esc_html_e( 'Tỉnh/ Thành Phố:', 'svw' ); ?></label>
								                            </div>
								                            <div class="svw-col-7">
								                                <?php echo wp_kses_post( $recipient_city ); ?>
								                            </div>
								                        </div>
								                    </div>
								                </div>
								            </div>
								            <div class="svw-row">
								                <div class="svw-col-6 parcel">
								                    <div class="svw-col-12">
								                        <div class="title"><?php esc_html_e( 'Gói Hàng', 'svw' ); ?></div>
								                        <i class="fa fa-angle-up"></i>
								                    </div>
								                    <div class="sub-content">
								                        <div class="svw-row">
								                            <div class="svw-col-4">
								                                <label><?php esc_html_e( 'Mã Đơn Hàng:', 'svw' ); ?></label>
								                            </div>
								                            <div class="svw-col-8">
								                                <?php echo wp_kses_post( '#'.$post->ID ); ?>
								                            </div>
								                        </div>
								                       	<div class="svw-row">
								                            <div class="svw-col-4">
								                                <label><?php esc_html_e( 'Khối Lượng', 'svw' ); ?></label>
								                            </div>
								                            <div class="svw-col-8">
								                                <?php 
									                                $weight_unit = get_option('woocommerce_weight_unit');
									                                if ( $weight_unit == 'g' ) {
														                $total_weight = $total_weight/1000;
														            } else {
														                $total_weight = $total_weight;
														            }
									                                echo wp_kses_post( $total_weight );
								                                ?> kg
								                            </div>
								                        </div>
								                        <div class="svw-row">
								                            <div class="svw-col-4">
								                                <label>Chú ý*:</label>
								                            </div>
								                            <div class="svw-col-8">
								                                <select name="recipient_note">
								                                	<option value="KHONGCHOXEMHANG"><?php esc_html_e( 'Không Cho Xem Hàng', 'svw' ); ?></option>
								                                	<option value="CHOXEMHANGKHONGTHU"><?php esc_html_e( 'Cho Xem Hàng, Không Thử', 'svw' ); ?></option>
								                                	<option value="CHOTHUHANG"><?php esc_html_e( 'Cho Thử Hàng', 'svw' ); ?></option>
								                                </select> 
								                            </div>
								                        </div>
								                    </div>
								                </div>
								                <div class="svw-col-6 package">
								                    <div class="svw-col-12">
								                        <div class="title"><?php esc_html_e( 'Gói Cước', 'svw' ); ?></div>
								                        <i class="fa fa-angle-up"></i>
								                    </div>
								                    <div class="sub-content">
							                            <div class="svw-row">
							                                <div class="svw-col-7">
							                                    <label for="#expenses"><?php esc_html_e( 'Tiền Thu Hộ (COD):', 'svw' ); ?></label>
							                                </div>
							                                <div class="svw-col-5">
							                                    <?php echo wp_kses_post( $cod_fee ); ?> VNĐ
							                                </div>
							                            </div>
							                            <div class="svw-row">
							                                <div class="svw-col-7">
							                                    <label><?php esc_html_e( 'Mã Khuyến Mãi:', 'svw' ); ?></label>
							                                </div>
							                                <div class="svw-col-5">
							                                    <input type="text" name="coupon" value"">
							                                </div>
							                            </div>
								                    </div>
								                </div>
								                <div class="svw-col-12">
						                            <div class="svw-col-12">
						                                <label><?php esc_html_e( 'Ghi Chú:', 'svw' ); ?></label>
						                            </div>
						                            <div class="svw-col-12">
						                                <textarea name="recipient_note_extra"></textarea>
						                            </div>
						                        </div>
								            </div>
							                <div class="svw-row">
							                    <div class="svw-col-12">
							                        <div class="title"><?php esc_html_e( 'Cước Phí', 'svw' ); ?></div>
							                        <div class="desc"><?php esc_html_e( 'Thời gian và chi phí giao hàng được tính tại thời điểm khách hàng đặt hàng. Chi phí và thời gian giao hàng dự kiến có thể sẽ thay đổi nếu GHN thay đổi biểu phí tại thời điểm tạo vận đơn', 'svw' ); ?></div>
							                    </div>
							                    <div class="sub-content">
							                        <div class="svw-row">
							                            <div class="svw-col-4">
							                                <label><?php esc_html_e( 'Tổng:', 'svw' ); ?></label>
							                            </div>
							                            <div class="svw-col-8">
							                                <?php echo wp_kses_post( $order_data['total'] ); ?> VNĐ
							                            </div>
							                        </div>
							                        <div class="svw-row">
							                            <div class="svw-col-4">
							                                <label><?php esc_html_e( 'Phí Vận Chuyển:', 'svw' ); ?></label>
							                            </div>
							                            <div class="svw-col-8">
							                                <?php echo wp_kses_post( $shipping_method_total ) ?> - <?php echo wp_kses_post( $shipping_method_title ) ?>
							                            </div>
							                        </div>
							                    </div>
							                </div>
							                <button id="svw_create_order_ghn" class="button button-primary" name="save" value="Đăng Đơn" 
							                data-recipient_name="<?php echo esc_attr( $recipient_name ); ?>" 
							                data-recipient_address="<?php echo esc_attr( $recipient_address ); ?>" 
							                data-recipient_phone="<?php echo esc_attr( $recipient_phone ); ?>" 
							                data-recipient_city="<?php echo esc_attr( $city_id ); ?>" 
							                data-recipient_district="<?php echo esc_attr( $billing_district_id ); ?>" 
							                data-recipient_ward="<?php echo esc_attr( $billing_ward_id ); ?>" 
							                data-sender_name="<?php echo esc_attr( $sender_name ); ?>" 
							                data-sender_address="<?php echo esc_attr( $sender_address ); ?>" 
							                data-sender_phone="<?php echo esc_attr( $sender_phone ); ?>" 
							                data-sender_city="<?php echo esc_attr( $sender_city_id ); ?>" 
							                data-sender_district="<?php echo esc_attr( $sender_district_id ); ?>" 
							                data-sender_ward="<?php echo esc_attr( $sender_ward_id ); ?>" 
							                data-sender_token="<?php echo esc_attr( $sender_token ); ?>" 
							                data-cod_fee="<?php echo esc_attr( $cod_fee ); ?>"
							                data-service_id="<?php echo esc_attr( $service_id ); ?>" 
							                data-total_weight="<?php echo esc_attr( $total_weight ); ?>"
							                data-order_id="<?php echo esc_attr( $post->ID ); ?>"
							                ><?php esc_html_e( 'Đăng Đơn', 'svw' ); ?></button>
							                <div class="svw-row">
							                	<div class="svw-col-12">
							                		<div class="svw-response"></div>
							                	</div>
							                </div>
							            </div>
									</div>
								<?php else: // nếu đã tạo vận đơn rồi ?>
									<div class="svw-exits">
										<div class="button purple active"><?php echo esc_html__( 'GHN: ', 'svw' ).$ghn_code; ?></div>
										<div class="svw-ghn-status button orange active" data-ghn_code=<?php echo esc_attr( $ghn_code ); ?> data-token="<?php echo esc_attr( $sender_token ); ?>"><?php esc_html_e( 'Trạng Thái:', 'svw' ); ?> <span></span> </div>
									</div>
								<?php endif; ?>
							<?php 
							else :
								esc_html_e( 'Bạn chưa nhập đầy đủ thông tin người gửi hàng hoặc chưa kích hoạt phương thức này trong cài đặt Giao Hàng Nhanh', 'svw' );
							endif; 
							?>
					<?php 
						else : // nếu sử dụng giao hàng tiết kiệm 
							$sender_data_ghtk = new SVW_Shipping_Method_Ghtk();
							if ( isset( $sender_data_ghtk->settings['sender_name'] ) && 
								isset( $sender_data_ghtk->settings['sender_address'] ) && 
								isset( $sender_data_ghtk->settings['sender_phone'] ) && 
								isset( $sender_data_ghtk->settings['sender_city'] ) && 
								isset( $sender_data_ghtk->settings['sender_district'] ) && 
								isset( $sender_data_ghtk->settings['sender_ward'] ) && 
								isset( $sender_data_ghtk->settings['sender_token'] ) 
							) :

								$sender_district_id = $sender_data_ghtk->settings['sender_district'];
								$sender_ward_id     = $sender_data_ghtk->settings['sender_ward'];
								$sender_city_id     = (int) $sender_data_ghtk->settings['sender_city'];

								$sender_name     = $sender_data_ghtk->settings['sender_name'];
								$sender_address  = $sender_data_ghtk->settings['sender_address'];
								$sender_phone    = $sender_data_ghtk->settings['sender_phone'];
								$sender_token    = $sender_data_ghtk->settings['sender_token'];
								$sender_city     = SVW_Ultility::convert_id_to_name_city( $sender_city_id );
								$sender_district = SVW_Ultility::convert_id_to_name_district( $sender_city_id, $sender_district_id );
								$sender_ward     = SVW_Ultility::convert_id_to_name_ward( $sender_district_id, $sender_ward_id );

								$cod_fee = $order_data['total'] - $shipping_method_total;
								$ghtk_code = get_post_meta( $post->ID, '_ghtk_code', true );
								if ( !$ghtk_code ) :
					?>
								<?php add_thickbox(); ?>
										<div class="svw-create-order">
											<a href="#TB_inline?width=600&height=550&inlineId=svw-modal-create-order-shipping-ghtk" title="TẠO VẬN ĐƠN GHTK" class="thickbox button turquoise active"><?php esc_html_e('TẠO VẬN ĐƠN GHTK', 'svw' ); ?></a>
										</div>
										<div id="svw-modal-create-order-shipping-ghtk" style="display:none;">
										    <div id="create_order">
										    	<div class="svw-row">
									                <div class="svw-col-6 sender">
									                    <div class="svw-col-12">
									                        <div class="title"><?php esc_html_e( 'Người gửi', 'svw' ); ?></div>
									                    </div>
									                    <div class="sub-content">
															<div class="svw-row item">
									                            <div class="svw-col-5">
									                                <label><?php esc_html_e( 'Họ Tên:', 'svw' ); ?></label>
									                            </div>
									                            <div class="svw-col-7">
									                                <?php echo wp_kses_post( $sender_name ); ?>
									                            </div>
									                        </div>
									                        <div class="svw-row item">
									                            <div class="svw-col-5">
									                                <label><?php esc_html_e( 'Số Điện Thoại:', 'svw' ); ?></label>
									                            </div>
									                            <div class="svw-col-7">
									                                <?php echo wp_kses_post( $sender_phone ); ?>
									                            </div>
									                        </div>
									                        
									                        <div class="svw-row item">
									                            <div class="svw-col-5">
									                                <label><?php esc_html_e( 'Địa Chỉ:', 'svw' ); ?></label>
									                            </div>
									                            <div class="svw-col-7">
									                                <?php echo wp_kses_post( $sender_address ); ?>
									                            </div>
									                        </div>
									                        <div class="svw-row item">
									                            <div class="svw-col-5">
									                                <label><?php esc_html_e( 'Xã/Phường:', 'svw' ); ?></label>
									                            </div>
									                            <div class="svw-col-7">
																	<?php echo wp_kses_post( $sender_ward ); ?>
									                            </div>
									                        </div>
									                        <div class="svw-row item">
									                            <div class="svw-col-5">
									                                <label><?php esc_html_e( 'Quận/ Huyện:', 'svw' ); ?></label>
									                            </div>
									                            <div class="svw-col-7">
									                                <?php echo wp_kses_post( $sender_district ); ?>
									                            </div>
									                        </div>
									                        <div class="svw-row item">
									                            <div class="svw-col-5">
									                                <label><?php esc_html_e( 'Tỉnh/Thành Phố:', 'svw' ); ?></label>
									                            </div>
									                            <div class="svw-col-7">
									                                <?php echo wp_kses_post( $sender_city ); ?>
									                            </div>
									                        </div>
									                    </div>
									                </div>
									                <div class="svw-col-6 recipient">
									                    <div class="svw-col-12">
									                        <div class="title"><?php esc_html_e( 'Người nhận', 'svw' ); ?></div>
									                        <i class="fa fa-angle-up"></i>
									                    </div>
									                    <div class="sub-content">
									                        <div class="svw-row item">
									                            <div class="svw-col-5">
									                                <label><?php esc_html_e( 'Họ Tên:', 'svw' ); ?></label>
									                            </div>
									                            <div class="svw-col-7">
									                                <?php echo wp_kses_post( $billing_first_name.' '.$billing_last_name ); ?>
									                            </div>
									                        </div>
									                        <div class="svw-row item">
									                            <div class="svw-col-5">
									                                <label><?php esc_html_e( 'Số Điện Thoại:', 'svw' ); ?></label>
									                            </div>
									                            <div class="svw-col-7">
									                                <?php echo wp_kses_post( $recipient_phone ); ?>
									                            </div>
									                        </div>
									                        <div class="svw-row item">
									                            <div class="svw-col-5">
									                                <label><?php esc_html_e( 'Địa Chỉ:', 'svw' ); ?></label>
									                            </div>
									                           	<div class="svw-col-7">
									                                <?php echo wp_kses_post( $recipient_address ); ?>
									                            </div>
									                        </div>
									                        <div class="svw-row item">
									                            <div class="svw-col-5">
									                                <label><?php esc_html_e( 'Xã/ Phường:', 'svw' ); ?></label>
									                            </div>
									                            <div class="svw-col-7">
									                                <?php echo wp_kses_post( $recipient_ward ); ?>
									                            </div>
									                        </div>
									                        <div class="svw-row item">
									                            <div class="svw-col-5">
									                                <label><?php esc_html_e( 'Quận/ Huyện:', 'svw' ); ?></label>
									                            </div>
									                            <div class="svw-col-7">
									                                <?php echo wp_kses_post( $recipient_district ); ?>
									                            </div>
									                        </div>
									                       	<div class="svw-row item">
									                            <div class="svw-col-5">
									                                <label><?php esc_html_e( 'Tỉnh/ Thành Phố:', 'svw' ); ?></label>
									                            </div>
									                            <div class="svw-col-7">
									                                <?php echo wp_kses_post( $recipient_city ); ?>
									                            </div>
									                        </div>
									                    </div>
									                </div>
									            </div>
									            <div class="svw-row">
									                <div class="svw-col-6 parcel">
									                    <div class="svw-col-12">
									                        <div class="title"><?php esc_html_e( 'Gói Hàng', 'svw' ); ?></div>
									                        <i class="fa fa-angle-up"></i>
									                    </div>
									                    <div class="sub-content">
									                        <div class="svw-row">
									                            <div class="svw-col-4">
									                                <label><?php esc_html_e( 'Mã Đơn Hàng:', 'svw' ); ?></label>
									                            </div>
									                            <div class="svw-col-8">
									                                <?php echo wp_kses_post( '#'.$post->ID ); ?>
									                            </div>
									                        </div>
									                       	<div class="svw-row">
									                            <div class="svw-col-4">
									                                <label><?php esc_html_e( 'Khối Lượng', 'svw' ); ?></label>
									                            </div>
									                            <div class="svw-col-8">
									                                <?php 
										                                $weight_unit = get_option('woocommerce_weight_unit');
										                                if ( $weight_unit == 'g' ) {
															                $total_weight = $total_weight/1000;
															            } else {
															                $total_weight = $total_weight;
															            }
										                                echo wp_kses_post( $total_weight );
									                                ?> kg
									                            </div>
									                        </div>
									                    </div>
									                </div>
									                <div class="svw-col-6 package">
									                    <div class="svw-col-12">
									                        <div class="title"><?php esc_html_e( 'Gói Cước', 'svw' ); ?></div>
									                        <i class="fa fa-angle-up"></i>
									                    </div>
									                    <div class="sub-content">
								                            <div class="svw-row">
								                                <div class="svw-col-7">
								                                    <label for="#expenses"><?php esc_html_e( 'Tiền Thu Hộ (COD):', 'svw' ); ?></label>
								                                </div>
								                                <div class="svw-col-5">
								                                    <?php echo wp_kses_post( $cod_fee ); ?> VNĐ
								                                </div>
								                            </div>
									                    </div>
									                </div>
									                <div class="svw-col-12">
							                            <div class="svw-col-12">
							                                <label><?php esc_html_e( 'Ghi Chú:', 'svw' ); ?></label>
							                            </div>
							                            <div class="svw-col-12">
							                                <textarea name="recipient_note_extra"></textarea>
							                            </div>
							                        </div>
									            </div>
								                <div class="svw-row">
								                    <div class="svw-col-12">
								                        <div class="title"><?php esc_html_e( 'Cước Phí', 'svw' ); ?></div>
								                        <div class="desc"><?php esc_html_e( 'Thời gian và chi phí giao hàng được tính tại thời điểm khách hàng đặt hàng. Chi phí và thời gian giao hàng dự kiến có thể sẽ thay đổi nếu GHTK thay đổi biểu phí tại thời điểm tạo vận đơn', 'svw' ); ?></div>
								                    </div>
								                    <div class="sub-content">
								                        <div class="svw-row">
								                            <div class="svw-col-4">
								                                <label><?php esc_html_e( 'Tổng:', 'svw' ); ?></label>
								                            </div>
								                            <div class="svw-col-8">
								                                <?php echo wp_kses_post( $order_data['total'] ); ?> VNĐ
								                            </div>
								                        </div>
								                        <div class="svw-row">
								                            <div class="svw-col-4">
								                                <label><?php esc_html_e( 'Phí Vận Chuyển:', 'svw' ); ?></label>
								                            </div>
								                            <div class="svw-col-8">
								                                <?php echo wp_kses_post( $shipping_method_total ) ?> - <?php echo wp_kses_post( $shipping_method_title ) ?>
								                            </div>
								                        </div>
								                    </div>
								                </div>
								                <button id="svw_create_order_ghtk" class="button button-primary" name="save" value="Đăng Đơn" 
								                data-recipient_name="<?php echo esc_attr( $recipient_name ); ?>" 
								                data-recipient_address="<?php echo esc_attr( $recipient_address ); ?>" 
								                data-recipient_phone="<?php echo esc_attr( $recipient_phone ); ?>" 
								                data-recipient_city="<?php echo esc_attr( $recipient_city ); ?>" 
								                data-recipient_district="<?php echo esc_attr( $recipient_district ); ?>" 
								                data-recipient_ward="<?php echo esc_attr( $recipient_ward ); ?>" 
								                data-sender_name="<?php echo esc_attr( $sender_name ); ?>" 
								                data-sender_address="<?php echo esc_attr( $sender_address ); ?>" 
								                data-sender_phone="<?php echo esc_attr( $sender_phone ); ?>" 
								                data-sender_city="<?php echo esc_attr( $sender_city ); ?>" 
								                data-sender_district="<?php echo esc_attr( $sender_district ); ?>" 
								                data-sender_ward="<?php echo esc_attr( $sender_ward ); ?>" 
								                data-sender_token="<?php echo esc_attr( $sender_token ); ?>" 
								                data-cod_fee="<?php echo esc_attr( $cod_fee ); ?>"
								                data-total_weight="<?php echo esc_attr( $total_weight ); ?>"
								                data-order_id="<?php echo esc_attr( $post->ID ); ?>"
								                ><?php esc_html_e( 'Đăng Đơn', 'svw' ); ?></button>
								                <div class="svw-row">
								                	<div class="svw-col-12">
								                		<div class="svw-response"></div>
								                	</div>
								                </div>
								            </div>
										</div>
								<?php else : ?>
									<div class="svw-exits">
										<div class="button purple active"><?php echo esc_html__( 'GHTK: ', 'svw' ).$ghtk_code; ?></div>
										<div class="svw-ghtk-status button orange active" data-ghtk_code=<?php echo esc_attr( $ghtk_code ); ?> data-token="<?php echo esc_attr( $sender_token ); ?>"><?php esc_html_e( 'Trạng Thái:', 'svw' ); ?> <span></span> </div>
									</div>
								<?php endif; ?>
							<?php 
							else :
								esc_html_e( 'Bạn chưa nhập đầy đủ thông tin người gửi hàng hoặc chưa kích hoạt phương thức này trong cài đặt Giao Hàng Tiết Kiệm.', 'svw' );
							endif; 
							?>
					<?php endif; ?>
					<?php else: 
						esc_html_e( 'Bạn không thể tạo vận đơn vì đơn hàng này được tạo trước khi active Shipping Viet Nam WooCommerce.', 'svw' );
					endif; ?>
				</li>

				<?php do_action( 'svw_woocommerce_shipping_actions_end', $post->ID ); ?>

			</ul>
			<?php
		}
	}

	new SVW_Custom_Fields_Order();
}