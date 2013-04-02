<?php
/*
 * static class to handle everything
 * */

class wprobot_woocommerce{
	
	static $wprobot_post = array();
	static $product = array();
	static $post_id = 0;
	
	
	static function init(){
		
		//loop and single product fatching form amazon using wprobot
		add_action('woocommerce_before_single_product', array(get_class(), 'wprobot_post'));
		add_action('woocommerce_before_shop_loop_item', array(get_class(), 'wprobot_post'));		

		//single products tabs handling
		add_filter('woocommerce_product_tabs', array(get_class(), 'products_features'), 20);		
			
		//shopping cart actions
		remove_action('init', 'woocommerce_add_to_cart_action');
		remove_action('init', 'woocommerce_update_cart_action');
		
		add_action('init', array(get_class(), 'Amazon_add_to_cart_action'));
		//add_action('init', 'Amazon_update_cart_action');
				
		
	}
	
	
	
	/**************************************************************************************
	 * 
	 * Cart Managemment
	 * Using amazon advertising api
	 * saves cart as cookie
	 * nothing is static, everything comes from amazon
	 * 
	 * */
	static function Amazon_add_to_cart_action(){
		//die();
		if ( empty( $_REQUEST['add-to-cart'] ) || ! is_numeric( $_REQUEST['add-to-cart'] ) ) return;
		
		global $woocommerce;
		
		$product_id		= (int) $_REQUEST['add-to-cart'];
    	$quantity 		= (isset($_REQUEST['quantity'])) ? (int) $_REQUEST['quantity'] : 1;
    	$country = 'us';
    	
    	
    	
    	 if(class_exists('AmazonPAS')):
			$pas = new AmazonPAS();
			$offer_listing_id = array(self::get_amazon_asin($product_id) => $quantity);			
			$cartCookie = json_decode(stripslashes($_COOKIE["wo_rzon_cart_info"]));
			if($cartCookie != null){
				$response = $pas->cart_add($cartCookie->cart->cartid, $cartCookie->cart->hmac, $offer_listing_id, null, $cartCookie->cart->country);
										
			}
			else{
				$response = $pas->cart_create($offer_listing_id,null,$country);		
				if($response->isOK()){
					$cookie = array();
					$cartid = (string)$response->body->Cart->CartId;
					$hmac = (string)$response->body->Cart->HMAC;		
					$cart = array("cartid"=>$cartid,"hmac"=>$hmac,"country"=>$country);
					$cookie["cart"] = $cart;
					setcookie('wo_rzon_cart_info', json_encode(self::wo_arrayToObject($cookie)), time()+100*24*60*60, '/');
				}						
				
			}
		 endif;
		 
		 
		// var_dump($response); die();
		 
		 //checking if the response is successful
		if($response->isOK()) {
	    	woocommerce_add_to_cart_message($product_id);
	    	$was_added_to_cart = true;
		}
		
		
		// If we added the product to the cart we can now do a redirect, otherwise just continue loading the page to show errors
	    if ( $was_added_to_cart ) {
	
			$url = apply_filters( 'add_to_cart_redirect', $url );
	
			// If has custom URL redirect there
			if ( $url ) {
				wp_safe_redirect( $url );
				exit;
			}
	
			// Redirect to cart option
			elseif ( get_option('woocommerce_cart_redirect_after_add') == 'yes' && $woocommerce->error_count() == 0 ) {
				wp_safe_redirect( $woocommerce->cart->get_cart_url() );
				exit;
			}
	
			// Redirect to page without querystring args
			elseif ( wp_get_referer() ) {
				wp_safe_redirect( remove_query_arg( array( 'add-to-cart', 'quantity', 'product_id' ), wp_get_referer() ) );
				exit;
			}
	
	    }
    	
	}
	
	
	/*
	 * @array = mulitidimentional array
	 * returns an object
	 * */
	static function wo_arrayToObject($array) {
	    if(!is_array($array)) {
	        return $array;
	    }
	    
	    $object = new stdClass();
	    if (is_array($array) && count($array) > 0) {
	      foreach ($array as $name=>$value) {
	         $name = strtolower(trim($name));
	         if (!empty($name)) {
	            $object->$name = self::wo_arrayToObject($value);
	         }
	      }
	      return $object; 
	    }
	    else {
	      return FALSE;
	    }
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
			
			if(strlen(wprobot_woocommerce::$wprobot_post['description']) > 2){
				$tabs['amazondescription'] = array(
					'title' => __("Description"),
					'priority' => 3,
					'callback' => array(get_class(), 'get_product_description')
				);
			}
		}
		
		return $tabs;
	}
	
	
	//get the poduct features
	static function get_product_features(){
		woocommerce_get_template( 'single-product/tabs/amazon-features.php' );
	}
	
	
	//get product descriptions
	static function get_product_description(){
		woocommerce_get_template( 'single-product/tabs/amazon-description.php' );
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
	
	
	//get product id by asin
		

	
	
}

?>
