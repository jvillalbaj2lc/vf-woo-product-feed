<?php
/**
 * Feed Generator for VF Facebook RSS Feed
 *
 * @package vf-woo-facebook-rss
 * @version 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'VF_FB_RSS_Feed' ) ) :

	/**
	 * Handles the generation of the RSS feed file.
	 */
	class VF_FB_RSS_Feed {

		protected static $_instance = null;
        private $options;

		public static function get_instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct() {
            $this->options = get_option( 'vf_fb_rss_options', array() );
		}

        /**
         * Force regeneration of the feed file.
         * Can be called from the admin page or WP-CLI.
         */
        public function regenerate_file() {
            $feed_dir = VF_FB_RSS_Admin::get_feed_directory();
            $file_path = $feed_dir['path'] . '/facebook.xml';

            ob_start();
            $this->generate_feed_content();
            $feed_xml = ob_get_clean();

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
            @file_put_contents( $file_path, $feed_xml );
        }

        /**
         * Deletes the generated feed file.
         */
        public function clear_feed_file() {
            $feed_dir = VF_FB_RSS_Admin::get_feed_directory();
            $file_path = $feed_dir['path'] . '/facebook.xml';
            if ( file_exists( $file_path ) ) {
                unlink( $file_path );
            }
        }

        /**
         * Generates the raw XML content for the feed by querying products.
         */
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
				'posts_per_page' => 100, // Increased for faster file generation
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

            if ( ! $this->get_setting('include_hidden', 0) ) {
                $args['tax_query'][] = array(
                    'taxonomy' => 'product_visibility',
                    'field'    => 'name',
                    'terms'    => 'exclude-from-catalog',
                    'operator' => 'NOT IN',
                );
            }

            if ( ! $this->get_setting('include_out_of_stock', 0) ) {
                $args['meta_query'][] = array(
                    'key' => '_stock_status',
                    'value' => 'instock'
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

						if ( $product->is_type( 'variable' ) ) {
                            if ( ! $product->has_child() ) continue;
							$variations = $product->get_children();
							foreach ( $variations as $variation_id ) {
								$variation = wc_get_product( $variation_id );
								if ( ! $variation || $variation->get_status() !== 'publish' ) continue;
                                if ( ! $this->get_setting('include_out_of_stock', 0) && !$variation->is_in_stock() ) continue;
								$this->write_item_xml( $xml, $variation, $product );
							}
						} else {
                            if ( ! $this->get_setting('include_out_of_stock', 0) && !$product->is_in_stock() ) continue;
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
            if ( $this->get_setting('strip_shortcodes', 1) ) {
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

            if ( $this->get_setting('use_sale_price', 1) && $product->is_on_sale()) {
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
            return isset( $this->options[ $key ] ) ? $this->options[ $key ] : $default;
        }
	}
endif;
