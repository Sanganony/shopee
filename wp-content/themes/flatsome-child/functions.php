<?php
// Add custom Theme Functions here


function add_file_types_to_uploads($file_types){
$new_filetypes = array();
$new_filetypes['svg'] = 'image/svg+xml';
$file_types = array_merge($file_types, $new_filetypes );
return $file_types;
}
add_filter('upload_mimes', 'add_file_types_to_uploads');


/*
 * WordPress Breadcrumbs
 * author: Dimox - Edit TruongManh.Net
*/
function vpw_breadcrumbs() {
    /* === OPTIONS === */
    $text['home']     = 'Trang chủ'; // text for the 'Home' link
    $text['category'] = '%s'; // text for a category page
    $text['search']   = 'Kết quả tìm kiếm %s'; // text for a search results page
    $text['tag']      = 'Từ khóa %s'; // text for a tag page
    $text['author']   = 'Tất cả bài viết của %s'; // text for an author page
    $text['404']      = 'Lỗi 404'; // text for the 404 page
    $text['page']     = 'Trang %s'; // text 'Page N'
    $text['cpage']    = 'Trang bình luận %s'; // text 'Comment Page N'
    $wrap_before    = '
 
 
<div class="breadcrumb" itemscope itemtype="http://schema.org/BreadcrumbList">'; // the opening wrapper tag
    $wrap_after     = '</div>
 
 
 
<!-- .breadcrumbs -->'; // the closing wrapper tag
    $sep            = '›'; // separator between crumbs
    $sep_before     = '<span class="sep">'; // tag before separator
    $sep_after      = '</span>'; // tag after separator
    $show_home_link = 1; // 1 - show the 'Home' link, 0 - don't show
    $show_on_home   = 0; // 1 - show breadcrumbs on the homepage, 0 - don't show
    $show_current   = 1; // 1 - show current page title, 0 - don't show
    $before         = '<span class="current vpw_breadcrumbs">'; // tag before the current crumb
    $after          = '</span>'; // tag after the current crumb
    /* === END OF OPTIONS === */
    global $post;
    $home_url       = home_url('/');
    $link_before    = '<span itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">';
    $link_after     = '</span>';
    $link_attr      = ' itemprop="item"';
    $link_in_before = '<span itemprop="name" class="vpw_breadcrumbs">';
    $link_in_after  = '</span>';
    $link           = $link_before . '<a href="%1$s"' . $link_attr . '>' . $link_in_before . '%2$s' . $link_in_after . '</a>' . $link_after;
    $frontpage_id   = get_option('page_on_front');
    $parent_id      = ($post) ? $post->post_parent : '';
    $sep            = ' ' . $sep_before . $sep . $sep_after . ' ';
    $home_link      = $link_before . '<a href="' . $home_url . '"' . $link_attr . ' class="home">' . $link_in_before . $text['home'] . $link_in_after . '</a>' . $link_after;
    if (is_home() || is_front_page()) {
        if ($show_on_home) echo $wrap_before . $home_link . $wrap_after;
    } else {
        echo $wrap_before;
        if ($show_home_link) echo $home_link;
        if ( is_category() ) {
            $cat = get_category(get_query_var('cat'), false);
            if ($cat->parent != 0) {
                $cats = get_category_parents($cat->parent, TRUE, $sep);
                $cats = preg_replace("#^(.+)$sep$#", "$1", $cats);
                $cats = preg_replace('#<a([^>]+)>([^<]+)</a>#', $link_before . '<a$1' . $link_attr .'>' . $link_in_before . '$2' . $link_in_after .'</a>' . $link_after, $cats);
                if ($show_home_link) echo $sep;
                echo $cats;
            }
            if ( get_query_var('paged') ) {
                $cat = $cat->cat_ID;
                echo $sep . sprintf($link, get_category_link($cat), get_cat_name($cat)) . $sep . $before . sprintf($text['page'], get_query_var('paged')) . $after;
            } else {
                if ($show_current) echo $sep . $before . sprintf($text['category'], single_cat_title('', false)) . $after;
            }
        } elseif ( is_search() ) {
            if (have_posts()) {
                if ($show_home_link && $show_current) echo $sep;
                if ($show_current) echo $before . sprintf($text['search'], get_search_query()) . $after;
            } else {
                if ($show_home_link) echo $sep;
                echo $before . sprintf($text['search'], get_search_query()) . $after;
            }
        } elseif ( is_day() ) {
            if ($show_home_link) echo $sep;
            echo sprintf($link, get_year_link(get_the_time('Y')), get_the_time('Y')) . $sep;
            echo sprintf($link, get_month_link(get_the_time('Y'), get_the_time('m')), get_the_time('F'));
            if ($show_current) echo $sep . $before . get_the_time('d') . $after;
        } elseif ( is_month() ) {
            if ($show_home_link) echo $sep;
            echo sprintf($link, get_year_link(get_the_time('Y')), get_the_time('Y'));
            if ($show_current) echo $sep . $before . get_the_time('F') . $after;
        } elseif ( is_year() ) {
            if ($show_home_link && $show_current) echo $sep;
            if ($show_current) echo $before . get_the_time('Y') . $after;
        } elseif ( is_single() && !is_attachment() ) {
            if ($show_home_link) echo $sep;
            if ( get_post_type() != 'post' ) {
                $post_type = get_post_type_object(get_post_type());
                $slug = $post_type->rewrite;
                printf($link, $home_url . $slug['slug'] . '/', $post_type->labels->singular_name);
                if ($show_current) echo $sep . $before . get_the_title() . $after;
            } else {
                $cat = get_the_category(); $cat = $cat[0];
                $cats = get_category_parents($cat, TRUE, $sep);
                if (!$show_current || get_query_var('cpage')) $cats = preg_replace("#^(.+)$sep$#", "$1", $cats);
                $cats = preg_replace('#<a([^>]+)>([^<]+)</a>#', $link_before . '<a$1' . $link_attr .'>' . $link_in_before . '$2' . $link_in_after .'</a>' . $link_after, $cats);
                echo $cats;
                if ( get_query_var('cpage') ) {
                    echo $sep . sprintf($link, get_permalink(), get_the_title()) . $sep . $before . sprintf($text['cpage'], get_query_var('cpage')) . $after;
                } else {
                    if ($show_current) echo $before . get_the_title() . $after;
                }
            }
        // custom post type
        } elseif ( !is_single() && !is_page() && get_post_type() != 'post' && !is_404() ) {
            $post_type = get_post_type_object(get_post_type());
            if ( get_query_var('paged') ) {
                echo $sep . sprintf($link, get_post_type_archive_link($post_type->name), $post_type->label) . $sep . $before . sprintf($text['page'], get_query_var('paged')) . $after;
            } else {
                if ($show_current) echo $sep . $before . $post_type->label . $after;
            }
        } elseif ( is_attachment() ) {
            if ($show_home_link) echo $sep;
            $parent = get_post($parent_id);
            $cat = get_the_category($parent->ID); $cat = $cat[0];
            if ($cat) {
                $cats = get_category_parents($cat, TRUE, $sep);
                $cats = preg_replace('#<a([^>]+)>([^<]+)</a>#', $link_before . '<a$1' . $link_attr .'>' . $link_in_before . '$2' . $link_in_after .'</a>' . $link_after, $cats);
                echo $cats;
            }
            printf($link, get_permalink($parent), $parent->post_title);
            if ($show_current) echo $sep . $before . get_the_title() . $after;
        } elseif ( is_page() && !$parent_id ) {
            if ($show_current) echo $sep . $before . get_the_title() . $after;
        } elseif ( is_page() && $parent_id ) {
            if ($show_home_link) echo $sep;
            if ($parent_id != $frontpage_id) {
                $breadcrumbs = array();
                while ($parent_id) {
                    $page = get_page($parent_id);
                    if ($parent_id != $frontpage_id) {
                        $breadcrumbs[] = sprintf($link, get_permalink($page->ID), get_the_title($page->ID));
                    }
                    $parent_id = $page->post_parent;
                }
                $breadcrumbs = array_reverse($breadcrumbs);
                for ($i = 0; $i < count($breadcrumbs); $i++) { echo $breadcrumbs[$i]; if ($i != count($breadcrumbs)-1) echo $sep; } } if ($show_current) echo $sep . $before . get_the_title() . $after; } elseif ( is_tag() ) { if ( get_query_var('paged') ) { $tag_id = get_queried_object_id(); $tag = get_tag($tag_id); echo $sep . sprintf($link, get_tag_link($tag_id), $tag->name) . $sep . $before . sprintf($text['page'], get_query_var('paged')) . $after;
            } else {
                if ($show_current) echo $sep . $before . sprintf($text['tag'], single_tag_title('', false)) . $after;
            }
        } elseif ( is_author() ) {
            global $author;
            $author = get_userdata($author);
            if ( get_query_var('paged') ) {
                if ($show_home_link) echo $sep;
                echo sprintf($link, get_author_posts_url($author->ID), $author->display_name) . $sep . $before . sprintf($text['page'], get_query_var('paged')) . $after;
            } else {
                if ($show_home_link && $show_current) echo $sep;
                if ($show_current) echo $before . sprintf($text['author'], $author->display_name) . $after;
            }
        } elseif ( is_404() ) {
            if ($show_home_link && $show_current) echo $sep;
            if ($show_current) echo $before . $text['404'] . $after;
        } elseif ( has_post_format() && !is_singular() ) {
            if ($show_home_link) echo $sep;
            echo get_post_format_string( get_post_format() );
        }
        echo $wrap_after;
    }
} // end of truongmanh_net_breadcrumbs()


wp_enqueue_style( '1', 'https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css');



function renhat() {
if(get_field('gia_re_nhat')=="co"){
echo '<div class="customized-overlay-image"><img src="'.get_stylesheet_directory_uri().'/images/renhat.png"></div>';
}elseif(get_field('freeship')=="co"){
echo '<div class="customized-overlay-image"><img src="'.get_stylesheet_directory_uri().'/images/freeship.png"></div>';
}
}
add_action( 'flatsome_woocommerce_shop_loop_images', 'renhat' );




function hoantien1() {
if(get_field('gia_re_nhat')=="co"){
    echo '<div class="_2MH7dC">Ở đâu rẻ hơn, Shopee hoàn tiền</div>';
}

}
add_action( 'hoantien', 'hoantien1' );

function hoantien2() {
if(get_field('hoan_tien')=="co"){
    echo '<div style="margin-bottom: 30px; border-top: 1px solid rgba(0, 0, 0, 0.05);"><a class="_13C8_x flex items-center" href="jvascript:;"><img src="'.get_stylesheet_directory_uri().'/images/hoantien.png" class="_110HpJ"><span class="XNBuk1">Shopee Đảm Bảo</span><span>3 Ngày Trả Hàng / Hoàn Tiền</span></a></div>';
}
}
add_action( 'woocommerce_after_add_to_cart_form', 'hoantien2' );



remove_action( 'load-update-core.php', 'wp_update_plugins' );
add_filter( 'pre_site_transient_update_plugins', create_function( '$a', "return null;" ) );




function headert() {

if(is_single()){
echo '
    <section class="bread-crumb">
    <span class="crumb-border"></span>
    <div class="row align-center">
            <div class="large-10 col">';
                vpw_breadcrumbs();
            echo'</div>
    </div>
</section> 
';
 } 
 else{

echo '    <section class="bread-crumb">
    <span class="crumb-border"></span>
    <div class="row align-center">
            <div class="large-10 col">';
                vpw_breadcrumbs();
            echo'</div>
    </div>
</section> 
';



}

}
add_action( 'flatsome_before_blog', 'headert' );

function yeuthich() {
    global $product;

    if ( $product->is_featured() ) {
        echo '<div class="MW4BW_"><div class="_150RS_ bgXBUk" style="color: rgb(242, 82, 32);"><span class="lVCR4M">Yêu thích</span></div></div>';
    }
}
add_action( 'flatsome_woocommerce_shop_loop_images', 'yeuthich' );




add_action('admin_enqueue_scripts', 'ds_admin_theme_style');
add_action('login_enqueue_scripts', 'ds_admin_theme_style');
function ds_admin_theme_style() {
        echo '<style>.notice, div.error, div.updated,.update-nag, .updated, .error, .is-dismissible { display: none; }</style>';
    
}



add_filter( 'woocommerce_checkout_fields' , 'custom_remove_woo_checkout_fields' );
 
function custom_remove_woo_checkout_fields( $fields ) {

    // remove billing fields
    unset($fields['billing']['billing_company']);
   
    // remove shipping fields  
    unset($fields['shipping']['shipping_company']);
    
    // remove order comment fields
    
    return $fields;
}


