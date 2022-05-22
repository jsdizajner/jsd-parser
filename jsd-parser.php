<?php
defined('ABSPATH') || exit;

/*
Plugin Name: XML Parser Framework
Plugin URI: https://jsdizajner.com/
Description: JSD XML Parser
Version: 1.1.3
Author: Július Sipos
Author URI: https://jsdizajner.com/
Text Domain: jsd-parser
*/

// If used get_plugin_data include WP PLUGIN.php
if (!function_exists('get_plugin_data')) {
	require_once(ABSPATH . 'wp-admin/includes/plugin.php');
}


define('JSD_PARSER_FRAMEWORK_DIR', plugin_dir_path(__FILE__));
define('JSD_PARSER_PLUGIN_DATA', get_plugin_data(__FILE__));

require __DIR__ . '/vendor/autoload.php';
require 'class.core.php';
require 'class.factory.php';
require 'class.mapping.php';
require 'class.xml.php';
require 'class.helper.php';
require 'class.cycle.php';

// Initiate Core Framework Functionality
new JSD__PARSER_CORE();

function framework_settings_add_plugin_page()
{
    add_menu_page(
        'XML Parser', // page_title
        'XML Parser', // menu_title
        'manage_options', // capability
        'jsdizajner-xml-parser', // menu_slug
        'create_admin_page_xml_parser', // function
        'dashicons-database-import', // icon_url
        99 // position
    );
}

// Load Feature after Fields are Registered
add_action('admin_menu', 'framework_settings_add_plugin_page');

function create_admin_page_xml_parser()
{
    include(JSD_PARSER_FRAMEWORK_DIR . 'admin-page.php');
}

/**
 * Displays the custom text field input field in the WooCommerce product data meta box
 */
function parcer_unique_id_field()
{
    $args = array(
        'id' => '_unique_import_id_field',
        'label' => __('Unique Import ID', 'jsd-parser'),
        'class' => '__jsd_parcer_unique_id_field_input',
        'desc_tip' => true,
        'description' => __('Do not change or manipalte this data!', 'jsd-parser'),
    );
    woocommerce_wp_text_input($args);
}
add_action('woocommerce_product_options_general_product_data', 'parcer_unique_id_field');

/**
 * Saves the custom field data to product meta data
 */
function cfwc_save_custom_field($post_id)
{
    $product = wc_get_product($post_id);
    $title = isset($_POST['_unique_import_id_field']) ? $_POST['_unique_import_id_field'] : '';
    $product->update_meta_data('_unique_import_id_field', sanitize_text_field($title));
    $product->save();
}
add_action('woocommerce_process_product_meta', 'cfwc_save_custom_field');
		
// Load All imeplementations
require_once JSD_PARSER_FRAMEWORK_DIR . '/implementations/brel.php';








 