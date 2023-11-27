<?php
/**
 * OrderTrackingApi
 *
 * @package OrderTrackingApi
 * @since 1.0
 */

defined( 'ABSPATH' ) || exit;
/**
 * Main OrderTrackingApi Class.
 *
 *
 * @class OrderTrackingApi
 */
class OrderTrackingApi {

   /**
   * OrderTrackingApi version.
   *
   * @var string
   */
  public $version = '1.0';


  /**
   * The single instance of the class.
   *
   * @var OrderTrackingApi
   * @since 1.0
   */
  protected static $_instance = null;


  /**
   * Main OrderTrackingApi Instance.
   *
   * Ensures only one instance of OrderTrackingApi is loaded or can be loaded.
   *
   * @since 1.0
   * @static
   * @return OrderTrackingApi - Main instance.
   */
  public static function instance() {
    if ( is_null( self::$_instance ) ) {
      self::$_instance = new self();
    }
    return self::$_instance;
  }
  
  /**
   * Order_Items_Limit Constructor.
   */
  public function __construct() {
    $this->define_constants();
    $this->includes();
    $this->init_hooks();
    // $this->define_admin_hooks();
  }
  
  /**
   * Define constant if not already set.
   *
   * @param string      $name  Constant name.
   * @param string|bool $value Constant value.
   */
  private function define( $name, $value ) {
    if ( ! defined( $name ) ) {
      define( $name, $value );
    }
  }
   /**
   * Define Constants
   */
  private function define_constants() {
    $this->define( 'WWRO_OTA_VERSION', $this->version );
    $this->define( 'WWRO_OTA_PLUGIN_DIR', plugin_dir_path( WWRO_ORDER_API_FILE ) );
    $this->define( 'WWRO_OTA_PLUGIN_URL', plugin_dir_url( WWRO_ORDER_API_FILE  ) );
    $this->define( 'WWRO_OTA_BASE', plugin_basename( WWRO_ORDER_API_FILE ) );
  }
  
  /**
   * Hook into actions and filters.
   *
   * @since 1.0
   */
  private function init_hooks() {
    add_action( 'admin_notices', array( $this, 'ota_required_plugins' ) );

    add_filter( 'plugin_action_links_'.WWRO_OTA_BASE, array( $this, 'ota_settings_link' ) );

    add_action( 'plugins_loaded', array( $this, 'ota_load_textdomain' ) );

    add_filter( 'wowrestro_get_settings_pages', array( $this, 'wowrestro_get_order_tracking_api_settings_page' ), 10, 1 );

    add_filter( 'wowresto_addon_list', array( $this, 'wwro_ota_set_license_data' ) );

    add_action('admin_enqueue_scripts', array($this, 'wowrestro_order_tracking_api_admin_script'));

  }
    /**
   * Add details when plugin is activated
   */
  public function wwro_ota_set_license_data( $data ) {

    $item['wwro-ota'] = array( 'author' => 'magnigenie', 'path' => WWRO_OTA_PLUGIN_DIR, 'version' => WWRO_OTA_VERSION, 'name' => ' WowRestro - Order Api' );

    array_push( $data, $item );

    return $data;
  }
  /**
   * Load text domain
   *
   * @since 1.0
   */
  public function ota_load_textdomain() {
    load_plugin_textdomain( 'wwro-order-api', false, dirname( plugin_basename( WWRO_ORDER_API_FILE )  ) . '/languages/' );
}
  
  /**
 * Include required files for settings
 *
 * @since 1.0
 */
private function includes() {
  $endpoints_file = WWRO_OTA_PLUGIN_DIR . 'includes/class-wwro-order-api-endpoints.php';

  if ( file_exists( $endpoints_file ) ) {
      require_once $endpoints_file;
      new Wowrestro_Order_Api_Endpoints();
  } else {
      // Handle the case where the file is not found
      // You can log an error, display a message, or take other appropriate actions.
      error_log( 'File not found: ' . $endpoints_file );
  }
}

  /**
   * Check plugin dependency
   *
   * @since 1.0
   */
  public function ota_required_plugins() {

    if (  ! class_exists( 'WowRestro', false ) ) {
      $plugin_link = 'https://wordpress.org/plugins/wowrestro/';

      echo '<div id="notice" class="error"><p>' . sprintf( __( 'Order Tracking Api requires <a href="%1$s" target="_blank"> WowRestro </a> plugin to be installed. Please install and activate it', 'wwro-oil' ), esc_url( $plugin_link ) ).  '</p></div>';

      deactivate_plugins( 'wowrestro-order-tracking-api2/wowrestro-order-api.php' );
    }
  }

  
  public function wowrestro_order_tracking_api_admin_script() {
    // Check if the 'tab' parameter is set to 'order_api'
    if (isset($_GET['tab']) && $_GET['tab'] === 'order_api') {
      // Enqueue the style only when the condition is met
      wp_enqueue_style('wwro-order-api', WWRO_OTA_PLUGIN_URL . 'assets/css/order-api.css', array(), WWRO_OTA_VERSION);
    }
  }
  

  
  /**
   * Add settings link for the plugin
   *
   * @since 1.0
   */
  public function ota_settings_link( $links ) {
    $link = admin_url( 'admin.php?page=wowrestro-settings&tab=order_time_interval_limits' );
    $settings_link = sprintf( __( '<a href="%1$s">Settings</a>', 'wwro-order-api' ), esc_url( $link ) );
    array_unshift( $links, $settings_link );
    return $links;
  }
  /**
   * Include setting page
   * 
   * @since 1.0
   */
  public function wowrestro_get_order_tracking_api_settings_page( $settings ) {

    $settings[] = include 'admin/class-wwro-order-tracking-api-settings.php';
    // $settings[] =  WWRO_OTA_PLUGIN_DIR . 'admin/class-wwro-order-tracking-api-settings.php';

    return $settings;

  }



  
  }
