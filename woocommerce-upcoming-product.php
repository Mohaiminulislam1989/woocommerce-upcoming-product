<?php
/*
Plugin Name: Woocommerce upcoming Products
Plugin URI: http://shaikat.me/
Description: Manage your upcoming product easily. add upcoming label, remove add to cart button for the product, short by upcoming on shop page and set product available date.
Version: 1.2
Author: Sk Shaikat
Author URI: http://shaikat.me/
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
class Woocommerce_Upcoming_Product {

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
    public function __construct() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        // Localize our plugin
        add_action( 'init', array( $this, 'localization_setup' ) );

        // Loads frontend scripts and styles
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );



        // Add Discount and sales price optin in backend for addmin
        add_action( 'woocommerce_product_options_pricing', array( $this, 'add_upcoming_options' ),10 );
        add_action( 'woocommerce_process_product_meta_simple', array( $this, 'save_upcoming_options' ),10 );
        add_action( 'woocommerce_process_product_meta_variable', array( $this, 'save_upcoming_options' ),10 );

        // image ribbon
        add_filter( 'the_title', array( $this, 'upcoming_product_title' ), 2, 10 );

        // add search form option
        add_filter( 'woocommerce_catalog_orderby', array( $this, 'upcoming_search_option' ) );
        
        // custom preorder search queary
        add_action( 'woocommerce_product_query', array( $this, 'upcoming_custom_queary'), 2 );

        add_filter( 'woocommerce_get_availability', array( $this, 'custom_get_availability' ), 1, 2 );

        // pre order single product view
        add_action( 'woocommerce_single_product_summary', array( $this, 'upcoming_single_page_view'), 40 );

        add_filter( 'woocommerce_get_sections_products', array( $this, 'wup_wc_product_settings_section' ), 10 );

        add_filter( 'woocommerce_get_settings_products', array( $this, 'wup_wc_product_settings_option' ), 10, 2 );

    }

    /**
     * Initializes the Woocommerce_Upcoming_Product() class
     *
     * Checks for an existing Woocommerce_Upcoming_Product() instance
     * and if it doesn't find one, creates it.
     */
    public static function init() {
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
    public function activate() {

    }

    /**
     * Placeholder for deactivation function
     *
     * Nothing being called here yet.
     */
    public function deactivate() {

    }

    /**
     * Initialize plugin for localization
     *
     * @uses load_plugin_textdomain()
     */
    public function localization_setup() {
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
    public function enqueue_scripts() {

        /**
         * All styles goes here
         */
        wp_enqueue_style( 'upcoming-styles', plugins_url( 'css/style.css', __FILE__ ), false, date( 'Ymd' ) );
        /**
         * All scripts goes here
         */
        wp_enqueue_script( 'upcoming-scripts', plugins_url( 'js/script.js', __FILE__ ), array( 'jquery' ), false, true );

    }

    function add_upcoming_options() {
        global $post;
        woocommerce_wp_checkbox( array( 'id' => '_upcoming', 'label' => __( 'Upcoming Product', 'woocommerce' ), 'description' => __( 'Enable for upcoming product', 'woocommerce' ) ) );
        $available_class = ( get_post_meta( $post->ID, '_upcoming', true ) == 'yes' ) ? '' : 'wup-hide';
        woocommerce_wp_text_input( array( 'id' => '_available_on', 'label' => __( 'Available On', 'woocommerce' ), 'wrapper_class' => $available_class, 'description' => __( 'Insert product available date', 'woocommerce' ) ) );
        
    }

    function save_upcoming_options( $post_id  ) {
        $_upcoming = ( isset( $_POST['_upcoming'] ) ) ? $_POST['_upcoming'] : '';
        $_available_on = ( isset( $_POST['_available_on'] ) ) ? $_POST['_available_on'] : 'Date not set';
        update_post_meta( $post_id, '_upcoming', $_upcoming );
        update_post_meta( $post_id, '_available_on', $_available_on );
        if ($_upcoming != '' ) {
            update_post_meta( $post_id, '_stock_status', 'outofstock' );
        } else {
            if ( empty( $_POST['_manage_stock'] ) ) {
                update_post_meta( $post_id, '_stock_status', 'instock' );
            }
        }
    }

    
    function upcoming_product_title( $title, $id ){
        $label = WC_Admin_Settings::get_option( 'wup_title_label_txt', 'wup' );
        $_upcoming = get_post_meta( $id, '_upcoming', true );
        if ( WC_Admin_Settings::get_option( 'wup_title_label', 'wup' ) == 'yes' ) {
            if ( $_upcoming == 'yes' ) {
                $title .= ' (' . $label . ')';
            }
        }
        return $title;
    }

    function upcoming_search_option( $catalog_orderby ) {
        $catalog_orderby['upcoming'] = __( 'Sort by upcoming', 'wup' );
        return $catalog_orderby;
    }

    function upcoming_custom_queary($q) {
    
        $meta_query = $q->query_vars['meta_query'];

        if ( isset( $_GET['orderby'] ) && $_GET['orderby'] == 'upcoming' ) {
            $meta_query[] = array(
                'key' => '_upcoming',
                'value' => 'yes',
                'compare' => '='
            );

            $q->set( 'meta_query', $meta_query );
        }
    }

    // Our hooked in function $availablity is passed via the filter!
    function custom_get_availability( $availability, $_product ) {
        $button_label = WC_Admin_Settings::get_option( 'wup_button_label_txt', 'wup' );
        if ( !$_product->is_in_stock() ) $availability['availability'] = $button_label;
        return $availability;
    } 

    function upcoming_single_page_view() {
        global $post;
        $_upcoming = get_post_meta( $post->ID, '_upcoming', true );
        $_available_on = get_post_meta( $post->ID, '_available_on', true );
        if( $_upcoming == 'yes') { ?>
            <div class="product_meta">
                <span class="posted_in">
                <?php _e( 'Available from: ', 'wup' ); 
                 
                if( $_available_on == '' ) { ?>
                    <strong><?php _e( 'Date not yet set.', 'wup' ); ?></strong>
                <?php
                }else { ?>
                    <strong><?php echo $_available_on; ?></strong>
                <?php
                }
                ?>
                </span>
            </div>
    <?php
        }
    }

    function wup_wc_product_settings_section( $sections ) {
        $sections['wup']  = __( 'Upcoming Products', 'wup' );
        return $sections;
    }

    function wup_wc_product_settings_option( $settings, $current_section ) {
        if ( 'wup' == $current_section ) {
            $settings = apply_filters( 'wup_product_settings', array(
                array(
                    'title' => __( 'Upcoming Product', 'wup' ),
                    'type'  => 'title',
                    'desc'  => '',
                    'id'    => 'wup_options'
                ),

                array(
                    'title'   => __( 'Upcoming Product label', 'wup' ),
                    'desc'    => __( 'Allow for showing upcoming product title label', 'wup' ),
                    'id'      => 'wup_title_label',
                    'default' => 'yes',
                    'type'    => 'checkbox'
                ),

                array(
                    'title'   => __( 'Upcoming Product label Text', 'wup' ),
                    'desc'    => __( 'Product label on upcoming product title', 'wup' ),
                    'id'      => 'wup_title_label_txt',
                    'default' => 'Upcoming',
                    'type'    => 'text'
                ),

                array(
                    'title'   => __( 'Text Under Price', 'wup' ),
                    'desc'    => __( 'Text under product price rather than button', 'wup' ),
                    'id'      => 'wup_button_label_txt',
                    'default' => 'Coming Soon',
                    'type'    => 'text'
                ),

                array(
                    'type'  => 'sectionend',
                    'id'    => 'wup_options'
                ),
            ) );
        }
        return $settings;
    }

} // Woocommerce_Upcoming_Product

$upcoming = Woocommerce_Upcoming_Product::init();
