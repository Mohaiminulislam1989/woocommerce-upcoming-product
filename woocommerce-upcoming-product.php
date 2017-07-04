<?php
/*
Plugin Name: Woocommerce upcoming Products
Plugin URI: https://github.com/Sk-Shaikat/woocommerce-upcoming-product
Description: Best Plugin to Manage your upcoming product easily in WooCommerce.
Version: 1.5.6
Author: Sk Shaikat
Author URI: http://shaikat.me
Text Domain: wup
Domain Path: /languages
License: GPL2
*/

/**
 * Copyright (c) 2017 Sk Shaikat (email: sk.shaikat18@gmail.com). All rights reserved.
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
        $this->wup_define_constants();
        $this->wup_init_hooks();

        do_action( 'wup_loaded' );
    }

    /**
     * Hook into actions and filters.
     * @since  1.5.6
     */
    private function wup_init_hooks() {
        register_activation_hook( __FILE__, array( $this,'activate' ) );
        register_deactivation_hook( __FILE__, array( $this,'deactivate' ) );

        // Localize our plugin
        add_action( 'init', array( $this,'localization_setup' ) );
        add_action( 'init', array( $this,'wup_register_daily_upcoming_delete_event' ) );

        // Loads frontend scripts and styles
        add_action( 'admin_enqueue_scripts', array( $this,'admin_enqueue_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this,'enqueue_scripts' ) );


        // wup let's play option
        add_action( 'woocommerce_before_single_product', array( $this,'wup_single_page_view' ) );
        // wup let's play option
        add_action( 'woocommerce_before_shop_loop_item', array( $this,'wup_shop_page_view' ) );

        // Add upcoming option
        add_action( 'woocommerce_product_options_advanced', array( $this,'wup_add_upcoming_options' ), 10 );
        add_action( 'woocommerce_process_product_meta_simple', array( $this,'wup_save_upcoming_options' ), 10 );
        add_action( 'woocommerce_process_product_meta_variable', array( $this,'wup_save_upcoming_options' ), 10 );
        add_filter( 'woocommerce_add_to_cart_validation', array( $this,'wup_stop_adding_to_cart' ), 2, 10 );

        // image ribbon
        add_filter( 'the_title', array( $this,'wup_upcoming_product_title' ), 2, 10 );

        // add search form option
        add_filter( 'woocommerce_catalog_orderby', array( $this,'wup_upcoming_search_option' ) );

        // custom preorder search queary
        add_action( 'woocommerce_product_query', array( $this,'wup_upcoming_custom_queary'), 2 );

        add_filter( 'woocommerce_single_product_summary', array( $this,'wup_custom_get_availability' ), 15 );

        // pre order single product view
        add_action( 'woocommerce_single_product_summary', array( $this,'wup_upcoming_single_page_view'), 30 );
        add_action( 'woocommerce_after_shop_loop_item', array( $this,'wup_upcoming_shop_page_view'), 7 );

        add_filter( 'woocommerce_get_sections_products', array( $this,'wup_wc_product_settings_section' ), 10 );

        add_filter( 'woocommerce_get_settings_products', array( $this,'wup_wc_product_settings_option' ), 10, 2 );
        add_action( 'wup_expired_upcoming_product', array( $this, 'wup_auto_delete_product_updoming_meta' ) );

        add_filter( 'plugin_action_links_' . WUP_PLUGIN_BASENAME, array( $this, 'wup_plugin_action_links' ) );

        // need to add shortcode for showing upcoming procuct.
        // need subscription manager
    }


    /**
     * Initializes the Woocommerce_Upcoming_Product() class
     *
     * Checks for an existing Woocommerce_Upcoming_Product() instance
     * and if it doesn't find one, creates it.
     */
    public static function init() {
        // Before init action.
        do_action( 'before_wup_init' );
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
        if (! wp_next_scheduled ( 'wup_expired_upcoming_product' ) ) {
            wp_schedule_event( time(), 'twicedaily', 'wup_expired_upcoming_product' );
        }
    }

    /**
     * Placeholder for deactivation function
     *
     * Nothing being called here yet.
     */
    public function deactivate()
    {
        wp_clear_scheduled_hook( 'wup_expired_upcoming_product' );
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
     * Enqueue scripts
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
    }

    // Function which will register the event
    function wup_register_daily_upcoming_delete_event() {
        // Make sure this event hasn't been scheduled
        if ( !wp_next_scheduled ( 'wup_expired_upcoming_product' ) ) {
            wp_schedule_event( time(), 'twicedaily', 'wup_expired_upcoming_product' );
        }
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
    public function admin_enqueue_scripts() {
        global $pagenow;
        /**
         * All scripts goes here
         */
        if ( !in_array( $pagenow, array( 'post.php', 'post-new.php') ) ) {
            return;
        }
        wp_enqueue_style( 'upcoming-styles', plugins_url( 'css/admin-style.css', __FILE__ ), false, date( 'Ymd' ) );
        wp_enqueue_script( 'upcoming-scripts', plugins_url( 'js/script.js', __FILE__ ), array('jquery' ), false, true );
    }

    /**
     * Define WC Constants.
     */
    private function wup_define_constants() {
        $upload_dir = wp_upload_dir();
        $this->wup_define( 'WUP_PLUGIN_FILE', __FILE__ );
        $this->wup_define( 'WUP_ABSPATH', dirname( __FILE__ ) . '/' );
        $this->wup_define( 'WUP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
    }

    /**
     * Define constant if not already set.
     *
     * @param  string $name
     * @param  string|bool $value
     */
    private function wup_define( $name, $value ) {
        if ( ! defined( $name ) ) {
            define( $name, $value );
        }
    }

    /**
     * Show action links on the plugin screen.
     * 
     * @since 1.5.6
     *
     * @param   mixed $links Plugin Action links
     * @return  array
     */
    public static function wup_plugin_action_links( $links ) {
        $action_links = array(
            'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=products&section=wup' ) . '" aria-label="' . esc_attr__( 'View WooCommerce settings', 'woocommerce' ) . '">' . esc_html__( 'Settings', 'woocommerce' ) . '</a>',
        );

        return array_merge( $action_links, $links );
    }


    /**
     * Get human readable time difference between 2 dates
     *
     * Return difference between 2 dates in year, month, hour, minute or second
     * The $precision caps the number of time units used: for instance if
     * $time1 - $time2 = 3 days, 4 hours, 12 minutes, 5 seconds
     * - with precision = 1 : 3 days
     * - with precision = 2 : 3 days, 4 hours
     * - with precision = 3 : 3 days, 4 hours, 12 minutes
     * 
     * From: http://www.if-not-true-then-false.com/2010/php-calculate-real-differences-between-two-dates-or-timestamps/
     *
     * @param mixed $time1 a time (string or timestamp)
     * @param mixed $time2 a time (string or timestamp)
     * @param integer $precision Optional precision 
     * @return string time difference
     */
    function wup_get_date_diff( $time1, $time2, $precision = 2 ) {
        // If not numeric then convert timestamps
        if( !is_int( $time1 ) ) {
            $time1 = strtotime( $time1 );
        }
        if( !is_int( $time2 ) ) {
            $time2 = strtotime( $time2 );
        }
        // If time1 > time2 then swap the 2 values
        if( $time1 > $time2 ) {
            list( $time1, $time2 ) = array( $time2, $time1 );
        }
        // Set up intervals and diffs arrays
        $intervals = array( 'year', 'month', 'day', 'hour', 'minute', 'second' );
        $diffs = array();
        foreach( $intervals as $interval ) {
            // Create temp time from time1 and interval
            $ttime = strtotime( '+1 ' . $interval, $time1 );
            // Set initial values
            $add = 1;
            $looped = 0;
            // Loop until temp time is smaller than time2
            while ( $time2 >= $ttime ) {
                // Create new temp time from time1 and interval
                $add++;
                $ttime = strtotime( "+" . $add . " " . $interval, $time1 );
                $looped++;
            }
            $time1 = strtotime( "+" . $looped . " " . $interval, $time1 );
            $diffs[ $interval ] = $looped;
        }
        $count = 0;
        $times = array();
        foreach( $diffs as $interval => $value ) {
            // Break if we have needed precission
            if( $count >= $precision ) {
                break;
            }
            // Add value and interval if value is bigger than 0
            if( $value > 0 ) {
                if( $value != 1 ){
                    $interval .= "s";
                }
                // Add value and interval to times array
                $times[] = $value . " " . $interval;
                $count++;
            }
        }
        // Return string with times
        return implode( ", ", $times );
    }


    /**
     * Delete upcoming meta from product
     *
     * @since 1.5.5
     */
    function wup_auto_delete_product_updoming_meta() {
        if ( WC_Admin_Settings::get_option( 'wup_auto_live', 'yes' ) == 'yes' ) {
            $this->wup_scheduled_delete_product_updoming_meta();
        }
    }


    /**
     * get upcoming product
     *
     * @since 1.5.5
     */
    function wup_get_updoming_products() {
        $args = array(
            'post_type' => 'product',
            'meta_query' => array(
                array(
                    'key'    => '_upcoming',
                    'value'  => 'yes',
                    'compare'=> '='
                )
            )
         );
        return get_posts( $args );
    }

    /**
     * Delete upcoming meta from product
     *
     * @since 1.5
     */
    function wup_scheduled_delete_product_updoming_meta() {
        $posts_list = $this->wup_get_updoming_products();
        foreach ( $posts_list as  $post ) {
            if ( get_post_meta( $post->ID, '_upcoming', true ) == 'yes' ) {
                $available_on = get_post_meta( $post->ID, '_available_on', true );
                if ( empty( $available_on ) ) {
                    return;
                }
                $next_day = date( 'Y-m-d', strtotime( '+1 day' ) );
                $available_on = date( 'Y-m-d', strtotime( $available_on ) );
                if ( $next_day > $available_on ) {
                    delete_post_meta( $post->ID, '_upcoming' );
                }
            }
        }
    }

    /**
     * Check if the product is upcoming
     *
     * @since 1.0
     *
     * @global $post
     * @return boolian
     */
    function is_upcoming() {
        global $post;
        if ( get_post_meta( $post->ID, '_upcoming', true ) == 'yes' ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Product single page view
     *
     * @since 1.0
     */
    function wup_single_page_view() {
        if ( $this->is_upcoming() ) {
            if ( WC_Admin_Settings::get_option( 'wup_price_hide', 'no' ) == 'yes' ) {
                remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
            }
            if ( WC_Admin_Settings::get_option( 'wup_button_hide', 'no' ) == 'yes' ) {
                remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
            }
        }
    }

    /**
     * Product shop page view
     *
     * @since 1.0
     */
    function wup_shop_page_view() {
        if ( $this->is_upcoming() && WC_Admin_Settings::get_option( 'wup_price_hide', 'no' ) == 'yes' ) {
            remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
        } else if ( !$this->is_upcoming() && WC_Admin_Settings::get_option( 'wup_price_hide', 'no' ) == 'yes' ) {
            add_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
        }
        if ( $this->is_upcoming() && WC_Admin_Settings::get_option( 'wup_button_hide', 'no' ) == 'yes' ) {
            remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
        } else if ( !$this->is_upcoming() && WC_Admin_Settings::get_option( 'wup_button_hide', 'no' ) == 'yes' ) {
            add_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
        }
    }

    /**
     * Add upcoming setting on edit product page
     *
     * @since 1.0
     *
     * @global $post
     */
    function wup_add_upcoming_options() {
        global $post;
        woocommerce_wp_checkbox( array('id'         => '_upcoming','label'      => __( 'Upcoming Product', 'wup' ),'description'=> __( 'Enable for upcoming product', 'wup' ) ) );
        $available_class = get_post_meta( $post->ID, '_upcoming', true ) ? '' : 'wup-hide';
        woocommerce_wp_text_input( array('id'           => '_available_on','label'        => __( 'Available On', 'wup' ),'wrapper_class'=> $available_class,'description'  => __( 'Insert product available date', 'wup' ) ) );
    }

    /**
     * Save upcoming meta of product
     *
     * @since 1.0
     * 
     * @param int $post_id
     */
    function wup_save_upcoming_options( $post_id  ) {
        $_upcoming = isset( $_POST['_upcoming'] ) ? $_POST['_upcoming'] : '';
        $_available_on = ( isset( $_POST['_available_on'] ) ) ? wc_clean( $_POST['_available_on'] ) : '';
        
        update_post_meta( $post_id, '_upcoming', $_upcoming );
        update_post_meta( $post_id, '_available_on', $_available_on );
    }

    /**
     * Stop add to cart for upcoming product if button hide
     *
     * @since 1.5.5
     * 
     * @param bool true
     * @param int $product_id
     *
     * return bool
     */
    function wup_stop_adding_to_cart( $chack, $product_id  ) {
        if ( get_post_meta( $post->ID, '_upcoming', true ) != 'yes' ) {
            return true;
        } else if ( get_post_meta( $post->ID, '_upcoming', true ) != 'yes' && WC_Admin_Settings::get_option( 'wup_button_hide', 'no' ) == 'no' ) {
            return false;
        }
    }

    /**
     * Add text to upcoming product title
     *
     * @since 1.0
     *
     * @param string $title
     * @param int $id
     * @return string $title
     */
    function wup_upcoming_product_title( $title, $id = null )
    {
        if ( is_admin() ) {
            return $title;
        }
        $label     = WC_Admin_Settings::get_option( 'wup_title_label_txt', 'Upcoming' );
        if ( 'product' == get_post_type( $id ) && $this->is_upcoming() && WC_Admin_Settings::get_option( 'wup_show_title_label', 'yes' ) == 'yes' ) {
            $title .= sprintf( __( ' <span class="soon">(%s)</span>', 'wup' ), $label );
        }
        return $title;
    }

    /**
     * Add option to search upcoming product on shop page
     *
     * @since 1.0
     *
     * @param array $catalog_orderby
     * @return array $catalog_orderby
     */
    function wup_upcoming_search_option( $catalog_orderby )
    {
        $catalog_orderby['upcoming'] = WC_Admin_Settings::get_option( 'wup_sort_by_text', 'Sort by upcoming' );
        return $catalog_orderby;
    }

    /**
     * DUpdate search queary
     *
     * @since 1.0
     *
     * @param obj
     * 
     * @return obj
     */
    function wup_upcoming_custom_queary( $q ) {
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

    /**
     * Add custom text on single product page
     *
     * @since 1.0
     */
    function wup_custom_get_availability() {
        $price_label = WC_Admin_Settings::get_option( 'wup_price_label_txt', 'Coming Soon' );
        if ( $this->is_upcoming() && WC_Admin_Settings::get_option( 'wup_show_price_label', 'yes' ) == 'yes' ) {
            echo sprintf( __( '<div class="product_meta"><span class="wup-price-label">%s</span></div>', 'wup' ), $price_label );
        }
    }

    /**
     * Single page view for upcoming product
     *
     * @since 1.0
     *
     * @global $post
     */
    function wup_upcoming_single_page_view() {
        global $post;
        if ( $this->is_upcoming() ) {
            if ( WC_Admin_Settings::get_option( 'wup_show_available_date', 'yes' ) == 'yes' ) {
                $_available_on = get_post_meta( $post->ID, '_available_on', true ); ?>
                <div class="product_meta">
                    <span class="available-from">
                        <strong>
                            <?php
                            if ( !empty( WC_Admin_Settings::get_option( 'wup_availabel_date_lebel', 'Available from' ) ) ) {
                                echo WC_Admin_Settings::get_option( 'wup_availabel_date_lebel', 'Available from' ) . ': ';
                            }
                            if ( empty( $_available_on ) ) { 
                                echo WC_Admin_Settings::get_option( 'wup_not_availabel_date_text', 'Date not set yet' );
                            }else {
                                if ( 'date' == WC_Admin_Settings::get_option( 'wup_available_date_format', 'date' ) ) {
                                    echo date_i18n( get_option( 'date_format' ), strtotime( $_available_on ) );
                                } else if ( 'duration' == WC_Admin_Settings::get_option( 'wup_available_date_format', 'date' ) ) {
                                    echo $this->wup_get_date_diff( current_time('timestamp'), $_available_on );
                                }
                            } 
                            ?>
                        </strong>
                    </span>
                </div>
                <?php
            }
        }
    }


    /**
     * Shop page view for upcoming product
     *
     * @since 1.0
     *
     * @global $post
     */
    function wup_upcoming_shop_page_view() {
        global $post;
        if ( $this->is_upcoming() ) {
            if ( WC_Admin_Settings::get_option( 'wup_show_available_date', 'yes' ) == 'yes' ) {
                $_available_on = get_post_meta( $post->ID, '_available_on', true ); ?>
                <div class="price">
                    <span class="available-from">
                        <strong>
                            <?php
                            if ( !empty( WC_Admin_Settings::get_option( 'wup_availabel_date_lebel', 'Available from' ) ) ) {
                                echo WC_Admin_Settings::get_option( 'wup_availabel_date_lebel', 'Available from' ) . ': ';
                            }
                            if ( empty( $_available_on ) ) { 
                                echo WC_Admin_Settings::get_option( 'wup_not_availabel_date_text', 'Date not set yet' );
                            }else {
                                if ( 'date' == WC_Admin_Settings::get_option( 'wup_available_date_format', 'date' ) ) {
                                    echo date_i18n( get_option( 'date_format' ), strtotime( $_available_on ) );
                                } else if ( 'duration' == WC_Admin_Settings::get_option( 'wup_available_date_format', 'date' ) ) {
                                    echo $this->wup_get_date_diff( current_time('timestamp'), $_available_on );
                                }
                            } 
                            ?>
                        </strong>
                    </span>
                </div>
                <?php
            }
        }
    }

    /**
     * Add admin setting on woocommerce settings page
     *
     * @since 1.0
     *
     * @param array $sections
     * @return array $sections
     */
    function wup_wc_product_settings_section( $sections ) {
        $sections['wup'] = __( 'Upcoming Products', 'wup' );
        return $sections;
    }

    /**
     * Add admin setting fields on upcoming product setting page
     *
     * @since 1.0
     *
     * @param array $settings
     * @param string $current_section
     * @return array $settings
     */
    function wup_wc_product_settings_option( $settings, $current_section ) {
        if ( 'wup' == $current_section ) {
            $settings = apply_filters( 'wup_product_settings', array(
                    array(
                        'title'=> __( 'Upcoming Product', 'wup' ),
                        'type' => 'title',
                        'desc' => '',
                        'id'   => 'wup_options'
                    ),

                    array(
                        'title'  => __( 'Upcoming Product auto live', 'wup' ),
                        'desc'   => __( 'Upcoming product will automatically go online on available date', 'wup' ),
                        'id'     => 'wup_auto_live',
                        'default'=> 'yes',
                        'type'   => 'checkbox'
                    ),

                    array(
                        'title'  => __( 'Hide product price', 'wup' ),
                        'desc'   => __( 'Hide upcoming product price', 'wup' ),
                        'id'     => 'wup_price_hide',
                        'default'=> 'no',
                        'type'   => 'checkbox'
                    ),

                    array(
                        'title'  => __( 'Hide purchase button ', 'wup' ),
                        'desc'   => __( 'Hide <code>Add to Cart</code> button of upcoming product', 'wup' ),
                        'id'     => 'wup_button_hide',
                        'default'=> 'no',
                        'type'   => 'checkbox'
                    ),

                    array(
                        'title'  => __( 'Show title label', 'wup' ),
                        'desc'   => __( 'Show label on upcoming product title', 'wup' ),
                        'id'     => 'wup_show_title_label',
                        'default'=> 'yes',
                        'type'   => 'checkbox'
                    ),

                    array(
                        'title'  => __( 'Title label Text', 'wup' ),
                        'desc'   => __( 'Label will show with upcoming product title', 'wup' ),
                        'id'     => 'wup_title_label_txt',
                        'default'=> 'Upcoming',
                        'type'   => 'text'
                    ),

                    array(
                        'title'  => __( 'Show price label', 'wup' ),
                        'desc'   => __( 'Show text under upcoming product price', 'wup' ),
                        'id'     => 'wup_show_price_label',
                        'default'=> 'yes',
                        'type'   => 'checkbox'
                    ),

                    array(
                        'title'  => __( 'Price label text', 'wup' ),
                        'desc'   => __( 'Text under upcoming product price', 'wup' ),
                        'id'     => 'wup_price_label_txt',
                        'default'=> 'Coming Soon',
                        'type'   => 'text'
                    ),

                    array(
                        'title'  => __( 'Show available date', 'wup' ),
                        'desc'   => __( 'Show available date of upcoming products', 'wup' ),
                        'id'     => 'wup_show_available_date',
                        'default'=> 'yes',
                        'type'   => 'checkbox'
                    ),

                    array(
                        'title'  => __( 'Available date format', 'wup' ),
                        'desc'   => __( 'Show available date as date or duration', 'wup' ),
                        'id'     => 'wup_available_date_format',
                        'default'  => 'date',
                        'type'     => 'select',
                        'desc_tip' => true,
                        'options'  => array(
                            'date'      => __( 'Date', 'wup' ),
                            'duration'  => __( 'Duration', 'wup' ),
                        ),
                    ),

                    array(
                        'title'  => __( 'Available date lebel', 'wup' ),
                        'desc'   => __( 'Lebel of showing available date', 'wup' ),
                        'id'     => 'wup_availabel_date_lebel',
                        'default'=> 'Available from',
                        'type'   => 'text'
                    ),

                    array(
                        'title'  => __( 'Not available date text', 'wup' ),
                        'desc'   => __( 'Text to show if available date not set', 'wup' ),
                        'id'     => 'wup_not_availabel_date_text',
                        'default'=> 'Date not set yet',
                        'type'   => 'text'
                    ),

                    array(
                        'title'  => __( 'Short by dropdown text', 'wup' ),
                        'desc'   => __( 'Text to show in <code>shop page</code> short by dropdown', 'wup' ),
                        'id'     => 'wup_sort_by_text',
                        'default'=> 'Sort by upcoming',
                        'type'   => 'text'
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
