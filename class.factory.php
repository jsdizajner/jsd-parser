<?php
defined('ABSPATH') || exit;

use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;

class JSD__PARSER_FACTORY
{
    public $api_name;
    public static $wp_options_slugs = [];
    public $default = [
        'category_id' => 999,
        'image_src' => [
            'src' => 'https://raw.githubusercontent.com/koehlersimon/fallback/master/Resources/Public/Images/placeholder.jpg',
        ],
    ];

    /**
     * Initiate XML Factory
     *
     * @param array $config
     * @param array $products
     * @return void
     */
    public function __construct($config, $products)
    {
        $wp_options_slugs = $this->create_options_slugs($config['_xml_name']);
        self::$wp_options_slugs = $wp_options_slugs;

        // After First initiation of the plugin, create blank data
        if (get_option($wp_options_slugs['initiated_implementation']) === false) :
            foreach ($wp_options_slugs as $slug) {
                add_option($slug, []);
            }
            update_option($wp_options_slugs['cycle_next_position'], 0);
            update_option($wp_options_slugs['cycle_is_finished'], false);
            update_option($wp_options_slugs['report_name'], false);

            JSD__PARSER_FACTORY::get_current_data($config['auto_update']);
        endif;

        // Save config file to DB
        update_option($wp_options_slugs['implementation_config'], $config);

        // Parsed XML Products to DB
        update_option($wp_options_slugs['products_from_xml'], $products);

        // Prepare Cycle Data
        update_option($wp_options_slugs['cycle_chunks'], $config['chunks'] );
        update_option($wp_options_slugs['cycle_product_type'], $config['product_type'] );
        update_option($wp_options_slugs['cycle_importer_type'], $config['importer_type'] );
    }

    /**
     * Load eShop REST API via WooCommerce API Library
     *
     * @return object WooCommerce API Connection
     */
    public static function load_api()
    {
        $woocommerce = new Client(
            'https://parcer.local/',
            'ck_5b3cfd612cf39c0cafeb4b45a492c20e5b0ac013',
            'cs_3d65ffab0f702ed70321a25d658da43bba6e9e20',
            [
                'wp_api' => true,
                'version' => 'wc/v3',
                'verify_ssl' => false,
                'timeout' => 500,
            ]
        );

        return $woocommerce;
    }

    /**
     * If attributes are created, check for attribute ID
     *
     * @param array $attribute
     * @param object $woocommerce
     * @return int
     */
    public static function get_attribute_id($attribute, $woocommerce)
    {
        // Invalid Input
        if (empty($attribute) or !is_array($attribute)) : return 'Invalid Attribute input'; endif;

        // Assign ID Based on Current Attributes
        foreach ($this->current['attributes'] as $att) {
            if ($att->name === $attribute['name']) : return $att->id; endif;
        }

        // If ID is still null
        if ($attribute['id'] === null) {
            $data = [
                'name' => $attribute['name'],
                'type' => 'select',
                'order_by' => 'menu_order',
                'has_archives' => true
            ];
            $id = $woocommerce->post('products/attributes', $data);
            return $id->id;
        } 

    }

    /**
     * Gather local data from the store
     *
     * @param boolean $force
     * @return void
     */
    public static function get_current_data($force = false)
    {  
        if ($force === true) :
            delete_option(JSD__PARSER_CORE::$current_data['current_eshop_products']);
            delete_option(JSD__PARSER_CORE::$current_data['current_eshop_products']);
            delete_option(JSD__PARSER_CORE::$current_data['current_eshop_products']);
        endif;
        // Current Products
        if (get_option(JSD__PARSER_CORE::$current_data['current_eshop_products']) === false) : self::get_current_products(JSD__PARSER_CORE::$current_data['current_eshop_products']); endif;

        // Current Categories
        if (get_option(JSD__PARSER_CORE::$current_data['current_eshop_categories']) === false) : self::get_current_product_categories(JSD__PARSER_CORE::$current_data['current_eshop_categories']); endif;

        // Current attributes
        if (get_option(JSD__PARSER_CORE::$current_data['current_eshop_attributes']) === false) : self::get_current_product_attributes(JSD__PARSER_CORE::$current_data['current_eshop_attributes']); endif;
    }

    /**
     * Assign Category ID
     *
     * @param array $category
     * @param object $woocommerce
     * @return  array Category ID
     */
    public static function get_category_id($category, $woocommerce)
    {
        // Invalid Input
        if (empty($category) or !is_array($category)) : return 'Invalid Category input'; endif;

        // Assign ID Based on Current Categories
        for ($i = 0; $i > count($this->current['categories']); $i++) {
            if ($category['name'] === $this->current['categories'][$i]['name']) : return ['id' => $this->current['categories'][$i]['term_id'],]; endif;
        }

        // If the config is set to create new category if it is missing, create new category
        if ($category['create_category'] === true ) {
            // If ID is still null
            if ($category['id'] === null) {
                $data = [
                    'name'      => (string) $category['name'],
                ];
                $id = $woocommerce->post('products/categories', $data);
                return ['id' => $id->id];
            }
        }

        // Default
        return ['id' => $this->default['category_id'],];
    }

    /**
     * Find Unique items in an array using custom key
     *
     * @param array $array
     * @param string $key
     * @return array $temp_array
     */
    public static function unique_multidim_array($array, $key)
    {
        $temp_array = array();
        $i = 0;
        $key_array = array();

        foreach ($array as $val) {
            if (!in_array($val[$key], $key_array)) {
                $key_array[$i] = $val[$key];
                $temp_array[$i] = $val;
            }
            $i++;
        }
        
        return array_values($temp_array);
    }

    /**
     * Find and return attribute term
     *
     * @param $id
     * @param string $term
     * @return string $term
     */
    public static function attribute_term($id, $term)
    {
        if (empty($term)) {
            $term = '1';
        }

        return $term;
    }

    /**
     * Get all images from XML and put them in array
     * 
     * @param array $images
     * @return array $imagesFormatted
     */
    public static function get_product_images($images, $config)
    {
        if ($config === 'single') {
            foreach ($images as $image) {
                return $data[0] = ['src' => $image];
            }
        }

        if (is_int($config)) {
            $imagesFormated = [];
            for ($i = 0; $i < $config; $i++) {
                $imagesFormated[$i] = [
                    'src' => $images[$i],
                ];
            }

            return $imagesFormated;
        }

        return $this->default['images_src'];
    }

    /**
     * Upload Images to WP Gallery
     *
     * @param string $url
     * @return int $id
     */
    public static function upload_image_to_gallery($url)
    {
        // Required WP Core files
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $src = media_sideload_image($url, null, null, 'src');
        $id = attachment_url_to_postid($src);

        return $id;
    }

    /**
     * Check if category exist in eshop by name
     * 
     * @param array $categories
     * @param object $woocommerce
     * @return array $imported
     */
    public static function check_categories($categories, $woocommerce)
    {

        // Assign IDs from categories
        for ($i = 0; $i > count($categories); $i++) {
            $categories[$i]['id'] = $this->get_category_id($categories[$i], $woocommerce);
        }

        // Map $categories with IDs to $data
        // @docs https://woocommerce.github.io/woocommerce-rest-api-docs/#create-a-product
        for ($i = 0; $i > count($categories); $i++) {
            $data[$i] = [
                'id' => $categories[$i]['id'],
            ];
        }

        // Gather Data
        return $data;

    }

    /**
     * Create category 
     * 
     * @param string $name
     * @param object $woocommerce
     * @return [int] 
     */
    public static function create_category($category, $woocommerce)
    {
        $data = [
            'name'      => (string) $category,
        ];
        $category = $woocommerce->post('products/categories', $data);

        return $category->id;
    }

    /**
     * Get all current Products from eshop
     *
     * @return  array  Collection of current Products in Pages (100 items per page)
     */
    public static function get_current_products($option)
    {
        global $wpdb;
        $data = $wpdb->get_results("SELECT 
        p.ID, p.post_title,
        MAX(CASE WHEN t.name = 'simple' then t.name END) as product_type,
        MAX(CASE WHEN pm1.meta_key = '_unique_import_id_field' then pm1.meta_value ELSE NULL END) as jsd_id,
        MAX(CASE WHEN pm1.meta_key = '_stock_status' then pm1.meta_value ELSE NULL END) as stock_status,
        MAX(CASE WHEN pm1.meta_key = '_alg_ean' then pm1.meta_value ELSE NULL END) as ean
        FROM wp_posts p 
        LEFT JOIN wp_postmeta pm1 ON pm1.post_id = p.ID
        LEFT JOIN wp_term_relationships AS tr ON tr.object_id = p.ID
        JOIN wp_term_taxonomy AS tt ON tt.taxonomy = 'product_type' AND tt.term_taxonomy_id = tr.term_taxonomy_id 
        JOIN wp_terms AS t ON t.term_id = tt.term_id
        WHERE p.post_type in('product') AND p.post_status = 'publish' AND p.post_content <> ''
        GROUP BY p.ID,p.post_title", ARRAY_A);

        $i = 0;
        $final = [];
        foreach ($data as $set) {
            $final[$i] = $set;
            $i++;
        }
        update_option($option, $final);
    }

    /**
     * Get all current Product Attributes from eshop
     *
     * @param object $woocommerce
     * @return  array  Collection of current Product Attributes
     * @docs https://woocommerce.github.io/woocommerce-rest-api-docs/#list-all-product-attributes
     */
    public static function get_current_product_attributes($option)
    {
        $woocommerce = self::load_api();
        $currentAttributes = $woocommerce->get('products/attributes/');
        return update_option($option, $currentAttributes);
    }

    /**
     * Get all current Product Categories from eshop
     *
     * @return void;
     */
    public static function get_current_product_categories($option)
    {
        global $wpdb;
        $data = $wpdb->get_results("SELECT wp_term_taxonomy.term_id, wp_terms.name FROM wp_term_relationships 
        LEFT JOIN wp_term_taxonomy ON (wp_term_relationships.term_taxonomy_id = wp_term_taxonomy.term_taxonomy_id) 
        LEFT JOIN wp_terms ON (wp_terms.term_id = wp_term_taxonomy.term_taxonomy_id) 
        WHERE wp_term_taxonomy.taxonomy = 'product_cat' 
        GROUP BY wp_term_taxonomy.term_id 
        order by wp_terms.name", ARRAY_A);

        $data['last_update'] = date('d-m-Y');
        update_option($option, $data);

        return $data['last_update'];
    }

    /**
     * Check if product exist by slug
     * 
     * @param string $slug
     * @param array $shopProducts
     * @return array ['exist' => bool, 'id' => int] 
     */
    public static function check_product_slug($slug, $shopProducts)
    {

        // If there are no products
        if (empty($shopProducts)) {
            return ['exist' => false, 'id' => null];
        }

        // Check all existing products
        foreach ($shopProducts as $page => $products) {
            foreach ($products as $product) {
                $productSlug = $product->slug;

                // Get only 'svorto_' products
                $svortoSlug = 'svorto_';
                if (strpos($svortoSlug, $productSlug) !== false) {
                    // If the Slug Matches, the product exists
                    if ($productSlug === $slug) {
                        return ['exist' => true, 'id' => $product->id];
                    }
                }
            }
        }

        // Default: Product doesn't exist
        return ['exist' => false, 'id' => null];
    }

    /**
     * 
     * Create SLUG from import Product Name
     * 
     * @param string $name
     * @param string $divider
     * 
     * @return string $name or 'n-a'
     */
    public static function create_slug($name, $divider = '-')
    {

        // replace non letter or digits by divider
        $name = preg_replace('~[^\pL\d]+~u', $divider, $name);

        // transliterate
        $name = iconv('utf-8', 'us-ascii//TRANSLIT', $name);

        // remove unwanted characters
        $name = preg_replace('~[^-\w]+~', '', $name);

        // trim
        $name = trim($name, $divider);

        // remove duplicate divider
        $name = preg_replace('~-+~', $divider, $name);

        // lowercase
        $name = strtolower($name);

        if (empty($name)) {
            return 'n-a';
        }

        return $name;
    }

    /**
     * Creates unique slugs for WP OPTIONS
     *
     * @param string $api_name
     * @return array $wp_options_slugs
     */
    public static function create_options_slugs($api_name)
    {
        // Initiate Variable
        $wp_options_slugs = [];

        // Assign slugs
        $wp_options_slugs['implementation_config']      = 'jsd_parser_implementation_config_' . $api_name;
        $wp_options_slugs['initiated_implementation']   = 'jsd_parser_init_client_' . $api_name;
        $wp_options_slugs['imported_products']          = 'jsd_parser_imported_products_' . $api_name;
        $wp_options_slugs['products_from_xml']          = 'jsd_parser_xml_products_' . $api_name;
        $wp_options_slugs['report_name']                = 'jsd_parser_report_' . $api_name;
        $wp_options_slugs['cycle_last_position']        = 'jsd_parser_cycle_last_position_' . $api_name;
        $wp_options_slugs['cycle_next_position']        = 'jsd_parser_cycle_next_position_' . $api_name;
        $wp_options_slugs['cycle_is_finished']          = 'jsd_parser_cycle_is_finished_' . $api_name;
        $wp_options_slugs['cycle_report_data']          = 'jsd_parser_cycle_report_data_' . $api_name;
        $wp_options_slugs['cycle_product_type']         = 'jsd_parser_cycle_product_type_' . $api_name;
        $wp_options_slugs['cycle_importer_type']        = 'jsd_parser_cycle_importer_type_' . $api_name;
        $wp_options_slugs['cycle_chunks']               = 'jsd_parser_cycle_chunks_' . $api_name;

        // Return slugs
        return $wp_options_slugs;
    }

}
