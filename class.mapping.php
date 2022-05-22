<?php
defined('ABSPATH') || exit;

use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;

class JSD__PARSER_MAPPING extends JSD__PARSER_FACTORY
{

    /**
    * Filter product by set category
    *
    * @param array $list_of_categories
    * @param array $input
    * @return bool
    */
   public static function filter_product_by_categories($list_of_categories, $input = [])
   {

      if (is_string($input)) {
         if (in_array($input, $list_of_categories)) : return true; endif;
      } 

      foreach ($list_of_categories as $filtered_category) {
         if (in_array($filtered_category, $input)) : return true; endif;
      }

      return false;
   
   }

   /**
     * Create unique import id from input
     * ID is used for productExist Check
     *
     * @param mixed $id input ID
     * @return string Unique MetaData ID
     */
    public static function create_unique_import_id($id, $xml_name)
    {
        return 'uID__' . $xml_name . '_' . $id;
    }

    /**
     * Parce UTF-8 to HTML5
     *
     * @param string $description UTF-8 Description
     * @return string Decoded HTML5 Code
     */
    public static function get_description($description)
    {
        $decode = htmlspecialchars_decode($description, ENT_HTML5);
        return $decode;
    }

    /**
     * Calculate the price of the product
     *
     * @param float $price
     * @param int $q
     * @return string $finalPrice
     */
    public static function calculate_price($price, $q)
    {  
        $price = str_replace(',', '.', $price);
        return (float)$price * $q;
    }

    /**
     * Check stock status from XML Data
     * 
     * @param string $stock
     * @param array $config
     * @return string 'instock' || 'outofstock' || 'onbackorder'
     */
    public static function get_stock_status($stock, $config)
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
     * Check if product exist based on unique importer ID
     *
     * @param string  $id  Unique ID - mostly $jsdID
     * @return bool
     */
    public static function check_if_product_exist($id)
    {
        if (empty($id)) : return null; endif;
        $status = [
            'exist' => false,
            'id'    => null,
        ];
        $current_products = get_option(JSD__PARSER_CORE::$current_data['current_eshop_products']);
        for ($i = 0; $i < count($current_products); $i++) {
            if ($current_products[$i]['jsd_id'] === $id) :
                $status = ['exist' => true, 'id' => $current_products[$i]['ID']];
            endif;

            if ($current_products[$i]['jsd_id'] === null) :
                $status = ['exist' => false, 'id' => null];
            endif;
        } 
        return $status;
    }

}