<?php
/**
 * Feed Generator for VF Facebook RSS Feed
 *
 * @package vf-woo-facebook-rss
 * @version 1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'VF_FB_RSS_Feed' ) ) :

	/**
	 * Handles the generation, caching, and delivery of the RSS feed.
	 */
	class VF_FB_RSS_Feed {

		protected static $_instance = null;
        private $settings;

		public static function get_instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct() {
            $this->settings = get_option( 'woocommerce_vf-facebook-rss_settings', array() );

			add_action( 'init', array( $this, 'register_feed_endpoint' ) );
			add_action( 'template_redirect', array( $this, 'maybe_render_feed' ) );
            add_action( 'save_post_product', array( $this, 'clear_cache' ) );
            add_action( 'woocommerce_update_options_integration_vf-facebook-rss', array( $this, 'clear_cache' ) );
		}

		public function register_feed_endpoint() {
            add_rewrite_rule( '^facebook-rss\.xml/?$', 'index.php?vf_fb_feed=1', 'top' );
		}

        public function clear_cache() {
            delete_transient( $this->get_transient_key() );
            delete_transient( $this->get_transient_key( '_last_mod' ) );
        }

        public function regenerate_cache() {
            delete_transient( $this->get_transient_key( '_last_mod' ) );
            $this->get_last_modified_time();

            ob_start();
            $this->generate_feed_content();
            $feed_xml = ob_get_clean();

            $ttl = (int) $this->get_setting( 'cache_ttl', 60 );
            set_transient( $this->get_transient_key(), $feed_xml, $ttl * MINUTE_IN_SECONDS );
        }

        private function get_transient_key( $suffix = '' ) {
            return 'vf_fb_rss_feed_' . md5( serialize( $this->settings ) ) . $suffix;
        }

		public function maybe_render_feed() {
			if ( get_query_var( 'vf_fb_feed' ) ) {
                if ( isset( $_GET['flush'] ) && $_GET['flush'] == '1' ) {
                    if ( current_user_can( 'manage_woocommerce' ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'vf-fb-flush-cache' ) ) {
                        $this->clear_cache();
                        wp_safe_redirect( remove_query_arg( array( 'flush', '_wpnonce' ) ) );
                        exit;
                    }
                }
				$this->render_feed();
				exit;
			}
		}

        private function get_last_modified_time() {
            $transient_key = $this->get_transient_key( '_last_mod' );
            $last_mod_gmt = get_transient( $transient_key );

            if ( false === $last_mod_gmt ) {
                global $wpdb;
                $last_mod_gmt = $wpdb->get_var( "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_type IN ('product', 'product_variation') AND post_status = 'publish'" );
                set_transient( $transient_key, $last_mod_gmt, HOUR_IN_SECONDS * 6 );
            }

            return $last_mod_gmt ? strtotime( $last_mod_gmt . ' GMT' ) : time();
        }

		public function render_feed() {
            $last_modified = $this->get_last_modified_time();
            $etag = md5( $last_modified . serialize( $this->settings ) );

            header( 'Content-Type: application/rss+xml; charset=UTF-8' );
            header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $last_modified ) . ' GMT' );
            header( 'ETag: "' . $etag . '"' );

            if ( ( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) && strtotime( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) >= $last_modified ) ||
                 ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && trim( $_SERVER['HTTP_IF_NONE_MATCH'], '"' ) === $etag ) ) {
                status_header( 304 );
                exit;
            }

            $transient_key = $this->get_transient_key();
            $feed_xml = get_transient( $transient_key );

            if ( false === $feed_xml ) {
                $this->regenerate_cache();
                $feed_xml = get_transient( $transient_key );
            }

            echo $feed_xml;
            exit;
        }

        private function generate_feed_content() {
            $xml = new XMLWriter();
			$xml->openMemory();
			$xml->setIndent( true );
			$xml->startDocument( '1.0', 'UTF-8' );
			$xml->startElement( 'rss' );
			$xml->writeAttribute( 'version', '2.0' );
			$xml->writeAttribute( 'xmlns:g', 'http://base.google.com/ns/1.0' );
			$xml->startElement( 'channel' );
			$xml->writeElement( 'title', get_bloginfo( 'name' ) . ' - Facebook RSS Feed' );
			$xml->writeElement( 'link', home_url() );
			$xml->writeElement( 'description', 'Product catalog for ' . get_bloginfo( 'name' ) );

			$args = array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'tax_query'      => array(),
                'meta_query'     => array('relation' => 'AND'),
			);

            $exclude_cats = $this->get_setting('exclude_categories', array());
            if ( ! empty($exclude_cats) ) {
                $args['tax_query'][] = array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $exclude_cats,
                    'operator' => 'NOT IN',
                );
            }

            if ( $this->get_setting('include_hidden', 'no') === 'no' ) {
                $args['tax_query'][] = array(
                    'taxonomy' => 'product_visibility',
                    'field'    => 'name',
                    'terms'    => 'exclude-from-catalog',
                    'operator' => 'NOT IN',
                );
            }

            $paged = 1;
			do {
				$args['paged'] = $paged;
				$products_query = new WP_Query( $args );

				if ( $products_query->have_posts() ) {
					while ( $products_query->have_posts() ) {
						$products_query->the_post();
						$product = wc_get_product( get_the_ID() );

						if ( ! $product ) continue;

                        if ( $this->get_setting('include_out_of_stock', 'no') === 'no' && ! $product->is_in_stock() && ! $product->is_type('variable') ) {
                            continue;
                        }

						if ( $product->is_type( 'variable' ) ) {
							$variations = $product->get_children();
							foreach ( $variations as $variation_id ) {
								$variation = wc_get_product( $variation_id );
								if ( ! $variation || $variation->get_status() !== 'publish' ) continue;

								if ( $this->get_setting('include_out_of_stock', 'no') === 'no' && ! $variation->is_in_stock() ) {
									continue;
								}
								$this->write_item_xml( $xml, $variation, $product );
							}
						} else {
							$this->write_item_xml( $xml, $product );
						}
					}
				}
				wp_reset_postdata();
				$paged++;
			} while ( $paged <= $products_query->max_num_pages );

			$xml->endElement(); // channel
			$xml->endElement(); // rss
			$xml->endDocument();
			echo $xml->outputMemory();
        }

        private function write_item_xml( XMLWriter $xml, $product, $parent_product = null ) {
            $is_variation = $product->is_type('variation');
            $id = $product->get_sku() ? $product->get_sku() : get_current_blog_id() . '-' . $product->get_id();
            $currency = get_woocommerce_currency();

            $xml->startElement('item');

            $title = $is_variation ? ($parent_product->get_name() . ' - ' . implode(' / ', $product->get_variation_attributes(true))) : $product->get_name();
            $xml->startElement('title');
            $xml->writeCData($title);
            $xml->endElement();

            $link = $is_variation ? add_query_arg($product->get_variation_attributes(), $parent_product->get_permalink()) : $product->get_permalink();
            $xml->writeElement('link', $link);

            $description = $product->get_description() ? $product->get_description() : ($parent_product ? $parent_product->get_description() : '');
            if ($this->get_setting('strip_shortcodes', 'yes') === 'yes') {
                $description = strip_shortcodes($description);
            }
            $description = wp_strip_all_tags(str_replace('</', ' </', $description));
            $description = substr($description, 0, 4999);
            $xml->startElement('description');
            $xml->writeCData($description);
            $xml->endElement();

            $xml->writeElement('g:id', $id);

            if ($is_variation && $parent_product) {
                $parent_id = $parent_product->get_sku() ? $parent_product->get_sku() : get_current_blog_id() . '-' . $parent_product->get_id();
                $xml->writeElement('g:item_group_id', $parent_id);
            }

            $price = wc_format_decimal($product->get_price(), 2, false) . ' ' . $currency;
            $xml->writeElement('g:price', $price);

            if ($this->get_setting('use_sale_price', 'yes') === 'yes' && $product->is_on_sale()) {
                $sale_price = wc_format_decimal($product->get_sale_price(), 2, false) . ' ' . $currency;
                $xml->writeElement('g:sale_price', $sale_price);

                $from = get_post_meta($product->get_id(), '_sale_price_dates_from', true);
                $to = get_post_meta($product->get_id(), '_sale_price_dates_to', true);
                if ($from && $to) {
                    $sale_date_range = gmdate('Y-m-d\TH:i:s\Z', $from) . '/' . gmdate('Y-m-d\TH:i:s\Z', $to);
                    $xml->writeElement('g:sale_price_effective_date', $sale_date_range);
                }
            }

            $status = $product->get_stock_status();
            $availability = ($status === 'instock') ? 'in stock' : (($status === 'onbackorder') ? 'preorder' : 'out of stock');
            $xml->writeElement('g:availability', $availability);

            $xml->writeElement('g:condition', $this->get_setting('default_condition', 'new'));

            $brand_tax = $this->get_setting('brand_attribute');
            $brand = $brand_tax ? $product->get_attribute($brand_tax) : '';
            if (!$brand) $brand = $this->get_setting('brand_fallback');
            if ($brand) $xml->writeElement('g:brand', $brand);

            $image_id = $product->get_image_id();
            if ($is_variation && !$image_id && $parent_product) $image_id = $parent_product->get_image_id();
            if ($image_id) $xml->writeElement('g:image_link', wp_get_attachment_url($image_id));

            $gallery_ids = $parent_product ? $parent_product->get_gallery_image_ids() : $product->get_gallery_image_ids();
            $count = 0;
            foreach ($gallery_ids as $gid) {
                if ($count++ >= 10) break;
                if ($url = wp_get_attachment_url($gid)) $xml->writeElement('g:additional_image_link', $url);
            }

            $color_tax = $this->get_setting('color_attribute', 'pa_color');
            if ($color = $product->get_attribute($color_tax)) $xml->writeElement('g:color', $color);

            $size_tax = $this->get_setting('size_attribute', 'pa_size');
            if ($size = $product->get_attribute($size_tax)) $xml->writeElement('g:size', $size);

            $gtin = get_post_meta($product->get_id(), '_gtin', true) ?: get_post_meta($product->get_id(), '_wc_gtin_code', true);
            if ($gtin) $xml->writeElement('g:gtin', $gtin);

            $mpn = get_post_meta($product->get_id(), '_mpn', true);
            if (!$mpn && $this->get_setting('mpn_prefix')) {
                $mpn = $this->get_setting('mpn_prefix') . ($product->get_sku() ?: $product->get_id());
            }
            if ($mpn) $xml->writeElement('g:mpn', $mpn);

            $terms = get_the_terms($parent_product ? $parent_product->get_id() : $product->get_id(), 'product_cat');
            if ($terms && !is_wp_error($terms)) {
                $xml->writeElement('g:product_type', implode(' > ', wp_list_pluck($terms, 'name')));
            }

            $ship_country = $this->get_setting('shipping_country');
            $ship_price = $this->get_setting('shipping_price');
            if ($ship_country && $ship_price) {
                $xml->startElement('g:shipping');
                $xml->writeElement('g:country', $ship_country);
                $xml->writeElement('g:price', wc_format_decimal($ship_price, 2, false) . ' ' . $currency);
                if ($service = $this->get_setting('shipping_service')) $xml->writeElement('g:service', $service);
                $xml->endElement();
            }

            if ($tax_rate = $this->get_setting('tax_rate')) {
                $xml->startElement('g:tax');
                $xml->writeElement('g:country', $ship_country ?: get_option('woocommerce_default_country'));
                $xml->writeElement('g:rate', $tax_rate);
                $xml->endElement();
            }

            $xml->endElement(); // item
        }

        private function get_setting( $key, $default = '' ) {
            $value = isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
            if ( is_array($default) && !is_array($value)) {
                return $default;
            }
            return $value;
        }
	}
endif;
