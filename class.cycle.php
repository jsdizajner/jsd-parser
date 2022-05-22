<?php
defined('ABSPATH') || exit;

class JSD__PARSER_CYCLE extends JSD__PARSER_FACTORY
{  
    // Public Data
    public $products;
    public $cycle_metadata;
    public $imported_products;
    public $cycle_data;

    // Cycle Only Variables
    public $data_sets;
    public $importer_type;
    public $chunks;
    public $cycles;
    public $last_position;
    public $next_position;

    // Temporary Cycle Data
    public $cycle_last_position;
    public $cycle_next_position;
    public $cycle_is_finished = false;


    public function __construct($config, $products)
    {   
        // Load Factory settings
        parent::__construct($config, $products);
        
        // Prepare incoming arguments
        $this->products = get_option(self::$wp_options_slugs['products_from_xml']);
        $this->chunks = get_option(self::$wp_options_slugs['cycle_chunks']);
        $this->importer_type = get_option(self::$wp_options_slugs['cycle_importer_type']);
    
        // Prepare Cycle
        $this->data_sets = array_chunk($this->products, $this->chunks);
        $this->cycles = count($this->data_sets);
        $this->next_position = get_option(self::$wp_options_slugs['cycle_next_position']);
        if ($this->next_position === false ) : $this->next_position = 0; endif;

        // Run Cycle
        $this->cycle($this->next_position);

        // Log Initiation Process
        update_option(JSD__PARSER_FACTORY::$wp_options_slugs['initiated_implementation'], true);

    }

     /**
     * Start Importer loop, define chunks of products, run the loop
     *
     * @param int $position
     * @return void
     */
    public function cycle($position)
    {
        if ($position > $cycles) : update_option(self::$wp_options_slugs['cycle_is_finished'], true); return; endif;
        
        if (self::$wp_options_slugs['cycle_last_position'] === $position && $position != 0) : 
            return false;
        endif;

        $data_sets = $this->data_sets;
        $cycles = $this->cycles;

        // Collect Metadata for this cycle setup
        $this->cycle_metadata = [
            'chunks' => $this->chunks, 
            'iterations' => $this->cycles, 
        ];

        if ($this->importer_type === 'native') : 
            $iteration_status = $this->import_products_wp_native_method($data_sets[$position], $position);
        endif;

        if ($this->importer_type === 'wc_product') : 
            $iteration_status = $this->import_products_wc_method($data_sets[$position]); 
        endif;

        update_option(self::$wp_options_slugs['cycle_last_position'], $position);

        if ($iteration_status === true) : 
            $position++;
            update_option(self::$wp_options_slugs['cycle_next_position'], $position);
            if ($position > $cycles) : update_option(self::$wp_options_slugs['cycle_is_finished'], true); $this->create_report(); endif;
        endif;
    }

    /**
     * Import Products using WC_Product Class
     *
     * @param array $products
     * @return void
     */
    public function import_products_wc_method($products)
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

                    if (is_wp_error($post_id))
                    {
                        $errors = $post_id->get_error_messages();
                        foreach ($errors as $error)
                        {
                            echo $error;
                        }
                    }
                endif;

            endforeach;

            return true;

        endif;  
        
        return false;
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
     * Create a report from last cycle
     *
     * @return void
     */
    public function create_report()
    {
        // Prepare Report
        $products = get_option(self::$wp_options_slugs['imported_products']);
        $report = array_chunk($products, 2);
        update_option(self::$wp_options_slugs['report_name'], $report);
    }

    /**
     * Undocumented function
     *  
     * @return string $status
     */
    public function update_cycle_data()
    {
        $data = [
            'next_position' => $this->cycle_next_position,
            'last_position' => $this->cycle_last_position,
            'cycle_is_finished' => $this->cycle_is_finished,
            'current_iteration' => $this->imported_products,
        ];

        if (get_option(self::$wp_options_slugs['cycle_db_data']) === false) : 
            add_option(self::$wp_options_slugs['cycle_db_data'], $data);
        else :
            update_option(self::$wp_options_slugs['cycle_db_data'], $data);  
        endif;

        if (get_option(self::$wp_options_slugs['imported_products']) === false) :
            add_option(self::$wp_options_slugs['imported_products'], $this->imported_products);
        else :
            $current = get_option(self::$wp_options_slugs['imported_products']);
            $merged = array_push($current, $this->imported_products);   
            update_option(self::$wp_options_slugs['imported_product'], $merged);
        endif;

        return 'Cycle Data updated';
    }
}