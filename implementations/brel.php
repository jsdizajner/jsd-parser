<?php
defined('ABSPATH') || exit;


// Configure XML Parser
$config = [
    '_xml_name'             => 'brel',
    '_api_path'             => 'https://www.brel.sk/userdata/temp/productlist.xml',
    'auto_update'           => true,
    'product_type'          => 'simple',
    'importer_type'         => 'native',
    'chunks'                => 20,
    'test_data'             => false,
];

function load_raw_data_brel($config) {
    $xml = new JSD__PARSER_XML($config);
    $products = $xml->get_xml_data()->Products;

    $i = 0;
    $imported = [];
    foreach ($products->ProductItem as $product) {
    
        $category = (string) $product->ProductCategory;
        $category = explode(' | ', $category);
        $category_filter = JSD__PARSER_MAPPING::filter_product_by_categories(['Starostlivosť o zdravie', 'Starostlivosť o telo', 'Starostlivosť o dieťa'], $category);
        

        // Filter by categories
        if ($category_filter) {

            $jsdID = JSD__PARSER_MAPPING::create_unique_import_id($product->ProductID, $config['_xml_name']);
            $ean = (string) $product->ProductEan;
            $name = (string) $product->ProductName;
            $images = [
                [
                    'src' => (string) $product->ProductImgUrl,
                ],
            ];
            $desc = JSD__PARSER_MAPPING::get_description((string) $product->ProductDescription);
            $short_desc = '';
            $price = JSD__PARSER_MAPPING::calculate_price((string) $product->ProductPrice_VAT, 1);
            $stock = JSD__PARSER_MAPPING::get_stock_status((string)$product->ProductInventory, ['instock' => 'Skladom', 'outofstock' => 'Na objednávku', 'onbackorder' => '',]);
            $exist = JSD__PARSER_MAPPING::check_if_product_exist($jsdID);

            $imported[$i] = [
                'name'                  => $name,
                'price'                 => (string)$price,
                'type'                  => 'simple',
                'description'           => $desc,
                'short_description'     => '',
                'manage_stock'          => false,
                'stock_status'          => $stock,
                'categories'            => end($category),
                'attributes'            => false,
                'images'                => $images,
                'jsd_id'                => $jsdID,
                'ean'                   => $ean,
                'exist'                 => $exist,
                'meta_data'             => [
                    [
                        'key'   => '_unique_import_id_field',
                        'value' => $jsdID,
                    ],
                    [
                        'key'   => '_alg_ean',
                        'value' => $ean,
                    ],
                ],
            ];

            if ($exist['exist'] === true) : $imported[$i]['id'] = $exist['id']; endif;

            $i++;

        } 
        else {
            continue;
        }
    }
    return $imported;
}

$imported = load_raw_data_brel($config);
$factory = new JSD__PARSER_FACTORY($config, $imported);

add_filter( 'cron_schedules', 'add_fifteen_minute_cron_interval' );
function add_fifteen_minute_cron_interval( $schedules ) { 
    $schedules['fifteen_minutes'] = array(
        'interval' => 900,
        'display'  => esc_html__( 'Every 15 minutes' ), );
    return $schedules;
}

register_activation_hook(__FILE__, 'brel_cron_job_activation');
 
function brel_cron_job_activation() {
    if (! wp_next_scheduled ( 'brel_importer_cron_job' )) {
    wp_schedule_event(time(), 'fifteen_minutes', 'brel_importer_cron_job', [$api_name]);
    }
}
 
add_action('brel_importer_cron_job', 'brel_cron_job');
 
function brel_cron_job($api_name) {
    $slugs = JSD__PARSER_FACTORY::create_options_slugs($api_name);
    $config = get_option($slugs['implementation_config']);
    if (date('D') == 'Sat') :
        delete_option($slugs['initiated_implementation']);
        $imported = load_raw_data_brel($config);
        new JSD__PARSER_FACTORY($config, $imported);
    endif;
    $products = get_option($slugs['products_from_xml']);
    new JSD__PARSER_CYCLE($config, $products);
}
