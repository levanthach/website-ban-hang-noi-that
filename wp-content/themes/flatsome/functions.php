<?php

/**

 * Flatsome functions and definitions

 *

 * @package flatsome

 */



require get_template_directory() . '/inc/init.php';



/**

 * Note: It's not recommended to add any custom code here. Please use a child theme so that your customizations aren't lost during updates.

 * Learn more here: http://codex.wordpress.org/Child_Themes

 */





/**

* WooCommerce Replace “Free!” by a custom string

**/

function custom_call_for_price() {

    return 'Giá liên hệ';

}

 

add_filter('woocommerce_empty_price_html', 'custom_call_for_price');





// Translate Shopping Cart Breadcrumb

add_filter( 'gettext', function ( $strings ) {



$text = array(

'SHOPPING CART' => 'Giỏ hàng',

'CHECKOUT DETAILS' => 'Thanh toán',

'ORDER COMPLETE' => 'Hoàn tất',

);

$strings = str_ireplace( array_keys( $text ), $text, $strings );

return $strings;

}, 20 );



// Remove turn off Woo 3.6 Extensions

add_filter( 'woocommerce_allow_marketplace_suggestions', '__return_false' );



add_filter('woocommerce_currency_symbol', 'change_existing_currency_symbol', 10, 2);

 

function change_existing_currency_symbol( $currency_symbol, $currency ) {

 switch( $currency ) {

 case 'VND': $currency_symbol = ' VNĐ'; break;

 }

 return $currency_symbol;

}



// Remove Parent Category from Child Category URL

add_filter('term_link', 'devvn_no_category_parents', 1000, 3);

function devvn_no_category_parents($url, $term, $taxonomy) {

    if($taxonomy == 'category'){

        $term_nicename = $term->slug;

        $url = trailingslashit(get_option( 'home' )) . user_trailingslashit( $term_nicename, 'category' );

    }

    return $url;

}

// Rewrite url mới

function devvn_no_category_parents_rewrite_rules($flash = false) {

    $terms = get_terms( array(

        'taxonomy' => 'category',

        'post_type' => 'post',

        'hide_empty' => false,

    ));

    if($terms && !is_wp_error($terms)){

        foreach ($terms as $term){

            $term_slug = $term->slug;

            add_rewrite_rule($term_slug.'/?$', 'index.php?category_name='.$term_slug,'top');

            add_rewrite_rule($term_slug.'/page/([0-9]{1,})/?$', 'index.php?category_name='.$term_slug.'&paged=$matches[1]','top');

            add_rewrite_rule($term_slug.'/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$', 'index.php?category_name='.$term_slug.'&feed=$matches[1]','top');

        }

    }

    if ($flash == true)

        flush_rewrite_rules(false);

}

add_action('init', 'devvn_no_category_parents_rewrite_rules');

 

/*Sửa lỗi khi tạo mới category bị 404*/

function devvn_new_category_edit_success() {

    devvn_no_category_parents_rewrite_rules(true);

}

add_action('created_category','devvn_new_category_edit_success');

add_action('edited_category','devvn_new_category_edit_success');

add_action('delete_category','devvn_new_category_edit_success');

add_filter('use_block_editor_for_post', '__return_false');

 