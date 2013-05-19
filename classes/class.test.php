<?php 
	
	class Test_Amazon_Response{
		
		static function init(){
			//add_action('init', array(get_class(), 'response'));
		}
		
		static function response(){
			$asin = 'B004GF8TIK';
			$pas = new AmazonPAS();
			$pas->set_locale(PAS_LOCALE_US);
			$opt = array(
						'IdType' => 'ASIN',
						'ResponseGroup' => 'Large',
						'IncludeReviewsSummary' => "False"
					);
			
			$offer = $pas->item_lookup($asin, $opt);
			
			$gallery_images = array();
			if($offer->isOk()){
				$item = $offer->body->Items->Item;
				
				//var_dump($item);
				
				foreach($item->ImageSets->ImageSet as $imgset){
					if((string)$imgset->attributes()->Category == 'primary') continue;
										
					$gallery_images[] = (string)$imgset->LargeImage->URL;
				}
				
				
				$a = implode('<br>', $gallery_images);
				var_dump($a);
				
				$b = explode("<br>", $a);
				var_dump($b);
				
				
				var_dump($gallery_images);
				
				die();
			}
			
		}
		
		
	}

?>