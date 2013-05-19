<?php

/*
 * plugin name: WpRObot with Woocommerce
 * author: Mahibul Hasan
 * description: Automatically runs a cron to convert amazon post into wocoommerce products. Dynamically fetched the prices, description, features from amazon. The shop will work exactly woocommerce enviornment. While payment options is to handled, it will take the visitors to the amazon with referrel id
 * plugin uri: http://www.bouncerseat.net/
 * author uri: http://sohag07hasan.elance.com
 * */

define("WPROBOTWOOCOMMERCE_DIR", dirname(__FILE__));
define("WPROBOTWOOCOMMERCE_FILE", __FILE__);

include WPROBOTWOOCOMMERCE_DIR . '/classes/class.woocommerce.wprobot.php';
wprobot_woocommerce::init();


include WPROBOTWOOCOMMERCE_DIR . '/api/cloudfusion.class.php';

include WPROBOTWOOCOMMERCE_DIR . '/classes/class.cron.php';
WpRobotWocommerceCron::init();



//price updating cron
include WPROBOTWOOCOMMERCE_DIR . '/classes/cron-price-adjusting.php';
WoocommerceAmazonPrice::init();



//testing the amazon api
include WPROBOTWOOCOMMERCE_DIR . '/classes/class.test.php';
Test_Amazon_Response::init()


?>
