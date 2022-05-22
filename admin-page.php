<?php
defined('ABSPATH') || exit;

if (isset($_POST['run_importer'])) {
    $slugs = JSD__PARSER_FACTORY::create_options_slugs('brel');
    $config = get_option($slugs['implementation_config']);
    $products = get_option($slugs['products_from_xml']);
    new JSD__PARSER_CYCLE($config, $products);
}

?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

<?php
// foreach (JSD__PARSER_FACTORY::$wp_options_slugs as $key => $value) {
//     echo '<pre>';
//     echo $key;
    
//     echo '</pre>';
// }
var_dump(get_option(JSD__PARSER_FACTORY::$wp_options_slugs['cycle_next_position']));
var_dump(get_option(JSD__PARSER_FACTORY::$wp_options_slugs['cycle_is_finished']));
$config = get_option(JSD__PARSER_FACTORY::$wp_options_slugs['implementation_config']);



?>
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
    </section>

</section>