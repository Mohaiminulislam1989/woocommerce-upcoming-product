<?php
/*
Plugin Name: Woocommerce upcoming Products
Plugin URI: http://shaikat.me/
Description: Manage your upcoming product easily. add upcoming label, remove add to cart button for the product, short by upcoming on shop page and set product available date.
Version: 1.3.2
Author: Sk Shaikat
Author URI: https://twitter.com/SK_Shaikat
Text Domain: wup
Domain Path: /languages
License: GPL2
*/

/**
* Copyright (c) 2015 Sk Shaikat (email: sk.shaikat18@gmail.com). All rights reserved.
*
*/

// don't call the file directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
* Woocommerce_Upcoming_Product class
*
* @class Woocommerce_Upcoming_Product The class that holds the entire Woocommerce_Upcoming_Product plugin
*/
class Woocommerce_Upcoming_Product
{

    /**
    * Constructor for the Woocommerce_Upcoming_Product class
    *
    * Sets up all the appropriate hooks and actions
    * within our plugin.
    *
    * @uses register_activation_hook()
    * @uses register_deactivation_hook()
    * @uses is_admin()
    * @uses add_action()
    */
    public function __construct()
    {
        register_activation_hook( __FILE__, array($this,'activate' ) );
        register_deactivation_hook( __FILE__, array($this,'deactivate' ) );

        // Localize our plugin
        add_action( 'init', array($this,'localization_setup' ) );

        // Loads frontend scripts and styles
        add_action( 'admin_enqueue_scripts', array($this,'admin_enqueue_scripts' ) );
        add_action( 'wp_enqueue_scripts', array($this,'enqueue_scripts' ) );


        // wup let's play option
        add_action( 'woocommerce_before_single_product', array($this,'wup_single_page_view' ) );
        // wup let's play option
        add_action( 'woocommerce_before_shop_loop_item', array($this,'wup_shop_page_view' ) );

        // Add Discount and sales price optin in backend for addmin
        add_action( 'woocommerce_product_options_pricing', array($this,'add_upcoming_options' ),10 );
        add_action( 'woocommerce_process_product_meta_simple', array($this,'save_upcoming_options' ),10 );
        add_action( 'woocommerce_process_product_meta_variable', array($this,'save_upcoming_options' ),10 );

        // image ribbon
        add_filter( 'the_title', array($this,'upcoming_product_title' ), 2, 10 );

        // add search form option
        add_filter( 'woocommerce_catalog_orderby', array($this,'upcoming_search_option' ) );

        // custom preorder search queary
        add_action( 'woocommerce_product_query', array($this,'upcoming_custom_queary'), 2 );

        add_filter( 'woocommerce_single_product_summary', array($this,'custom_get_availability' ), 15 );

        // pre order single product view
        add_action( 'woocommerce_single_product_summary', array($this,'upcoming_single_page_view'), 40 );
        add_action( 'woocommerce_after_shop_loop_item', array($this,'upcoming_shop_page_view'), 7 );

        add_filter( 'woocommerce_get_sections_products', array($this,'wup_wc_product_settings_section' ), 10 );

        add_filter( 'woocommerce_get_settings_products', array($this,'wup_wc_product_settings_option' ), 10, 2 );

    }

    /**
    * Initializes the Woocommerce_Upcoming_Product() class
    *
    * Checks for an existing Woocommerce_Upcoming_Product() instance
    * and if it doesn't find one, creates it.
    */
    public static function init()
    {
        static $instance = false;

        if ( ! $instance ) {
            $instance = new Woocommerce_Upcoming_Product();
        }

        return $instance;
    }

    /**
    * Placeholder for activation function
    *
    * Nothing being called here yet.
    */
    public function activate()
    {

    }

    /**
    * Placeholder for deactivation function
    *
    * Nothing being called here yet.
    */
    public function deactivate()
    {

    }

    /**
    * Initialize plugin for localization
    *
    * @uses load_plugin_textdomain()
    */
    public function localization_setup()
    {
        load_plugin_textdomain( 'wup', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
    * Enqueue admin scripts
    *
    * Allows plugin assets to be loaded.
    *
    * @uses wp_enqueue_script()
    * @uses wp_localize_script()
    * @uses wp_enqueue_style
    */
    public function enqueue_scripts()
    {

        /**
        * All styles goes here
        */
        wp_enqueue_style( 'upcoming-styles', plugins_url( 'css/style.css', __FILE__ ), false, date( 'Ymd' ) );

    }

    public function admin_enqueue_scripts() {

        /**
        * All scripts goes here
        */
        wp_enqueue_script( 'upcoming-scripts', plugins_url( 'js/script.js', __FILE__ ), array('jquery' ), false, true );
    }

    function is_upcoming() {
        global $post;
        if ( get_post_meta( $post->ID, '_upcoming', true ) == 'yes' ) {
            return true;
        } else {
            return false;
        }
    }

    function wup_single_page_view()
    {
        if ( $this->is_upcoming() ) {
            if ( WC_Admin_Settings::get_option( 'wup_price_hide_single', 'no' ) == 'yes' ) {
                remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
            }
            if ( WC_Admin_Settings::get_option( 'wup_button_hide_single', 'no' ) == 'yes' ) {
                remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
            }
        }
    }

    function wup_shop_page_view()
    {
        if ( WC_Admin_Settings::get_option( 'wup_price_hide_shop', 'no' ) == 'yes' && $this->is_upcoming() ) {
            remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
        } else {
            add_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
        }
        if ( WC_Admin_Settings::get_option( 'wup_button_hide_shop', 'no' ) == 'yes' && $this->is_upcoming() ) {
            remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
        } else {
            add_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
        }
    }

    function add_upcoming_options()
    {
        global $post;
        woocommerce_wp_checkbox( array('id'         => '_upcoming','label'      => __( 'Upcoming Product', 'wup' ),'description'=> __( 'Enable for upcoming product', 'wup' ) ) );
        $available_class = ( get_post_meta( $post->ID, '_upcoming', true ) == 'yes' ) ? '' : 'wup-hide';
        woocommerce_wp_text_input( array('id'           => '_available_on','label'        => __( 'Available On', 'wup' ),'wrapper_class'=> $available_class,'description'  => __( 'Insert product available date', 'wup' ) ) );

    }

    function save_upcoming_options( $post_id  )
    {
        $_upcoming = ( isset( $_POST['_upcoming'] ) ) ? $_POST['_upcoming'] : '';
        $_available_on = ( isset( $_POST['_available_on'] ) ) ? $_POST['_available_on'] : __( 'Date not set', 'wup' );
        update_post_meta( $post_id, '_upcoming', $_upcoming );
        update_post_meta( $post_id, '_available_on', $_available_on );
    }


    function upcoming_product_title( $title, $id )
    {
        if ( is_admin() ) {
            return $title;
        }
        $label     = WC_Admin_Settings::get_option( 'wup_title_label_txt', __( 'Upcoming', 'wup' ) );
        if ( $this->is_upcoming() && WC_Admin_Settings::get_option( 'wup_title_label', 'yes' ) == 'yes' ) {
            $title .= ' <span class="soon">(' . $label . ')</span>';
        }
        return $title;
    }

    function upcoming_search_option( $catalog_orderby )
    {
        $catalog_orderby['upcoming'] = __( 'Sort by upcoming', 'wup' );
        return $catalog_orderby;
    }

    function upcoming_custom_queary($q)
    {

        $meta_query = $q->query_vars['meta_query'];

        if ( isset( $_GET['orderby'] ) && $_GET['orderby'] == 'upcoming' ) {
            $meta_query[] = array(
                'key'    => '_upcoming',
                'value'  => 'yes',
                'compare'=> '='
            );

            $q->set( 'meta_query', $meta_query );
        }
    }

    // Our hooked in function $availablity is passed via the filter!
    function custom_get_availability()
    {
        $price_label = WC_Admin_Settings::get_option( 'wup_price_label_txt', __( 'Coming Soon', 'wup' ) );
        if ( $this->is_upcoming() && WC_Admin_Settings::get_option( 'wup_price_label', 'yes' ) == 'yes' ) {
            echo '<div class="product_meta"><span class="wup-price-label">' . $price_label . '</span></div>';
        }
    }

    function upcoming_single_page_view()
    {
        global $post;
        if ( WC_Admin_Settings::get_option( 'wup_show_available_date_single', 'yes' ) == 'yes' ) {
            $_available_on = get_post_meta( $post->ID, '_available_on', true );
            if ( $this->is_upcoming() ) {
                ?>
                <div class="product_meta">
                    <span class="available-from">
                        <?php _e( 'Available from: ', 'wup' );

                        if ( $_available_on == '' ) {
                            ?>
                            <strong>
                                <?php _e( 'Date not yet set.', 'wup' ); ?>
                            </strong>
                            <?php
                        }else {
                            ?>
                            <strong>
                                <?php echo date_i18n(get_option( 'date_format' ), strtotime($_available_on)) ; ?>
                            </strong>
                            <?php
                        }
                        ?>
                    </span>
                </div>
                <?php
            }
        }
    }

    function upcoming_shop_page_view()
    {
        global $post;
        if ( WC_Admin_Settings::get_option( 'wup_show_available_date_shop', 'yes' ) == 'yes' ) {
            $_available_on = get_post_meta( $post->ID, '_available_on', true );
            if ( $this->is_upcoming() ) {
                ?>
                <div class="upcoming">
                    <?php _e( 'Available from: ', 'wup' );

                    if ( $_available_on == '' ) {
                        ?>
                        <strong>
                            <?php _e( 'Date not yet set.', 'wup' ); ?>
                        </strong>
                        <?php
                    }else {
                        $dateformatstring = "l d F, Y";
                        $unixtimestamp    = strtotime($_available_on);
                        ?>
                        <strong>
                            <?php echo date_i18n(get_option( 'date_format' ), strtotime($_available_on)) ; ?>
                        </strong>
                        <?php
                    }
                    ?>
                </div>
                <?php
            }
        }
    }

    function wup_wc_product_settings_section( $sections )
    {
        $sections['wup'] = __( 'Upcoming Products', 'wup' );
        return $sections;
    }

    function wup_wc_product_settings_option( $settings, $current_section )
    {
        if ( 'wup' == $current_section ) {
            $settings = apply_filters( 'wup_product_settings', array(
                    array(
                        'title'=> __( 'Upcoming Product', 'wup' ),
                        'type' => 'title',
                        'desc' => '',
                        'id'   => 'wup_options'
                    ),

                    array(
                        'title'  => __( 'Upcoming Product title label', 'wup' ),
                        'desc'   => __( 'Show label on upcoming product title', 'wup' ),
                        'id'     => 'wup_title_label',
                        'default'=> 'yes',
                        'type'   => 'checkbox'
                    ),

                    array(
                        'title'  => __( 'Title label Text', 'wup' ),
                        'desc'   => __( 'Label will show on upcoming product title', 'wup' ),
                        'id'     => 'wup_title_label_txt',
                        'default'=> 'Upcoming',
                        'type'   => 'text'
                    ),

                    array(
                        'title'  => __( 'Price Hide on Single Page', 'wup' ),
                        'desc'   => __( 'Hide price of upcoming product on single product page', 'wup' ),
                        'id'     => 'wup_price_hide_single',
                        'default'=> 'no',
                        'type'   => 'checkbox'
                    ),

                    array(
                        'title'   => __( 'Price Hide on Shop Page', 'wup' ),
                        'desc'    => __( 'Hide price of upcoming product on shop page', 'wup' ),
                        'id'      => 'wup_price_hide_shop',
                        'default' => 'no',
                        'type'    => 'checkbox'
                    ),

                    array(
                        'title'  => __( 'Upcoming Product price label', 'wup' ),
                        'desc'   => __( 'Show label under price of upcoming product', 'wup' ),
                        'id'     => 'wup_price_label',
                        'default'=> 'yes',
                        'type'   => 'checkbox'
                    ),

                    array(
                        'title'  => __( 'Text Under Price', 'wup' ),
                        'desc'   => __( 'Text under product price', 'wup' ),
                        'id'     => 'wup_price_label_txt',
                        'default'=> 'Coming Soon',
                        'type'   => 'text'
                    ),

                    array(
                        'title'  => __( '"Add to Cart" Button Hide on Single Page', 'wup' ),
                        'desc'   => __( 'Hide "Add to Cart" Button of upcoming product on single product page', 'wup' ),
                        'id'     => 'wup_button_hide_single',
                        'default'=> 'no',
                        'type'   => 'checkbox'
                    ),

                    array(
                        'title'   => __( '"Add to Cart" Button Hide on Shop Page', 'wup' ),
                        'desc'    => __( 'Hide "Add to Cart" Button of upcoming product on shop page', 'wup' ),
                        'id'      => 'wup_button_hide_shop',
                        'default' => 'no',
                        'type'    => 'checkbox'
                    ),

                    array(
                        'title'  => __( 'Available Date Show on Single Page', 'wup' ),
                        'desc'   => __( 'Show available date on single product page view', 'wup' ),
                        'id'     => 'wup_show_available_date_single',
                        'default'=> 'yes',
                        'type'   => 'checkbox'
                    ),

                    array(
                        'title'  => __( 'Available Date Show on Shop Page', 'wup' ),
                        'desc'   => __( 'Show available date on shop product page view', 'wup' ),
                        'id'     => 'wup_show_available_date_shop',
                        'default'=> 'yes',
                        'type'   => 'checkbox'
                    ),

                    array(
                        'type'=> 'sectionend',
                        'id'  => 'wup_options'
                    ),
                ));
        }
        return $settings;
    }

} // Woocommerce_Upcoming_Product

$upcoming = Woocommerce_Upcoming_Product::init();
