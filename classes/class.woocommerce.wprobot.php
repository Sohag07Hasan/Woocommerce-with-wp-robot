<?php
/*
 * static class to handle everything
 * */
 

//include dirname(__FILE__) . '/api/cloudfusion.class.php';
 
class wprobot_woocommerce{
	
	static $wprobot_post = array();
	static $product = array();
	static $post_id = 0;
	
	
	static function init(){
	//	add_filter('the_content', array(get_class(), 'wprobot_post'));
		add_action('woocommerce_before_single_product', array(get_class(), 'wprobot_post'));
		add_action('woocommerce_before_shop_loop_item', array(get_class(), 'wprobot_post'));		
		//price modification
		//add_action('woocommerce_single_price', array('woocommerce_variation_sale_price_html'));
		
		//add_action('init', array(get_class(), 'wprobot_post'));
		
		add_filter('woocommerce_product_tabs', array(get_class(), 'products_features'), 20);
		
				
		
	}
	
	
	
	/*return products features*/
	static function products_features($tabs){
		if(wprobot_woocommerce::$wprobot_post){
			if(strlen(wprobot_woocommerce::$wprobot_post['features']) > 2){
				$tabs['features'] = array(
					'title' => __("Features"),
					'priority' => 5,
					'callback' => array(get_class(), 'get_product_features')
				);
			}
		}
		
		return $tabs;
	}
	
	
	//get the poduct features
	static function get_product_features(){
		woocommerce_get_template( 'single-product/tabs/features.php' );
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

	//	var_dump($post); die();
		
		self::$wprobot_post = array();
		
		if($post->post_type == 'product' && function_exists('wpr_aws_request')){			
			$asin = self::get_amazon_asin($post->ID);
			self::$wprobot_post = self::wpr_amazon_product(array('asin' => $asin));	
				
		}				
	}
	
	
	
	//boolean is sellable
	static function is_sell_able(){
		if(wprobot_woocommerce::$wprobot_post){
			$list_price = preg_replace('/[^0-9.]/', '', wprobot_woocommerce::$wprobot_post['list_price']);
			$price = preg_replace('/[^0-9.]/', '', wprobot_woocommerce::$wprobot_post['price']);

			return ($list_price > 0 || $price > 0) ? true : false;
		}
		else{
			return false;
		}		
		
	}
	
	
	//return the amazon asin
	static function get_amazon_asin($post_id){
		return get_post_meta($post_id, 'ASIN', true);
	}
	
	
		//get the amazon product
	function wpr_amazon_product($atts, $content = array()) {
		global $wpdb,$wpr_table_templates;
				
		
		$product_information = array();
		
		
		$options = unserialize(get_option("wpr_options"));	
		$public_key = $options['wpr_aa_apikey'];
		$private_key = $options['wpr_aa_secretkey'];
		$locale = $options['wpr_aa_site'];		
		$affid = $options['wpr_aa_affkey'];	
		
		/*
		$public_key = 'AKIAJO5VQCKEW6GFRIWQ';
		$private_key = 'fp5xj/xv6xELWedI2U3dL3/gA9/Eo8UpzDDzFtOB';
		$locale = 'us';		
		$affid = 'bouncerseat-20';
		*/
		
		if($locale == "us") {$locale = "com";}
		if($locale == "uk") {$locale = "co.uk";}

		
		$pxml = wpr_aws_request($locale, array(
		"Operation"=>"ItemLookup",
		"ItemId"=>$atts["asin"],
		"IncludeReviewsSummary"=>"False",
		"AssociateTag"=>$affid,
		"TruncateReviewsAt"=>"5000",
		"ResponseGroup"=>"Large"
	//	"ResponseGroup"=>"OfferSummary"
		), $public_key, $private_key);
		
		
		
	//	var_dump($pxml);
		
		
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
