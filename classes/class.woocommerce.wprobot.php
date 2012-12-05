<?php
/*
 * static class to handle everything
 * */
 

include dirname(__FILE__) . '/api/cloudfusion.class.php';
 
class wprobot_woocommerce{
	
	static $wprobot_post = array();
	static $product = array();
	static $post_id = 0;
	
	
	static function init(){
		//add_filter('the_content', array(get_class(), 'wprobot_post'));
		add_action('woocommerce_before_single_product', array(get_class(), 'wprobot_post'));		
		//price modification
		//add_action('woocommerce_single_price', array('woocommerce_variation_sale_price_html'));
		
	}
	
	
	//filter the price
	static function woocommerce_variation_sale_price_html($price, $product){
		die();
		$price .= '<del><span class="amount">'.self::$wprobot_post['list_price'].'</span></del> <ins><span class="amount">'.self::$wprobot_post['price'].'</span></ins>';
		echo 'aweful';
		echo $price;
	}
	
	
	static function wprobot_post(){
		global $post;
			
		self::$wprobot_post = array();
		
		if($post->post_type == 'product' && function_exists('wpr_aws_request')){			
			$asin = get_amazon_asin($post->ID);
			self::$wprobot_post = self::wpr_amazon_product(array('asin' => $asin));			
					
		}
				
	}
	
	
		//get the amazon product
	function wpr_amazon_product($atts, $content = array()) {
		global $wpdb,$wpr_table_templates;
		
		//return $wpr_table_templates;
		
		$product_information = array();
		
		$options = unserialize(get_option("wpr_options"));	
		$public_key = $options['wpr_aa_apikey'];
		$private_key = $options['wpr_aa_secretkey'];
		$locale = $options['wpr_aa_site'];		
		$affid = $options['wpr_aa_affkey'];	
		if($locale == "us") {$locale = "com";}
		if($locale == "uk") {$locale = "co.uk";}	
		$pxml = wpr_aws_request($locale, array(
		"Operation"=>"ItemLookup",
		"ItemId"=>$atts["asin"],
		"IncludeReviewsSummary"=>"False",
		"AssociateTag"=>$affid,
		"TruncateReviewsAt"=>"5000",
		"ResponseGroup"=>"Large"
		), $public_key, $private_key);
		//echo "<pre>";print_r($pxml);echo "</pre>";
		if ($pxml === False) {
			return $product_information;
		} else {
			if($pxml->Items->Item->CustomerReviews->IFrameURL) {
				
				
				foreach($pxml->Items->Item as $item) {	

					$desc = "";					
					if (isset($item->EditorialReviews->EditorialReview)) {
						foreach($item->EditorialReviews->EditorialReview as $descs) {
							$desc .= $descs->Content;
						}		
					}	
					
					$elength = ($options['wpr_aa_excerptlength']);
					if ($elength != 'full') {
						$desc = strip_tags($desc,'<br>');
						$desc = substr($desc, 0, $elength);
					}				
									
					$product_information['description'] = $desc;
					
					$features = "";
					if (isset($item->ItemAttributes->Feature)) {	
						$features = "<ul>";
						foreach($item->ItemAttributes->Feature as $feature) {
							$posx = strpos($feature, "href=");
							if ($posx === false) {
								$features .= "<li>".$feature."</li>";		
							}
						}	
						$features .= "</ul>";				
					}
					
					$product_information['features'] = $features;
					

					$price = str_replace("$", "$", $item->OfferSummary->LowestNewPrice->FormattedPrice);
					$listprice = str_replace("$", "$", $item->ItemAttributes->ListPrice->FormattedPrice);
									

					if($price == "Too low to display" || $price == "Price too low to display") {
						$price = $listprice;
					}
					
					$product_information['price'] = $price;
					$product_information['list_price'] = $listprice;
					$product_information['Thumbnail_Large'] = $item->LargeImage->URL;
					$product_information['Thumbnail_Medium'] = $item->MediumImage->URL;
					$product_information['Thumbnail_Small'] = $item->SmallImage->URL;
					$product_information['ASIN'] = $item->ASIN;
									
					return $product_information;
				}		

				
			} else {
				return $product_information;	
			}
		}
		
		return $product_information;
	}
	
	
	static function get_amazon_product($post_id){
		/*
		if($post_id == self::$post_id) return self::$product;
				
		self::$post_id = $post_id;
		*/
		$asin = get_amazon_asin($post_id);
		self::$product = self::wpr_amazon_product(array('asin' => $asin));
		return self::$product;			
	}

	
	
}

?>
