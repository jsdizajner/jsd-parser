<?php
defined('ABSPATH') || exit;

class JSD__PARSER_HELPERS
{
   /**
    * Is URL 404
    *
    * @param string $url
    * @return boolean
    */
   public static function is_404($url)
   {
      $headers = get_headers($url);
      $status = $headers[0];
      if (strpos($status, "200")) : return true; else : return false; endif; 

   }

}


// update_post_meta($product_id, '_visibility', 'visible');
// update_post_meta($product_id, '_stock_status', 'instock');
// update_post_meta($product_id, '_product_attributes', array());
// update_post_meta($product_id, '_manage_stock', "yes");
// update_post_meta($product_id, '_backorders', "no");
// update_post_meta($product_id, '_stock', $product['qty']);
// update_post_meta($product_id, '_price', $product['price']);
// update_post_meta($product_id, '_downloadable', 'yes');
// update_post_meta($product_id, '_virtual', 'yes');
// update_post_meta($product_id, '_regular_price', "1");
// update_post_meta($product_id, '_sale_price', "1");
// update_post_meta($product_id, '_purchase_note', "");
// update_post_meta($product_id, '_featured', "no");
// update_post_meta($product_id, '_weight', "");
// update_post_meta($product_id, '_length', "");
// update_post_meta($product_id, '_width', "");
// update_post_meta($product_id, '_height', "");
// update_post_meta($product_id, '_sale_price_dates_from', "");
// update_post_meta($product_id, '_sale_price_dates_to', "");
// update_post_meta($product_id, '_price', "1");
// update_post_meta($product_id, '_sold_individually', "");