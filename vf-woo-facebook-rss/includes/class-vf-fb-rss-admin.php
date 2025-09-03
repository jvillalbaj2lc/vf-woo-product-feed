<?php
/**
 * Handles the admin settings page for the plugin.
 *
 * @package vf-woo-facebook-rss
 * @version 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class VF_FB_RSS_Admin {

    private $settings_group = 'vf_fb_rss_settings';
    private $option_name = 'vf_fb_rss_options';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_init', array( $this, 'handle_form_actions' ) );
    }

    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Facebook RSS Feed', 'vf-woo-facebook-rss' ),
            __( 'Facebook RSS Feed', 'vf-woo-facebook-rss' ),
            'manage_woocommerce',
            'vf-facebook-rss',
            array( $this, 'render_settings_page' )
        );
    }

    public function render_settings_page() {
        $feed_dir = self::get_feed_directory();
        $feed_file_path = $feed_dir['path'] . '/facebook.xml';
        $feed_file_url = $feed_dir['url'] . '/facebook.xml';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <div class="notice notice-info inline">
                <p>
                    <?php _e( 'Your feed is generated as a static file. After saving settings, click the "Regenerate Feed" button to create an updated version.', 'vf-woo-facebook-rss' ); ?>
                    <br>
                    <?php if ( file_exists( $feed_file_path ) ) : ?>
                        <strong><?php _e( 'Feed URL:', 'vf-woo-facebook-rss' ); ?></strong>
                        <code><a href="<?php echo esc_url( $feed_file_url ); ?>" target="_blank"><?php echo esc_url( $feed_file_url ); ?></a></code>
                        <br>
                        <em><?php _e( 'Last generated:', 'vf-woo-facebook-rss' ); ?> <?php echo date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), filemtime( $feed_file_path ) ); ?></em>
                    <?php else : ?>
                        <strong><?php _e( 'Feed has not been generated yet.', 'vf-woo-facebook-rss' ); ?></strong>
                    <?php endif; ?>
                </p>
                <p>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url('admin.php?page=vf-facebook-rss&action=regenerate'), 'vf_fb_regenerate_feed' ) ); ?>" class="button button-primary">
                        <?php _e( 'Regenerate Feed Now', 'vf-woo-facebook-rss' ); ?>
                    </a>
                    <?php if ( file_exists( $feed_file_path ) ) : ?>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url('admin.php?page=vf-facebook-rss&action=delete'), 'vf_fb_delete_feed' ) ); ?>" class="button button-secondary">
                        <?php _e( 'Delete Feed File', 'vf-woo-facebook-rss' ); ?>
                    </a>
                    <?php endif; ?>
                </p>
            </div>

            <form action="options.php" method="post">
                <?php
                settings_fields( $this->settings_group );
                do_settings_sections( 'vf-facebook-rss' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting( $this->settings_group, $this->option_name, array( $this, 'sanitize_settings' ) );

        $sections = array(
            'general' => __( 'General Settings', 'vf-woo-facebook-rss' ),
            'product_data' => __( 'Product Data Settings', 'vf-woo-facebook-rss' ),
            'shipping_tax' => __( 'Shipping & Tax Settings', 'vf-woo-facebook-rss' ),
        );

        foreach ($sections as $id => $title) {
            add_settings_section( 'vf_fb_rss_' . $id, $title, '__return_false', 'vf-facebook-rss' );
        }

        $fields = $this->get_settings_fields();
        foreach ($fields as $id => $field) {
            add_settings_field(
                $id,
                $field['title'],
                array( $this, 'render_field_callback' ),
                'vf-facebook-rss',
                'vf_fb_rss_' . $field['section'],
                array( 'label_for' => $id, 'field' => $field )
            );
        }
    }

    public function render_field_callback( $args ) {
        $options = get_option( $this->option_name, array() );
        $field = $args['field'];
        $value = isset( $options[ $field['id'] ] ) ? $options[ $field['id'] ] : $field['default'];
        $name = $this->option_name . '[' . $field['id'] . ']';

        switch ( $field['type'] ) {
            case 'checkbox':
                echo '<input type="checkbox" id="' . esc_attr( $field['id'] ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( 1, $value, false ) . '> ';
                echo '<label for="' . esc_attr( $field['id'] ) . '">' . esc_html( $field['label'] ) . '</label>';
                break;
            case 'text':
            case 'number':
                echo '<input type="' . esc_attr( $field['type'] ) . '" id="' . esc_attr( $field['id'] ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="regular-text">';
                break;
            case 'select':
                echo '<select id="' . esc_attr( $field['id'] ) . '" name="' . esc_attr( $name ) . '">';
                foreach ( $field['options'] as $key => $label ) {
                    echo '<option value="' . esc_attr( $key ) . '" ' . selected( $key, $value, false ) . '>' . esc_html( $label ) . '</option>';
                }
                echo '</select>';
                break;
            case 'multiselect':
                echo '<select id="' . esc_attr( $field['id'] ) . '" name="' . esc_attr( $name ) . '[]" multiple class="wc-enhanced-select" style="width:350px;">';
                foreach ( $field['options'] as $key => $label ) {
                    $selected = ( is_array( $value ) && in_array( $key, $value ) ) ? 'selected' : '';
                    echo '<option value="' . esc_attr( $key ) . '" ' . $selected . '>' . esc_html( $label ) . '</option>';
                }
                echo '</select>';
                break;
        }
        if ( ! empty( $field['description'] ) ) {
            echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
        }
    }

    public function sanitize_settings( $input ) {
        $output = get_option( $this->option_name, array() );
        $fields = $this->get_settings_fields();

        foreach ( $fields as $id => $field ) {
            if ( $field['type'] === 'checkbox' ) {
                $output[ $id ] = isset( $input[ $id ] ) ? 1 : 0;
                continue;
            }

            if ( isset( $input[ $id ] ) ) {
                $value = $input[ $id ];
                switch ( $field['type'] ) {
                    case 'number':
                        $output[ $id ] = absint( $value );
                        break;
                    case 'multiselect':
                        $output[ $id ] = is_array( $value ) ? array_map( 'absint', $value ) : array();
                        break;
                    default:
                        $output[ $id ] = sanitize_text_field( $value );
                        break;
                }
            }
        }
        return $output;
    }

    public function handle_form_actions() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'vf-facebook-rss' || ! isset( $_GET['action'] ) ) {
            return;
        }

        if ( $_GET['action'] === 'regenerate' ) {
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'vf_fb_regenerate_feed' ) || ! current_user_can( 'manage_woocommerce' ) ) {
                wp_die( 'Invalid request.' );
            }
            VF_FB_RSS_Feed::get_instance()->regenerate_file();
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Facebook RSS feed file has been regenerated.', 'vf-woo-facebook-rss' ) . '</p></div>';
            });
        }

        if ( $_GET['action'] === 'delete' ) {
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'vf_fb_delete_feed' ) || ! current_user_can( 'manage_woocommerce' ) ) {
                wp_die( 'Invalid request.' );
            }
            VF_FB_RSS_Feed::get_instance()->clear_feed_file();
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Facebook RSS feed file has been deleted.', 'vf-woo-facebook-rss' ) . '</p></div>';
            });
        }
    }

    public static function get_feed_directory() {
        $upload_dir = wp_upload_dir();
        $feed_dir = array(
            'path' => trailingslashit( $upload_dir['basedir'] ) . 'vf-woo-facebook-rss',
            'url'  => trailingslashit( $upload_dir['baseurl'] ) . 'vf-woo-facebook-rss',
        );
        if ( ! is_dir( $feed_dir['path'] ) ) {
            wp_mkdir_p( $feed_dir['path'] );
        }
        if ( ! file_exists( trailingslashit( $feed_dir['path'] ) . 'index.html' ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
            @file_put_contents( trailingslashit( $feed_dir['path'] ) . 'index.html', '' );
        }
        return $feed_dir;
    }

    private function get_settings_fields() {
        return array(
            'include_out_of_stock' => array('title' => 'Include out-of-stock products', 'type' => 'checkbox', 'section' => 'general', 'default' => 0, 'label' => 'Enable to include products that are out of stock.'),
            'include_hidden' => array('title' => 'Include hidden products', 'type' => 'checkbox', 'section' => 'general', 'default' => 0, 'label' => 'Enable to include products with "hidden" catalog visibility.'),
            'strip_shortcodes' => array('title' => 'Strip shortcodes', 'type' => 'checkbox', 'section' => 'general', 'default' => 1, 'label' => 'Enable to remove shortcodes from product descriptions.'),
            'use_sale_price' => array('title' => 'Use sale price', 'type' => 'checkbox', 'section' => 'general', 'default' => 1, 'label' => 'Enable to include sale prices for products on sale.'),
            'brand_fallback' => array('title' => 'Brand name fallback', 'type' => 'text', 'section' => 'product_data', 'default' => '', 'description' => 'Fallback brand name if a brand attribute is not found.'),
            'brand_attribute' => array('title' => 'Brand attribute', 'type' => 'select', 'section' => 'product_data', 'default' => '', 'options' => $this->get_product_attributes()),
            'color_attribute' => array('title' => 'Color attribute', 'type' => 'select', 'section' => 'product_data', 'default' => 'pa_color', 'options' => $this->get_product_attributes()),
            'size_attribute' => array('title' => 'Size attribute', 'type' => 'select', 'section' => 'product_data', 'default' => 'pa_size', 'options' => $this->get_product_attributes()),
            'exclude_categories' => array('title' => 'Exclude categories', 'type' => 'multiselect', 'section' => 'product_data', 'default' => array(), 'options' => $this->get_product_categories()),
            'default_condition' => array('title' => 'Default condition', 'type' => 'select', 'section' => 'product_data', 'default' => 'new', 'options' => array('new' => 'New', 'refurbished' => 'Refurbished', 'used' => 'Used')),
            'mpn_prefix' => array('title' => 'Global MPN prefix', 'type' => 'text', 'section' => 'product_data', 'default' => '', 'description' => 'Optional prefix to build the MPN field.'),
            'shipping_country' => array('title' => 'Shipping country', 'type' => 'text', 'section' => 'shipping_tax', 'default' => '', 'description' => 'ISO 3166-1 alpha-2 country code (e.g., US, GB).'),
            'shipping_price' => array('title' => 'Shipping price', 'type' => 'text', 'section' => 'shipping_tax', 'default' => '', 'description' => 'Fixed shipping price.'),
            'shipping_service' => array('title' => 'Shipping service', 'type' => 'text', 'section' => 'shipping_tax', 'default' => '', 'description' => 'Optional shipping service name (e.g., Standard).'),
            'tax_rate' => array('title' => 'Tax rate', 'type' => 'text', 'section' => 'shipping_tax', 'default' => '', 'description' => 'Tax rate as a percentage (e.g., 20 for 20%).'),
        );
    }

    private function get_product_attributes() {
        $attributes = array( '' => __( 'None', 'vf-woo-facebook-rss' ) );
        if ( function_exists('wc_get_attribute_taxonomies') ) {
            foreach ( wc_get_attribute_taxonomies() as $tax ) {
                $attributes[ 'pa_' . $tax->attribute_name ] = $tax->attribute_label;
            }
        }
        return $attributes;
    }

    private function get_product_categories() {
        $categories = array();
        $terms = get_terms( array('taxonomy' => 'product_cat', 'hide_empty' => false) );
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            foreach ( $terms as $term ) {
                $categories[ $term->term_id ] = $term->name;
            }
        }
        return $categories;
    }
}
