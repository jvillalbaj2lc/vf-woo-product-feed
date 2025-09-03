<?php
/**
 * WooCommerce Integration for VF Facebook RSS Feed Settings
 *
 * @package vf-woo-facebook-rss
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'VF_FB_RSS_Settings' ) ) :

	/**
	 * VF Facebook RSS Feed Integration Settings.
	 *
	 * @class       VF_FB_RSS_Settings
	 * @extends     WC_Integration
	 */
	class VF_FB_RSS_Settings extends WC_Integration {

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->id                 = 'vf-facebook-rss';
			$this->method_title       = __( 'VF Facebook RSS Feed', 'vf-woo-facebook-rss' );
			$this->method_description = __( 'Settings for the VF Facebook RSS Feed plugin. Once configured, your feed will be available at', 'vf-woo-facebook-rss' ) . ' <code>' . esc_url( home_url( '/facebook-rss.xml' ) ) . '</code>';

			$this->init_form_fields();
			$this->init_settings();

			add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		/**
		 * Initialize form fields.
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'general_settings' => array(
					'title' => __( 'General Settings', 'vf-woo-facebook-rss' ),
					'type'  => 'title',
				),
				'include_out_of_stock' => array(
					'title'   => __( 'Include out-of-stock products', 'vf-woo-facebook-rss' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable to include products that are out of stock in the feed.', 'vf-woo-facebook-rss' ),
					'default' => 'no',
				),
				'include_hidden' => array(
					'title'   => __( 'Include hidden products', 'vf-woo-facebook-rss' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable to include products with "hidden" catalog visibility.', 'vf-woo-facebook-rss' ),
					'default' => 'no',
				),
                'strip_shortcodes' => array(
                    'title'   => __( 'Strip shortcodes from descriptions', 'vf-woo-facebook-rss' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable to remove shortcodes from product descriptions.', 'vf-woo-facebook-rss' ),
                    'default' => 'yes',
                ),
                'use_sale_price' => array(
                    'title'   => __( 'Use sale price', 'vf-woo-facebook-rss' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable to include `g:sale_price` for products on sale.', 'vf-woo-facebook-rss' ),
                    'default' => 'yes',
                ),
				'cache_ttl' => array(
					'title'       => __( 'Cache TTL', 'vf-woo-facebook-rss' ),
					'type'        => 'number',
					'description' => __( 'Time-to-live for the feed cache in minutes.', 'vf-woo-facebook-rss' ),
					'default'     => 60,
                    'desc_tip'    => true,
				),

				'product_data_settings' => array(
					'title' => __( 'Product Data Settings', 'vf-woo-facebook-rss' ),
					'type'  => 'title',
				),
				'brand_fallback' => array(
					'title'       => __( 'Brand name fallback', 'vf-woo-facebook-rss' ),
					'type'        => 'text',
					'description' => __( 'Fallback brand name if a brand attribute is not found for a product.', 'vf-woo-facebook-rss' ),
					'default'     => '',
                    'desc_tip'    => true,
				),
				'brand_attribute' => array(
					'title'       => __( 'Brand attribute', 'vf-woo-facebook-rss' ),
					'type'        => 'select',
					'options'     => $this->get_product_attributes(),
					'description' => __( 'Select the product attribute to use for the `g:brand` field.', 'vf-woo-facebook-rss' ),
                    'desc_tip'    => true,
				),
				'color_attribute' => array(
					'title'       => __( 'Color attribute', 'vf-woo-facebook-rss' ),
					'type'        => 'select',
					'options'     => $this->get_product_attributes(),
					'description' => __( 'Select the attribute for `g:color`. Tries `pa_color` by default.', 'vf-woo-facebook-rss' ),
					'default'     => 'pa_color',
                    'desc_tip'    => true,
				),
				'size_attribute' => array(
					'title'       => __( 'Size attribute', 'vf-woo-facebook-rss' ),
					'type'        => 'select',
					'options'     => $this->get_product_attributes(),
					'description' => __( 'Select the attribute for `g:size`. Tries `pa_size` by default.', 'vf-woo-facebook-rss' ),
					'default'     => 'pa_size',
                    'desc_tip'    => true,
				),
				'exclude_categories' => array(
					'title'       => __( 'Exclude categories', 'vf-woo-facebook-rss' ),
					'type'        => 'multiselect',
					'class'       => 'wc-enhanced-select',
					'options'     => $this->get_product_categories(),
					'description' => __( 'Select categories to exclude from the feed.', 'vf-woo-facebook-rss' ),
                    'desc_tip'    => true,
				),
				'default_condition' => array(
					'title'       => __( 'Default condition', 'vf-woo-facebook-rss' ),
					'type'        => 'select',
					'options'     => array(
						'new'         => __( 'New', 'vf-woo-facebook-rss' ),
						'refurbished' => __( 'Refurbished', 'vf-woo-facebook-rss' ),
						'used'        => __( 'Used', 'vf-woo-facebook-rss' ),
					),
					'default'     => 'new',
				),
				'mpn_prefix' => array(
					'title'       => __( 'Global MPN prefix', 'vf-woo-facebook-rss' ),
					'type'        => 'text',
					'description' => __( 'Optional prefix to build the `g:mpn` field. MPN will be `prefix + product_id` or `prefix + product_sku`.', 'vf-woo-facebook-rss' ),
					'default'     => '',
                    'desc_tip'    => true,
				),

				'shipping_tax_settings' => array(
					'title' => __( 'Shipping & Tax Settings', 'vf-woo-facebook-rss' ),
					'type'  => 'title',
				),
				'shipping_country' => array(
					'title'       => __( 'Shipping country', 'vf-woo-facebook-rss' ),
					'type'        => 'text',
					'description' => __( 'ISO 3166-1 alpha-2 country code (e.g., US, GB).', 'vf-woo-facebook-rss' ),
                    'desc_tip'    => true,
				),
				'shipping_price' => array(
					'title'       => __( 'Shipping price', 'vf-woo-facebook-rss' ),
					'type'        => 'text',
					'description' => __( 'Fixed shipping price. Leave blank to omit.', 'vf-woo-facebook-rss' ),
                    'desc_tip'    => true,
				),
				'shipping_service' => array(
					'title'       => __( 'Shipping service', 'vf-woo-facebook-rss' ),
					'type'        => 'text',
					'description' => __( 'Optional shipping service name (e.g., Standard, Express).', 'vf-woo-facebook-rss' ),
                    'desc_tip'    => true,
				),
				'tax_rate' => array(
					'title'       => __( 'Tax rate', 'vf-woo-facebook-rss' ),
					'type'        => 'text',
					'description' => __( 'Tax rate as a percentage (e.g., 20 for 20%). Only needed for specific locales.', 'vf-woo-facebook-rss' ),
                    'desc_tip'    => true,
				),

                'cache_management' => array(
                    'title'       => __( 'Cache Management', 'vf-woo-facebook-rss' ),
                    'type'        => 'title',
                    'description' => $this->get_cache_regeneration_html(),
                ),
			);
		}

        /**
         * Get product attributes for select dropdown.
         *
         * @return array
         */
        private function get_product_attributes() {
            $attributes = array( '' => __( 'None', 'vf-woo-facebook-rss' ) );
            if ( function_exists('wc_get_attribute_taxonomies') ) {
                $attribute_taxonomies = wc_get_attribute_taxonomies();
                if ( $attribute_taxonomies ) {
                    foreach ( $attribute_taxonomies as $tax ) {
                        $attributes[ 'pa_' . $tax->attribute_name ] = $tax->attribute_label;
                    }
                }
            }
            return $attributes;
        }

        /**
         * Get product categories for multiselect.
         *
         * @return array
         */
        private function get_product_categories() {
            $categories = array();
            $terms = get_terms( array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
            ) );
            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                foreach ( $terms as $term ) {
                    $categories[ $term->term_id ] = $term->name;
                }
            }
            return $categories;
        }

        /**
         * Get cache regeneration HTML for the settings page.
         *
         * @return string
         */
        protected function get_cache_regeneration_html() {
            $html = '<p>' . __( 'The feed is cached to improve performance. You can manually clear the cache here or by visiting the feed URL with the <code>&flush=1</code> parameter.', 'vf-woo-facebook-rss' ) . '</p>';
            $feed_url = home_url( '/facebook-rss.xml' );
            $flush_url = wp_nonce_url( add_query_arg( 'flush', '1', $feed_url ), 'vf-fb-flush-cache' );
            $html .= '<p><a href="' . esc_url( $flush_url ) . '" class="button" target="_blank" rel="noopener noreferrer">' . __( 'Regenerate Feed Now', 'vf-woo-facebook-rss' ) . '</a></p>';
            return $html;
        }
	}

endif;
