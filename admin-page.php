<?php

$config = [
    '_xml_name'             => 'brel',
    '_api_path'             => 'https://www.brel.sk/userdata/temp/productlist.xml',
    'auto_update'           => true,
    'type'                  => 'simple',
    'importer'              => 'native',
];

$xml = new JSD__PARSER_XML($config);
$data = $xml->get_xml_data();
$products = $data->Products;
$factory = new JSD__PARSER_FACTORY($config);
$woocommerce = $factory->load_api();


$i = 0;
$imported = [];
foreach ($products->ProductItem as $product) {
    
    $category = (string) $product->ProductCategory;
    $category = explode(' | ', $category);
    $category_filter = JSD__PARSER_HELPERS::filter_product_by_categories(['Starostlivosť o zdravie', 'Starostlivosť o telo', 'Starostlivosť o dieťa'], $category);
    

    // Filter by categories
    if ($category_filter) {

        $jsdID = $factory->create_unique_import_id($product->ProductID);
        $ean = (string) $product->ProductEan;
        $name = (string) $product->ProductName;
        $images = [
            [
                'src' => (string) $product->ProductImgUrl,
            ],
        ];
        $desc = $factory->get_description((string) $product->ProductDescription);
        $short_desc = '';
        $price = $factory->calculate_price((string) $product->ProductPrice_VAT, 1);
        $stock = $factory->get_stock_status((string)$product->ProductInventory, ['instock' => 'Skladom', 'outofstock' => 'Na objednávku', 'onbackorder' => '',]);
        $exist = $factory->check_if_product_exist($jsdID);

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

if (isset($_POST['run_importer'])) {
    $factory->importer($imported, 20);
}

?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

<section class="import my-5 py-5">
    <section class="container">
        <div class="row">
            <div class="col-md-6">
                <div class="card border-primary mb-3">
                    <div class="card-body text-primary">
                        <h5 class="card-title mb-4"><?php echo __('Status Panel', 'jsd-parser'); ?></h5>
                        <section class="data my-2">
                            <div class="d-flex justify-content-between">
                                <h6 class="text-black"><?php echo __('Product Count:', 'jsd-parser');?></h6>
                                <p class="card-text text-dark"> <?php echo ' ' . count($imported); ?></p>
                            </div>
                            <div class="d-flex justify-content-between">
                                <h6 class="text-black"><?php echo __('Last Cycle:', 'jsd-parser');?></h6>
                                <p class="card-text text-dark">
                                    <?php if (!is_array($factory->imported_products)) : echo '0'; else: echo ' ' . count($factory->imported_products); endif; ?>
                                </p>
                            </div>
                            <div class="d-flex justify-content-between">
                                <h6 class="text-black"><?php echo __('API Connection:', 'jsd-parser');?></h6>
                                <p class="card-text text-dark">
                                    <?php if (JSD__PARSER_HELPERS::is_404($config['_api_path'])) : echo ' <span class="badge bg-success">ONLINE</span>'; else : echo ' <span class="badge bg-danger">Offline</span>'; endif; ?>
                                </p>
                            </div>
                            <div class="my-3">
                                <h6 class="text-black"><?php echo __('Plugin Implementation:', 'jsd-parser');?></h6>
                                <p class="card-text text-dark">
                                    <?php echo 'Version: ' . JSD__PARSER_CORE::$info['version'] . '<br/>'; ?>
                                    <?php echo 'Documentation: ' . '<a href=" ' . JSD__PARSER_CORE::$info['docs'] . '" target="_blank">Technical Documentation</a> <br/>'; ?>
                                </p>
                            </div>
                        </section>
                        <form method="post">
                            <button type="submit" name="run_importer" class="btn btn-primary"><?php echo __('Run Importer', 'jsd-parser'); ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">JSD_ID</th>
                    <th scope="col">Name</th>
                    <th scope="col">EAN</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <?php if (get_option($factory->report_name) != false) : ?>
            <tbody>
                <?php $metadata = get_option($factory->report_name); ?>
                <?php for ($i = 0; $i < count($metadata); $i++) { ?>
                    <?php foreach ($metadata[$i] as $data) { ?>
                    <tr>
                        <td><?php echo $data['product_id']; ?></td>
                        <td><?php echo $data['jsd_id']; ?></td>
                        <td><?php echo $data['name']; ?></td>
                        <td><?php echo $data['ean']; ?></td>
                        <td><?php echo $data['status']; ?></td>
                    </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
            <?php endif; ?>
        </table>
    </section>

</section>