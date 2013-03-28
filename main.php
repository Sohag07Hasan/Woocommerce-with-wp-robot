<?php

/*
 * plugin name: WpRObot with Woocommerce
 * author: Mahibul Hasan
 * */

define("WPROBOTWOOCOMMERCE_DIR", dirname(__FILE__));
define("WPROBOTWOOCOMMERCE_FILE", __FILE__);

include WPROBOTWOOCOMMERCE_DIR . '/classes/class.woocommerce.wprobot.php';
wprobot_woocommerce::init();


include WPROBOTWOOCOMMERCE_DIR . '/api/cloudfusion.class.php';

include WPROBOTWOOCOMMERCE_DIR . '/classes/class.cron.php';
WpRobotWocommerceCron::init();


?>
