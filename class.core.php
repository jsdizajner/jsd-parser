<?php
defined('ABSPATH') || exit;

/**
 * Load all Plugin Data into JSD_PLUGIN_DATA
 */

if (!function_exists('get_plugin_data')) {
	require_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

class JSD__PARSER_CORE
{
	public static $info = [
		'name'      		=> JSD_PARSER_PLUGIN_DATA['Name'],
		'author'    		=> JSD_PARSER_PLUGIN_DATA['Author'],
		'version'  		 	=> JSD_PARSER_PLUGIN_DATA['Version'],
        'slug' 				=> 'jsdizajner-xml-parser',
		'docs'				=> 'https://documentation.jsdizajner.com/',
		'update'			=> 'https://update.jsdizajner.com/',
		'plugin_file_path'	=> JSD_PARSER_FRAMEWORK_DIR . 'jsd-parser.php',
		'checker'			=> JSD_PARSER_FRAMEWORK_DIR . 'includes/plugin-update-checker/plugin-update-checker.php',
		'style'				=> '/assets/css/admin.css',
	];

	public function __construct()
	{
		self::init();
	}

	public static function init()
	{

		require JSD_PARSER_FRAMEWORK_DIR . '/vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';
		$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
			'http://example.com/path/to/details.json',
			self::$info['plugin_file_path'], //Full path to the main plugin file or functions.php.
			self::$info['slug']
		);

		//Set the branch that contains the stable release.
        $MyUpdateChecker->setBranch('master');
        $MyUpdateChecker->getVcsApi()->enableReleaseAssets();
	}

    
}

?>