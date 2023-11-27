<?php




/**
 * WOWRestro Tips Settings
 *
 * @package WOWRestro/Admin
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WWRO_Order_Apis_Settings', false ) ) {
    return new WWRO_Order_Apis_Settings();
}

/**
 * WWRO_Order_Tips_Settings.
 */
class WWRO_Order_Apis_Settings extends WWRO_Settings_Page {

    /**
     * Constructor.
     */
    public function __construct() {
        
        $this->id    = 'order_api';
        $this->label = __( ' WowRestro Order Api', 'wwro-order-api' );
        parent::__construct();
    }

    /**
     * Generating the unique API key on the 1st Load of Settings page
     *
     * Note: It remains the same for an installation. It cannot be changed
     * from the addon Settings page
     *
     * @since 1.0
     * @access private
     * @return str $unique_key
     */
    public function rp_ota_generate_api_key()
    {
        $unique_key = md5( microtime() . rand() );

        if ( ! get_option( 'wow_restro_api_key' ) ) {
            add_option( 'wow_restro_api_key', $unique_key );
        } else {
            $unique_key = get_option( 'wow_restro_api_key' );
        }

        return $unique_key;
    }

    public function render_settings_page_content()
    {
        ?>
            <!-- Create a header in the default WordPress 'wrap' container -->
            <div class="wrap">

                <h2><?php _e('WOWRestro Order Api Options', 'wowrestro-order-api'); ?></h2>
                <?php settings_errors(); ?>

                <form class="wowrestro-order-api" method="post" action="options.php">
                    <?php
                    settings_fields('wowrestro_order_api_main_options');

                    do_settings_sections('wowrestro_order_api_main_options');

                    submit_button();
                    ?>
                </form>
            </div>
        <?php
    }

    /**
     * Provide default values for the Options.
     *
     * @return array
     */
    public function get_settings( $current_section = '' ) {
      $wow_restro_api_key = $this->rp_ota_generate_api_key();

      $qr_image = '<img src="https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=' . get_bloginfo('url') . '=key=' . esc_attr($wow_restro_api_key) . '" title="Scan this QR Code from order tracking mobile app.">';

 
         $api_key = '<input type="text" id="wow_restro_api_key" name="wowrestro_order_api_main_options[wow_restro_api_key]" value="' . esc_attr( $wow_restro_api_key ) . '" readonly/>';
 
        $settings = apply_filters(
            'wowrestro_apis_settings',
            array(
                array(
                    'title'   => __( 'WOWRestro Order Api Options', 'wwro-order-api' ),
                    'type'    => 'title',
                    'desc'    => '',
                    'id'      => 'order_apiss_options',
                 ),

               
                array(
                    'title'    => __( 'Api Key', 'wwro-order-api' ),
                    'type'    => 'title',
                     'id'       => 'wow_restro_ot_api_key',
                     'desc' => $wow_restro_api_key ,
                 ),
                array(
                  'title'    => __( 'QR Code', 'wwro-order-api' ),
                  'type'    => 'title',
                   'id'       => 'wow_restro_ot_qr',
                   'desc' => $qr_image ,
               ),
                
            )
        );

        return apply_filters( 'wowrestro_order_apis_settings_' . $this->id, $settings, $current_section );
    }
    

    /**
     * Callback function to render the API key settings field.
     */
    public function api_key_callback()
    {
        $wow_restro_api_key = $this->rp_ota_generate_api_key();

        echo '<input type="text" id="wow_restro_api_key" name="wowrestro_order_api_main_options[wow_restro_api_key]" value="' . esc_attr( $wow_restro_api_key ) . '" readonly/>';
    }

    
}

return new WWRO_Order_Apis_Settings();
