# VF Woo Facebook RSS Feed

A production-ready WordPress plugin that generates a single, static XML RSS file compatible with Facebook Catalog, which uses the Google Merchant Center RSS specification.

## Description

This plugin generates a static XML file of your WooCommerce products, including variations. It's designed to be memory-efficient and performant, even on sites with a large number of products. The feed file is stored in your site's `uploads` directory.

## Features

- Includes simple products and product variations (as separate items).
- `g:item_group_id` connects variations to their parent product.
- Comprehensive settings page under the **WooCommerce -> Facebook RSS Feed** menu.
- Efficient, streamed XML generation to prevent memory exhaustion during file creation.
- "Regenerate" and "Delete" buttons for manual file management.
- WP-CLI command for cache management and cron jobs.

## Installation

1.  Upload the `vf-woo-facebook-rss` folder to your `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Go to **WooCommerce > Facebook RSS Feed** and configure the settings.
4.  Click the **Regenerate Feed Now** button to create your feed file for the first time.

## Usage

After generating the feed, the settings page will display the direct URL to your static feed file (e.g., `https://your-domain.com/wp-content/uploads/vf-woo-facebook-rss/facebook.xml`). You can use this URL to set up a product catalog in your Facebook Business Manager.

### File Management

The feed is **not** generated automatically. You must regenerate it manually after making changes to your products or the plugin settings.

You can manage the feed file in two ways:
1.  On the plugin settings page, click **Regenerate Feed Now** to create or update the file, or **Delete Feed File** to remove it.
2.  Use the WP-CLI command.

### WP-CLI

You can regenerate the feed file from the command line, which is ideal for cron jobs.

**`wp vf-feed:generate`**

This command generates or overwrites the static feed file.

Example:
```shell
# Regenerate the feed file
$ wp vf-feed:generate
Regenerating feed file...
Success: Feed file regenerated successfully.
The feed is available at: https://example.com/wp-content/uploads/vf-woo-facebook-rss/facebook.xml
```
