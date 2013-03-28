<?php
set_time_limit(0);
/*
 * Handles the cron jobs
 * */

class WpRobotWocommerceCron{
	
	const interval = "hourly";
	const hook = 'schedule_posts_to_product';
	
	//contains all the hooks
	static function init(){
		register_activation_hook(WPROBOTWOOCOMMERCE_FILE, array(get_class(), 'create_scheduler'));
		register_deactivation_hook(WPROBOTWOOCOMMERCE_FILE, array(get_class(), 'clear_scheduler'));
		add_action(self::hook, array(get_class(), 'schedule_posts_to_product'));
		
		
		add_action('after_delete_post', array(get_class(), 'delete_associated_product'));
		
	
	}
		
	
	/*
		if a post is deleted, it deletes associated post
	*/
	static function delete_associated_product($post_id){
		global $wpdb;
		$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key = 'woocommerce_id' AND meta_value = '$post_id'");
	}
	
	
	/*
	 * handle scheduler
	 * */
	static function create_scheduler(){
		
		if(!wp_next_scheduled(self::hook)) {
			wp_schedule_event( current_time( 'timestamp' ), self::interval, self::hook);
		}
	}
	
	
	/*
	 * clear the scheduler
	 * */
	static function clear_scheduler(){
		wp_clear_scheduled_hook(self::hook);
	}
	
	
	/*
	 * do everything
	 * */
	static function schedule_posts_to_product(){		
		
		$posts = self::get_50_posts();
		
		//var_dump($posts);	
		
		if(!empty($posts)) :
			global $wpdb;	
			
			self::handle_categories();
						
			
			foreach($posts as $post){
				
				$post_time = strtotime($post->post_date);
				$post_status = (current_time('timestamp') > $post_time) ? 'publish' : 'future';
				
				$post_data = array('post_title'=>$post->post_title, 'post_type'=>'product', 'post_status'=>$post_status, 'post_content'=>'', 'post_excerpt'=>'', 'post_date'=>$post->post_date);
				
								
				$ID = wp_insert_post($post_data);
							
				
				if($ID) :					
					
					update_post_meta($post->ID, 'woocommerce_id', $ID);
					update_post_meta($ID, 'post_id', $post->ID);
									
					$categories = wp_get_object_terms($post->ID, 'category', array('fields' => 'names'));					
					wp_set_object_terms($ID, $categories, 'product_cat');					
					
					
					$tags = wp_get_object_terms($post->ID, 'post_tag', array('fields' => 'names'));
					wp_set_object_terms($ID, $tags, 'product_tag');
					
					$post_metas = get_post_custom($post->ID);										
					
					if($post_metas) :					
						update_post_meta($ID, 'ASIN', $post_metas['AMAZON_ASIN'][0]);							
						update_post_meta($ID, 'Thumbnail_Large', $post_metas['Thumbnail_Large'][0]);

						/*
						update_post_meta($ID, 'Thumbnail_Medium', $post_metas['Thumbnail_Medium'][0]);			
						update_post_meta($ID, 'Thumbnail_Small', $post_metas['Thumbnail_Small'][0]);
						*/
						
						
						self::handle_attachment($ID, $post_metas);
						
						
						if(strlen($post_metas['AMAZON_ASIN'][0]) > 2){
							update_post_meta($ID, '_visibility', 'visible');			
						}
									
					endif;					
				
				endif;		
			}
		endif;
			
	}
	
	
	
	//handle attachments
	static function handle_attachment($post_id, $post_metas){
		$upload_dir = wp_upload_dir();
		$image_dir = $upload_dir['basedir'] . '/robotcommerce' ;
		if(!file_exists($image_dir)){
			@ mkdir($image_dir);
		}
		
		$unique_name = $post_metas['AMAZON_ASIN'][0];
		
				
		$outPath = $image_dir . '/' . $unique_name . '.jpg';
		$outUrl = $upload_dir['baseurl'] . $unique_name . '.jpg';
		$inPath = $post_metas['Thumbnail_Large'][0];
		
		if(self::save_image($inPath, $outPath)){
			
			$wp_filetype = wp_check_filetype(basename($outUrl), null );
			
			$info = array(
				'guid' => $outUrl,
				'post_mime_type' => $wp_filetype['type'],
				'post_title' => preg_replace('/\.[^.]+$/', '', basename($outUrl)),
				'post_content' => '',
				'post_status' => 'inherit'
			);
			
			$attach_id = wp_insert_attachment( $info, $outPath, $post_id );
			
			if(!function_exists('wp_generate_attachment_metadata')){
				include ABSPATH . 'wp-admin/includes/image.php';
			}
			
			$attach_data = wp_generate_attachment_metadata( $attach_id, $outPath );
			wp_update_attachment_metadata( $attach_id, $attach_data );
			
			//adding attachment data
			update_post_meta($post_id, '_thumbnail_id', $attach_id);
		}
		
		
	}
	
	
	//save image
	static function save_image($inPath,$outPath){
		$in=    fopen($inPath, "rb");
	    $out=   fopen($outPath, "wb");
	    while ($chunk = fread($in,8192))
	    {
	        fwrite($out, $chunk, 8192);
	    }
	    fclose($in);
	    fclose($out);
	    
	    if(file_exists($outPath)){
	    	return true;
	    }
	    return false;
	}
	
	
	/*
	 * return 100posts
	 * */
	static function get_50_posts(){
		global $wpdb;
		
			
		/*
		$sql = "select ID, post_title from $wpdb->posts where ID in (
						select c.post_id from(
							SELECT count(*) as num, post_id  FROM `$wpdb->postmeta` WHERE `meta_key` LIKE 'AMAZON_ASIN' or meta_key like 'woocommerce_id'  group by post_id 
						) c where  c.num=1
		)" ;	
		*/
		
		$sql = "select ID, post_title, post_date from $wpdb->posts where ID in (
						select c.post_id from(
							SELECT count(*) as num, post_id  FROM `$wpdb->postmeta` WHERE `meta_key` LIKE 'AMAZON_ASIN' or meta_key like 'woocommerce_id'  group by post_id 
						) c where  c.num=1
		) LIMIT 50" ;	
				
		
		$posts = $wpdb->get_results($sql);		
		return $posts;
	}
	
	
	/*
	 * handle eveything about taxonomy
	 * */
	static function handle_categories(){
		return self::check_categories();
	}
	
	/*
	 * function to get the parent categories
	 * */
	function get_top_level_categories($parent = 0){
		global $wpdb;		
		$sql = "SELECT $wpdb->terms.term_id FROM $wpdb->terms
				INNER JOIN $wpdb->term_taxonomy
				ON $wpdb->terms.term_id = $wpdb->term_taxonomy.term_id
				WHERE $wpdb->term_taxonomy.taxonomy = 'category'
				AND $wpdb->term_taxonomy.parent = $parent
				/* AND $wpdb->term_taxonomy.count > 0 */
				ORDER BY $wpdb->terms.name ASC
		";
			//
		return $wpdb->get_col($sql);
	}
	
	
	
	
	 
	static function check_categories(){
			
		self::hierarchical_category_tree( 0, 0 );
			
	}
	
	
	static $array = array();
	static $index = '';
	
	static function hierarchical_category_tree($cat, $parent){
		$next = get_categories('hide_empty=0&orderby=name&order=ASC&parent=' . $cat);				
		  global $wpdb;
		
		  
			
		  if( $next ) : 		
			foreach( $next as $catt ) :
				
				//$product_cat = self::get_term($cat->name, $parent);
				$term_exists = term_exists($catt->name, 'product_cat', $parent);
				
				$array[] = $parent;
				$array[] = $catt->name;
				
				//var_dump($array);
				
								
				if($term_exists){
					$newparent = $term_exists['term_id'];
				}
				else{
																
					$new_inserted = self::insert_term($catt->name, $parent);
					if($new_inserted){
						$newparent = $new_inserted;
					}
					
				}			
				
				self::hierarchical_category_tree( $catt->term_id, $newparent );
				
								
			endforeach;    	
		
		  endif;
		
	}
	
	
	static function get_term($name, $parent){
		global $wpdb;
		$taxonomy = 'product_cat';		
		$term = $wpdb->get_row( $wpdb->prepare( "SELECT t.*, tt.* FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy = %s AND name = %s AND tt.parent = $parent LIMIT 1", $taxonomy, $name) );
		if($term) return $term;
		return false;
	}
	
	static function insert_term($name, $parent){
		global $wpdb;
		
		//var_dump($wpdb->terms);
		$slug = self::get_unique_slug($name);
		
		
		$wpdb->insert($wpdb->terms, array('name'=>$name, 'slug'=>$slug), array('%s', '%s'));
		$term_id = $wpdb->insert_id;
		//var_dump($term_id);
		if($term_id){
			$wpdb->insert($wpdb->term_taxonomy, array('term_id'=>$term_id, 'taxonomy'=>'product_cat', 'parent'=>$parent), array('%d', '%s', '%d'));
		}
		
		return $term_id;
		
	}
	
	
	static function get_unique_slug($name){
		$slug = sanitize_title($name);
		return self::unique_slug($slug);
	}
	
	static function unique_slug($slug){
		global $wpdb;
			
		$sql = "SELECT term_id FROM $wpdb->terms WHERE slug = '$slug'";
		
		if($wpdb->get_var($sql)){
			$slug .= rand(1000, 99999);
			return self::get_unique_slug($slug);
		}
		
		return $slug;
	}
		
			
}
