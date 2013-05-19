<?php
set_time_limit(0);

/*
 * Handle the price adjusting cron
 * */

class WoocommerceAmazonPrice{
	
	const interval = "everythreehour";
	const hook = "update_product_price";
	const option_key = "woocommerce_amazon_update";
	
	static function init(){
	//	add_action('init', array(get_class(), 'update_product_information'));
		
		add_filter('cron_schedules', array(get_class(), 'add_new_interval'));
		
		register_activation_hook(WPROBOTWOOCOMMERCE_FILE, array(get_class(), 'create_scheduler'));
		register_deactivation_hook(WPROBOTWOOCOMMERCE_FILE, array(get_class(), 'clear_scheduler'));
		add_action(self::hook, array(get_class(), 'update_product_information'));		
	}
		
	
	
	//add a new scheduler
	static function add_new_interval($schedules){
		$schedules['everytwohour'] = array(
			'interval' => 2 * HOUR_IN_SECONDS,
			'display' => 'Every Two Hour'
		);
		
		$schedules['everythreehour'] = array(
			'interval' => 3 * HOUR_IN_SECONDS,
			'display' => 'Every Three Hour'
		);
		
		return $schedules;
	}
	
	
	//create scheduler
	static function create_scheduler(){
		if(!wp_next_scheduled(self::hook)) {
			wp_schedule_event( current_time( 'timestamp' ) + 1200, self::interval, self::hook);
		}
	}
	
/*
	 * clear the scheduler
	 * */
	static function clear_scheduler(){
		wp_clear_scheduled_hook(self::hook);
	}
	
	
	//main funciton to handle everyting
	static function update_product_information(){
		global $wpdb;
		
		//getting the previous cron offset
		$prev_info = get_option(self::option_key);	
		$offset = (isset($prev_info['offset'])) ? (int) $prev_info['offset'] : 0;
		$limit = 300;
		
		
		
		
		//offset checking
		$maximum_product_sql = "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_status LIKE 'publish' AND post_type LIKE 'product'";
		$max_product_id = $wpdb->get_var($maximum_product_sql);
		
		if($offset > (int) $max_product_id){
			$offset = 0;
		}
		
		
		
		//getting products
		$products = self::get_products($limit, $offset);
		
		//var_dump($products); die();
		
		//if product exists increment the offset by limit number
		if($products){
			$offset += $limit;
			update_option(self::option_key, array('time'=>current_time('timestamp'), 'offset'=>$offset));
		}
	
		
		if($products){
				
			
			$pas = new AmazonPAS();
			$pas->set_locale(PAS_LOCALE_US);
						
			foreach($products as $pr){
				
				$opt = array(
					'IdType' => 'ASIN',
					'ResponseGroup' => 'OfferFull',				
					'IncludeReviewsSummary' => "False"
				);
				
				
				$amazon_reviews = self::get_product_reviews($pr->meta_value);
								
												
				$offer = $pas->item_lookup($pr->meta_value, $opt);
				
				 
				
				if($offer->isOk()){
					foreach($offer->body->Items->Item as $item){
						
						//checking amazon availability checking
						$availability = (string) $item->Offers->Offer->OfferListing->AvailabilityAttributes->AvailabilityType;
						$min_hours = (string) $item->Offers->Offer->OfferListing->AvailabilityAttributes->MinimumHours;
						$max_hours = (string) $item->Offers->Offer->OfferListing->AvailabilityAttributes->MaximumHours;
						
						if($availability == 'now' || !empty($min_hours) || !empty($max_hours)) {
						
							$list_price = (string) $item->Offers->Offer->OfferListing->Price->FormattedPrice;
							$price = (string) $item->OfferSummary->LowestNewPrice->FormattedPrice;
							
							$sanitized_price = self::sanitize_price($list_price, $price);
							
							if($sanitized_price['p'] > 0){
								update_post_meta($pr->ID, '_regular_price', $sanitized_price['l']);
								update_post_meta($pr->ID, '_sale_price', $sanitized_price['p']);
								update_post_meta($pr->ID, '_price', $sanitized_price['p']);
							}
							
							if($amazon_reviews){
								update_post_meta($pr->ID, 'avg_rating', $amazon_reviews['avg_rating']);
								update_post_meta($pr->ID, 'review_count', $amazon_reviews['count']);
							}
						
						}
						else{
							wp_delete_post($pr->ID);
						}
						
					}
				}
								
			}
		}
		
	}
	
	
	
	/*
	 * sanitize price
	 * */
	static function sanitize_price($list_price, $price){
		$list_price = preg_replace('/[^0-9.]/', '', $list_price);
		$price = preg_replace('/[^0-9.]/', '', $price);
		
		if(empty($price) && $list_price > 0){
			$price = $list_price;
		}
		
		return array(
			'l' => $list_price,
			'p' => $price
		);
		
	}
	
	
	
	//get products
	static function get_products($limit, $offset){
		
		global $wpdb;
				
		$sql = "SELECT $wpdb->posts.ID, $wpdb->postmeta.meta_value FROM $wpdb->posts INNER JOIN $wpdb->postmeta on $wpdb->posts.ID = $wpdb->postmeta.post_id WHERE $wpdb->posts.post_status = 'publish' AND $wpdb->posts.post_type = 'product' AND $wpdb->postmeta.meta_key LIKE 'ASIN' ORDER BY $wpdb->posts.post_date ASC LIMIT %d, %d";
				
		$results = $wpdb->get_results($wpdb->prepare($sql, $offset, $limit));		
		
		return $results;
	}
	
	
	//get product average reviews and and review numbers
	static function get_product_reviews($asin){
		$pas = new AmazonPAS();
		$pas->set_locale(PAS_LOCALE_US);
		$opt = array(
					'IdType' => 'ASIN',
					'ResponseGroup' => 'Reviews',
					'IncludeReviewsSummary' => "True"
				);
		
		$offer = $pas->item_lookup($asin, $opt);
		if($offer->isOk()){
			foreach($offer->body->Items->Item as $item){
				if((string) $item->CustomerReviews->HasReviews == 'true'){
					$revcontent = file_get_contents((string) $item->CustomerReviews->IFrameURL);
					
					//var_dump($revcontent);
					
					if($revcontent){
						preg_match('/<div class="crIFrameNumCustReviews">.*?<\/div>/msi', $revcontent, $reviewdiv);
					
						if(isset($reviewdiv[0])){
							
							//average reviews
							preg_match('/<img.*? \/>/msi', $reviewdiv[0], $img);
						//	var_dump($img);
							
							preg_match('/title="(.*?)"/msi', $img[0], $title);
						//	var_dump($title);
							
							preg_match('/([0-9].*?)[a-zA-Z ]/msi', $title[1], $avg_rating);
						//	var_dump($avg_rating);
							
							$rating = preg_replace('/[^0-9.]/', '', $avg_rating[1]);
						//	var_dump($rating);
							
							//reveiw count
							preg_match('/\(<a.*?>(.*?)<\/a>\)/msi', $reviewdiv[0], $count);
						//	var_dump($count);
							
							$review_count = preg_replace('/[^0-9.]/', '', $count[1]);
						//	var_dump($review_count);
							
							return array('avg_rating'=>$rating, 'count'=>$review_count);
						}
					}
									
					
				}
				
			}
		}
		
		return false;
	}
	
	
	
	//get amazon response
	
}