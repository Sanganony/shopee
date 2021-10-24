<?php

class SVW_Ultility {

    public static function get_cities_array() {
        return json_decode( SVW_CITIES );
    }

    public static function get_district_array() {
        return json_decode( SVW_DISTRICTS );
    }

    public static function get_wards_array() {
        return json_decode( SVW_WARDS );
    }

    public static function get_districts_array_by_city_id( $city_id ) {
        $array = array( '' => esc_html__( 'Chọn Quận/ Huyện', 'svw' ) );
        if ( isset( $city_id ) && $city_id ) {
            if ( isset( self::get_district_array()->$city_id ) ) {
                $districts = self::get_district_array()->$city_id;
                if ( isset( $districts ) && $districts ) {
                    foreach ( $districts as $key => $value ) {
                        $array[ $key ] = $value;
                    }
                }
            }
        }
        return $array;
    }

    public static function show_districts_option_by_city_id( $city_id ) {
        echo '<option value="">'.esc_html__( 'Chọn Quận/ Huyện', 'svw' ).'</option>';
        if ( isset( $city_id ) && $city_id ) {
            $districts = self::get_district_array()->$city_id ;
            if ( isset( $districts ) && $districts ) {
                foreach ( $districts as $key => $value ) {
                    echo '<option value="'.esc_attr( $key ).'">'.esc_attr( $value ).'</option>';
                }
            }
        }
    }

    public static function show_wards_option_by_district_id( $district_id ) {
        echo '<option value="">'.esc_html__( 'Chọn Xã/ Phường', 'svw' ).'</option>';
        if ( isset( $district_id ) && $district_id ) {
            $wards = self::get_wards_array()->$district_id ;
            if ( isset( $wards ) && $wards ) {
                foreach ( $wards as $key => $value ) {
                    echo '<option value="'.esc_attr( $key ).'">'.esc_attr( $value ).'</option>';
                }
            }
        }
    }

    public static function get_wards_array_by_district_id( $district_id ) {
        $array = array( '' => esc_html__( 'Chọn Xã/ Phường', 'svw' ) );
        if ( isset( $district_id ) && $district_id ) {
            $wards = self::get_wards_array()->$district_id ;
            if ( isset( $wards ) && $wards ) {
                foreach ( $wards as $key => $value ) {
                    $array[ $key ] = $value;
                }
            }
        }
        return $array;
    }

    public static function convert_id_to_name_city( $city_id ) {
        if ( isset( $city_id ) && $city_id ) {
            $cities = self::get_cities_array();
            return $cities->$city_id;
        }
    }

    public static function convert_id_to_name_district( $city_id, $district_id ) {
        if ( isset( $city_id ) && $city_id && isset( $district_id ) && $district_id ) {
            $districts = self::get_district_array()->$city_id;
            return $districts->$district_id;
        }
    }

    public static function convert_id_to_name_ward( $district_id, $ward_id ) {
        if ( isset( $ward_id ) && $ward_id && isset( $district_id ) && $district_id ) {
            $wards = self::get_wards_array()->$district_id;
            return $wards->$ward_id;
        }
    }
}
