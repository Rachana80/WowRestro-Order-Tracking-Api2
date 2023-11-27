<?php
/**
 * Plugin Name:      WowRestro - Order Api
 * Plugin URI: 
 * Description:       Allows to fetch WOWrestro orders details 
 * Version:           1.0
 * Author:            MagniGenie
 * Author URI:
 * Text Domain:       wwro-order-api
 * Domain Path:       /languages
 * 
 * @package           WOWRestro_Order_Api
 */

 defined( 'ABSPATH' ) || exit;

 if ( ! defined( 'WWRO_ORDER_API_FILE' ) ) {
    define( 'WWRO_ORDER_API_FILE', __FILE__ );
  }
  require __DIR__ . '/vendor/autoload.php';

// Include the main class class-wwro-oil
if ( ! class_exists( 'Order_Tracking_api', false ) ) {
    include_once dirname( __FILE__ ) . '/includes/class-wwro-ota.php';
  }

  /**
 * Returns the main instance of Order_Tracking_Api.
 *
 * @return Order_Tracking_Api
 */
function wowrestro_order_api() {
  return OrderTrackingApi::instance();
}

wowrestro_order_api();


?>