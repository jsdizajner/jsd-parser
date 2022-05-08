<?php
defined('ABSPATH') || exit;

use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;

class JSD__PARSER_FACTORY
{

    /**
     * WooCommerce REST API
     */
    private $ck = 'ck_cdf63f1e2ef981b12be27ad8537c385f86453113';
    private $cs = 'cs_e9a7f6d5cacb181d585b66ce62df248575cb63e9';
    private $url = 'https://parcer.local/';

    public $type;
    public $WPoption;
    public $current = [];
    public $imported_products;
    public $report_name;
    public $importer;
    public $cycle_metadata;
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
     * @return void
     */
    public function __construct($config)
    {

        $woocommerce = $this->load_api();
        $this->type = $config['type'];
        $this->importer = $config['importer'];
        $this->WPoption = 'jsd_xml_parser_current_data_' . $config['_xml_name'];
        $this->report_name = 'jsd_xml_parser_report_' . $config['_xml_name'];

        /**
         * Load Current Data
         */

        // Current Products
        if ($config['auto_update'] === true ) : 
            delete_option($this->WPoption); delete_option('__jsd_xml_parcer_eshop_categories'); delete_option('__jsd_xml_parcer_eshop_attributes'); 
            $this->get_current_products($this->type);
            $this->get_current_product_categories();
            $this->get_current_product_attributes($woocommerce);
        endif;

        if (get_option($this->WPoption) === false) : $this->get_current_products($this->type); endif;
        $this->current['products'] = get_option($this->WPoption);

        // Current Categories
        if (get_option('__jsd_xml_parcer_eshop_categories') === false) : $this->get_current_product_categories(); endif;
        $this->current['categories'] = get_option('__jsd_xml_parcer_eshop_categories');

        // Current attributes
        if (get_option('__jsd_xml_parcer_eshop_attributes') === false) : $this->get_current_product_attributes($woocommerce); endif;
        $this->current['attributes'] = get_option('__jsd_xml_parcer_eshop_attributes');

        // If there is no report return blank array
        if (get_option($this->report_name) === false ) : add_option($this->report_name, []); endif;

    }

    /**
     * Load eShop REST API via WooCommerce API Library
     *
     * @return object WooCommerce API Connection
     */
    public function load_api()
    {

        $woocommerce = new Client(
            $this->url,
            $this->ck,
            $this->cs,
            [
                'wp_api' => true,
                'version' => 'wc/v3',
                'verify_ssl' => false,
                'timeout' => 120,
            ]
        );

        return $woocommerce;
    }

    /**
     * Start Importer loop, define chunks of products, run the loop
     *
     * @param array $products
     * @param int $chunks
     * @return void
     */
    public function importer($products, $chunks)
    {
        $data_sets = array_chunk($products, $chunks);
        $position = 0;
        $total = count($data_sets);

        // Collect Metadata for this cycle setup
        $this->cycle_metadata = [
            'chunks' => $chunks, 
            'iterations' => $total, 
        ];

        for ($position = 0; $position < $total; $position++ ) 
        {
            for ($position; $position < $total; $position++) 
            {
                if ($this->importer === 'native') : $this->import_products_wp_native_method($data_sets[$position], $position); endif;
                if ($this->importer === 'wc_product') : $this->import_product_data($data_sets[$position]); endif;
                break;
            }
        }

        $this->create_report();
    }

    /**
     * Import Products using WC_Product Class
     *
     * @param array $products
     * @return void
     */
    public function import_product_data($products)
    {
        try {

            // Initiate API Connection
            $woocommerce = $this->load_api();

            $importedProducts = [];
            $productCounter = 0;

            if ($this->type === 'simple') {
        
                foreach ($products as $product) {
                    // Prepare Attributes
                    if ($product['attributes'] != false) { 
                        $data = $this->check_attributes($product['attributes'], $this->type, $woocommerce);
                        $product['attributes'] = $data;
                    }
                    if ($product['attributes'] === false) : unset($product['attributes']); endif;

                    // Prepare Categories
                    if ($product['categories'] != false) {
                        $categories = $this->check_categories($product['categories'], $this->current['categories']);
                        $product['categories'] = $categories;
                    }

                    if ($product['categories'] === false) : $product['categories'] = [$this->default['category_id']]; endif;

                    // Prepare Images
                    if (isset($product['images'])) {
                        if (is_array($product['images'])) {
                            $image_ids = []; $i = 0;
                            foreach ($product['images'] as $image) { 
                                $image_ids[$i] = $this->upload_image_to_gallery($image['src']);
                            }
                        }
                        $image_ids = $this->upload_image_to_gallery($product['images'][0]['src']);
                        $product['images'] = $image_ids;
                    }

                    if ($product['exist']['exist'] === true) : 
                        $this->update_product($product);
                    endif;

                    if ($product['exist']['exist'] === false) :
                        $this->create_product($product);
                    endif;

                }

            }

            return $productCounter;

        }    
        catch (HttpClientException $e) {

            /**
             * Log the failed process into DB as WP_OPTIONS '__jsd_xml_parcer_svorto'
             */
            
            $SQLoptions = [
                'e_message' => $e->getMessage(),
                'e_code'    => $e->getCode(),
                'e_trace'   => $e->getTraceAsString(),
                'product_data'  => $product,
            ];
            echo '<pre>';
            var_dump($SQLoptions);
            echo '</pre>';
        }
    }

    /**
     * Import Products using wp_post function
     *
     * @param array $product_array
     * @return void
     */
    public function import_products_wp_native_method($product_array, $iteration)
    {
        if (!empty($product_array)) :

            if ($iteration === 0) : $counter = $iteration; 
            elseif ($iteration === 1) : $counter = $this->cycle_metadata['chunks']; 
            elseif ($iteration > 1) : $counter = $iteration * $this->cycle_metadata['chunks']; 
            endif;

            foreach ($product_array as $product) :

                // Prepare Images
                if (isset($product['images'])) {
                    if (is_array($product['images'])) {
                        $image_ids = []; $i = 0;
                        foreach ($product['images'] as $image) { 
                            $image_ids[$i] = $this->upload_image_to_gallery($image['src']);
                        }
                    }
                    $image_ids = $this->upload_image_to_gallery($product['images'][0]['src']);
                    $product['images'] = $image_ids;
                }

                // Create Product
                if ($product['exist']['exist'] === false) :
                    $post = [
                        'post_content' => $product['description'],
                        'post_status' => "publish",
                        'post_title' => wp_strip_all_tags($product['name']),
                        'post_name' => $product['name'],
                        'post_parent' => '',
                        'post_type' => "product",
                    ];
                    //Create Post
                    $product_id = wp_insert_post($post);

                    //set Product Image
                    set_post_thumbnail($product_id, $product['images'] );

                    //set Product Category
                    wp_set_object_terms($product_id, $product['categories'], 'product_cat');

                    //set product type
                    wp_set_object_terms($product_id, 'simple', 'product_type');

                    update_post_meta($product_id, '_visibility', 'visible');
                    update_post_meta($product_id, '_stock_status', $product['stock_status']);
                    update_post_meta($product_id, '_manage_stock', $product['manage_stock']);
                    update_post_meta($product_id, '_price', $product['price']);

                    if (isset($product['attributes'])) : update_post_meta($product_id, '_product_attributes', $product['attributes']); endif;
                    if (isset($product['_stock'])) : update_post_meta($product_id, '_stock', $product['_stock']); endif;

                    foreach ($product['meta_data'] as $metadata) {
                        update_post_meta($product_id, $metadata['key'], $metadata['value']);
                    }

                    $this->imported_products[$counter] = [
                        'product_id'    => $product_id,
                        'jsd_id'        => $product['jsd_id'],
                        'name'          => $product['name'],
                        'ean'           => $product['ean'],
                        'status'        => 'Added',
                    ];

                // Update Product
                else :
                    $post = [
                        'ID' => $product['id'],
                        'post_title' => $product['name'],
                        'post_content' => $product['description'],
                    ];

                    update_post_meta($product['id'], '_stock_status', $product['stock_status']);
                    update_post_meta($product['id'], '_price', $product['price']);

                    $post_id = wp_update_post($post, true);

                    $this->imported_products[$counter] = [
                        'product_id'    => $product['id'],
                        'jsd_id'        => $product['jsd_id'],
                        'name'          => $product['name'],
                        'ean'           => $product['ean'],
                        'status'        => 'Updated',
                    ];

                    if (is_wp_error($post_id))
                    {
                        $errors = $post_id->get_error_messages();
                        foreach ($errors as $error)
                        {
                            echo $error;
                        }
                    }
                endif;

                $counter++;

            endforeach;

        endif;
    }

    /**
     * Programatically create product using WC_Product Class
     *
     * @param array $data Prepared Product Data
     * @return bool
     */
    public static function create_product($product)
    {

        $woo = new WC_Product();
        $woo->set_name($product['name']);
        $woo->set_price($product['price']);
        $woo->set_regular_price($product['price']);
        $woo->set_description($product['description']);
        $woo->set_catalog_visibility('visible');
        $woo->set_stock_status($product['stock_status']);
        $woo->set_short_description($product['short_description']);
        $woo->set_category_ids(array_map('intval', $product['categories']));
        if (isset($product['attributes'])) : $woo->set_attributes($product['attributes']); endif;
        $woo->set_image_id($product['images']);
        foreach ($product['meta_data'] as $metadata) {
            $woo->update_meta_data($metadata['key'], $metadata['value']);
        }
        $woo->set_meta_data($product['meta_data']);
        return $woo->save();
    }

    /**
     * Programatically update product using WC_Product Class
     *
     * @param array $product Prepared Product Data
     * @param int $id Product ID
     * @return bool
     */
    public static function update_product($product)
    {  
        $woo = new WC_Product($product['id']);
        $woo->set_price($product['price']);
        $woo->set_regular_price($product['price']);
        $woo->set_stock_status($product['stock_status']);
        foreach ($product['meta_data'] as $metadata) {
            $woo->update_meta_data($metadata['key'], $metadata['value']);
        }
        $woo->set_meta_data($product['meta_data']);
        return $woo->save();
    }

    /**
     * If there are no product found by name, import them
     *
     * @param array $data
     * @param object $woocommerce
     * @return json
     */
    public function create_products($data, $woocommerce)
    {
        return $woocommerce->post('products', $data);
    }

    /**
     * If there are no matches in EAN, create new variation
     *
     * @param int $productID
     * @param array $data
     * @param object $woocommerce
     * 
     * @return json
     */
    public function create_variations($productID, $data, $woocommerce)
    {
        return $woocommerce->post('products/' . (string) $productID  . '/variations', $data);
    }

    /**
     * When there is a match in EAN within variations, just update the data
     *
     * @param int $productID
     * @param int $varationID
     * @param array $data
     * @param object $woocommerce
     * @return json
     */
    public function update_variations($productID, $varationID, $data, $woocommerce)
    {
        return $woocommerce->put('products/' . $productID  . '/variations' . '/' . $varationID, $data);
    }

    /**
     * When there are no attributes found by name, create new attribute
     *
     * @param string $attribute
     * @param object $woocommerce
     * @return int $id->id 
     */
    public function create_attribute($attribute, $woocommerce)
    {
        $data = [
            'name'      => (string) $attribute,
            'slug'      => 'svorto_' . $this->create_slug($attribute),
            'type'      => 'select',
            'order_by'  => 'menu_order',
            'has_archives' => true,
        ];

        $id = $woocommerce->post('products/attributes', $data);
        return $id->id;
    }

    /**
     * If attributes are created, check for attribute ID
     *
     * @param array $attribute
     * @param object $woocommerce
     * @return int
     */
    public function get_attribute_id($attribute, $woocommerce)
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
     * Assign Category ID
     *
     * @param array $category
     * @param object $woocommerce
     * @return  array Category ID
     */
    public function get_category_id($category, $woocommerce)
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
    public function unique_multidim_array($array, $key)
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
    public function attribute_term($id, $term)
    {
        if (empty($term)) {
            $term = '1';
        }

        return $term;
    }

    /**
     * Check attributes from eshop by name
     * 
     * @param array $attributes
     * @param string $type
     * @param object $woocommerce
     * @return array [int]
     */
    public function check_attributes($attributes, $type, $woocommerce)
    {

        // Assign IDs from Attributes
        $i = 0;
        $attIDs = [];
        foreach ($attributes as $attribute) {
            $attIDs[$i] = $attribute;
            $attIDs[$i]['id'] = $this->get_attribute_id($attribute, $woocommerce);
            $i++;
        }

        // Prepare Attribute Options
        $options = [];
        $i = 0;
        foreach ($attIDs as $att) {
            $options[$att['id']][$i] = $att['value'];
            $i++;
        }


        // Find unique Attributes from $attributes
        $unique = $this->unique_multidim_array($attIDs, 'name');


        // Map Attributes and Options
        $final = [];
        switch ($type) {
            case 'simple':
                $counter = 0;
                foreach ($unique as $att) {
                    $final[$counter] = [
                        'id' => $att['id'],
                        'variation' => false,
                        'visible'   => true,
                        'options'   => array_values($options[$att['id']])
                    ];
                    $counter++;
                }

                // Returned mapped attributes
                return $final;
            break;

            case 'variable':
                $counter = 0;
                foreach ($unique as $att) {
                    $final[$counter] = [
                        'id' => $att['id'],
                        'variation' => true,
                        'visible'   => true,
                        'options'   => array_values($options[$att['id']])
                    ];
                    $counter++;
                }

                // Returned mapped attributes
                return $final;
            break;
            
            default:
                $counter = 0;
                foreach ($unique as $att) {
                    $final[$counter] = [
                        'id' => $att['id'],
                        'variation' => false,
                        'visible'   => true,
                        'options'   => array_values($options[$att['id']])
                    ];
                    $counter++;
                }

                // Returned mapped attributes
                return $final;
            break;
        }

    }

    /**
     * Check stock status from XML Data
     * 
     * @param string $stock
     * @param array $config
     * @return string 'instock' || 'outofstock' || 'onbackorder'
     */
    public function get_stock_status($stock, $config)
    {
        if ($stock === $config['instock']) {
            return 'instock';
        } elseif ($stock === $config['outofstock']) {
            return 'outofstock';
        } elseif ($stock === $config['onbackorder']) {
            return 'onbackorder';
        }
    }

    /**
     * Get all images from XML and put them in array
     * 
     * @param array $images
     * @return array $imagesFormatted
     */
    public function get_product_images($images, $config)
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
    public function check_categories($categories, $woocommerce)
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
    public function create_category($category, $woocommerce)
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
    public function get_current_products($type)
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
        update_option($this->WPoption, $final);
    }

    /**
     * Get all current Product Attributes from eshop
     *
     * @param object $woocommerce
     * @return  array  Collection of current Product Attributes
     * @docs https://woocommerce.github.io/woocommerce-rest-api-docs/#list-all-product-attributes
     */
    public function get_current_product_attributes($woocommerce)
    {
        $currentAttributes = $woocommerce->get('products/attributes/');
        return update_option('__jsd_xml_parcer_eshop_attributes', $currentAttributes);
    }

    /**
     * Get all current Product Categories from eshop
     *
     * @return void;
     */
    public function get_current_product_categories()
    {
        global $wpdb;
        $data = $wpdb->get_results("SELECT wp_term_taxonomy.term_id, wp_terms.name FROM wp_term_relationships 
        LEFT JOIN wp_term_taxonomy ON (wp_term_relationships.term_taxonomy_id = wp_term_taxonomy.term_taxonomy_id) 
        LEFT JOIN wp_terms ON (wp_terms.term_id = wp_term_taxonomy.term_taxonomy_id) 
        WHERE wp_term_taxonomy.taxonomy = 'product_cat' 
        GROUP BY wp_term_taxonomy.term_id 
        order by wp_terms.name", ARRAY_A);

        $data['last_update'] = date('d-m-Y');
        update_option('__jsd_xml_parcer_eshop_categories', $data);

        return $data['last_update'];
    }

    /**
     * Check if product exist by slug
     * 
     * @param string $slug
     * @param array $shopProducts
     * @return array ['exist' => bool, 'id' => int] 
     */
    public function check_product_slug($slug, $shopProducts)
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
    public function create_slug($name, $divider = '-')
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
     * Calculate the price of the product
     *
     * @param float $price
     * @param int $q
     * @return string $finalPrice
     */
    public function calculate_price($price, $q)
    {  
        $price = str_replace(',', '.', $price);
        return (float)$price * $q;
    }

    /**
     * Create unique import id from input
     * ID is used for productExist Check
     *
     * @param mixed $id input ID
     * @return string Unique MetaData ID
     */
    public function create_unique_import_id($id)
    {
        return 'JSD__' . $id;
    }

    /**
     * Parce UTF-8 to HTML5
     *
     * @param string $description UTF-8 Description
     * @return string Decoded HTML5 Code
     */
    public function get_description($description)
    {
        $decode = htmlspecialchars_decode($description, ENT_HTML5);
        return $decode;
    }

    /**
     * Check if product exist based on unique importer ID
     *
     * @param string  $id  Unique ID - mostly $jsdID
     * @return bool
     */
    public function check_if_product_exist($id)
    {
        if (empty($id)) : return null; endif;
        $status = [
            'exist' => false,
            'id'    => null,
        ];
        for ($i = 0; $i < count($this->current['products']); $i++) {
            if ($this->current['products'][$i]['jsd_id'] === $id) :
                $status = ['exist' => true, 'id' => $this->current['products'][$i]['ID']];
            endif;

            if ($this->current['products'][$i]['jsd_id'] === null) :
                $status = ['exist' => false, 'id' => null];
            endif;
        } 
        return $status;
    }

    /**
     * Create a report from last cycle
     *
     * @return void
     */
    public function create_report()
    {
        // Prepare Report
        $report = array_chunk($this->imported_products, 25);
        update_option($this->report_name, $report);
    }

}
