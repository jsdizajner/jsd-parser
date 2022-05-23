<?php
defined('ABSPATH') || exit;

if (isset($_POST['run_importer'])) {
    $slugs = JSD__PARSER_FACTORY::create_options_slugs('brel');
    $config = get_option($slugs['implementation_config']);
    $products = get_option($slugs['products_from_xml']);
    new JSD__PARSER_CYCLE($config, $products);
}
if (isset($_POST['reset_importer'])) {
    $slugs = JSD__PARSER_FACTORY::create_options_slugs('brel');
    delete_option(JSD__PARSER_CORE::$current_data['current_eshop_products']);
    JSD__PARSER_FACTORY::get_current_products(JSD__PARSER_CORE::$current_data['current_eshop_products']);
    update_option($slugs['cycle_next_position'], 0);
}
if (isset($_POST['stop_importer'])) {
    $slugs = JSD__PARSER_FACTORY::create_options_slugs('brel');
    $all_xml_products = get_option($slugs['products_from_xml']);
    $chunks = get_option($slugs['cycle_chunks']);
    $data_sets = array_chunk($all_xml_products, $chunks);
    $sets = count($data_sets) + 1;
    update_option($slugs['cycle_next_position'], $sets);
}

?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

<?php $config = get_option(JSD__PARSER_FACTORY::$wp_options_slugs['implementation_config']); ?>

<section class="import my-5 py-5">
    <section class="container">
        <div class="row">
            <div class="col-md-6">
                <div class="card border-primary mb-3">
                    <div class="card-body text-primary">
                        <h5 class="card-title mb-4"><?php echo __('Status Panel', 'jsd-parser'); ?></h5>
                        <section class="data my-2">
                            <div class="d-flex justify-content-between">
                                <h6 class="text-black"><?php echo __('API Connection:', 'jsd-parser');?></h6>
                                <p class="card-text text-dark">
                                    <?php if (JSD__PARSER_HELPERS::is_404($config['_api_path'])) : echo ' <span class="badge bg-success">ONLINE</span>'; else : echo ' <span class="badge bg-danger">Offline</span>'; endif; ?>
                                </p>
                            </div>
                            <div class="d-flex justify-content-between">
                                <h6 class="text-black"><?php echo __('Data Sets:', 'jsd-parser');?></h6>
                                <p class="card-text text-dark">
                                    <?php
                                    $all_xml_products = get_option(JSD__PARSER_FACTORY::$wp_options_slugs['products_from_xml']);
                                    $chunks = get_option(JSD__PARSER_FACTORY::$wp_options_slugs['cycle_chunks']);
                                    $data_sets = array_chunk($all_xml_products, $chunks);
                                    echo count($data_sets);
                                    ?>
                                </p>
                            </div>
                            <div class="d-flex justify-content-between">
                                <h6 class="text-black"><?php echo __('Next Iteration:', 'jsd-parser');?></h6>
                                <p class="card-text text-dark">
                                    <?php
                                    echo get_option(JSD__PARSER_FACTORY::$wp_options_slugs['cycle_next_position']);
                                    ?>
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
                        <div class="d-flex my-2">
                            <form method="post">
                                <button type="submit" name="run_importer" class="btn btn-primary"><?php echo __('Run Importer', 'jsd-parser'); ?></button>
                            </form>
                        </div>
                        <div class="d-flex my-2">
                            <form method="post">
                                <button type="submit" name="reset_importer" class="btn btn-warning"><?php echo __('Reset Importer', 'jsd-parser'); ?></button>
                            </form>
                        </div>
                        <div class="d-flex my-2">
                            <form method="post">
                                <button type="submit" name="stop_importer" class="btn btn-danger"><?php echo __('Stop Importer', 'jsd-parser'); ?></button>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>

</section>
