<?php

/**
 * Wowrestro_Order_Api_Endpoints class
 * Here is all enpoints with their callback functions
 */

use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;

class Wowrestro_Order_Api_Endpoints
{

	// protected $woocommerce;
	protected $endpoint;
	protected $fb_api_key_default;


	public function __construct()
	{
		$this->fb_api_key_default 	= 'AAAADhLcQbs:APA91bG7FkcmAFNGhuIMGl6qhm0Vl7Jd-aFJN3q2qwKUS_r2N-HXEwmXlXEClKFeP5oP4J8lglWvl2xWAtP9xh4bpxsktuZFt6Lx_zy05e4eVPgzK_5IIujQt6Gi4dwInMcHJu8SnV8S';
        $this->endpoint = 'wowrestro/orderapi/v1';
		// if ($endpoint) {
		// 	$this->endpoint = $endpoint;
		// } else {
			
		// }

		// $this->woocommerce = new Client(
		// 	get_site_url(),
		// 	$consumer_key,
		// 	$consumer_secret,
		// 	[
		// 		'version' => 'wc/v3',
		// 		'verify_ssl' => false,
		// 		'query_string_auth' => true
		// 	]
		// );
		add_action('rest_api_init', array($this, 'register_wowrestro_order_routes'));
		// New order notification once the order is placed
		add_action('woocommerce_new_order', array($this, 'wr_api_new_order_notification'), 10, 2);
	}

	public function register_wowrestro_order_routes()
	{
		register_rest_route($this->endpoint, '/orders', array(
			'methods' => 'POST',
			'callback' => array($this, 'view_orders'),
			'permission_callback' => '__return_true',
		));
		register_rest_route($this->endpoint, '/validate_api', array(
			'methods' => 'POST',
			'callback' => array($this, 'wow_restro_api_validate'),
			'permission_callback' => '__return_true',
		));

		register_rest_route($this->endpoint, '/user-login', array(
			'methods' => 'POST',
			'callback' => array($this, 'wr_app_login'),
			'permission_callback' => '__return_true',
		));
		register_rest_route($this->endpoint, '/logout', array(
			'methods' => 'POST',
			'callback' => array($this, 'wr_app_logout_callback'),
			'permission_callback' => '__return_true',
		));
		register_rest_route($this->endpoint, '/update_order', array(
			'methods' => 'POST',
			'callback' => array($this, 'update_order'),
			'permission_callback' => '__return_true',
		));
		register_rest_route($this->endpoint, '/order_count', array(
			'methods' => 'POST',
			'callback' => array($this, 'order_count'),
			'permission_callback' => '__return_true',
		));

		register_rest_route($this->endpoint, '/order_status', array(
			'methods' => 'GET',
			'callback' => array($this, 'order_status'),
			'permission_callback' => '__return_true',
		));
	}


	/**
	 * New order notification generation once a order is placed
	 * It does not check whether the payment is successful or not
	 *
	 * @since 1.0
	 * @param int $payment_id Payment ID
	 * @param object $payment Complete Payment Object
	 */
	public function wr_api_new_order_notification($payment_id, $order)
	{

		$order_total = $order->get_total();
		$currency = $order->get_currency();

		$customer = $order->get_formatted_billing_full_name();

		$title = __('You have received a new order !!', 'wwro-order-api');
		$body = sprintf(__(' %s has placed a new order. Order total is %s %s ', 'wwro-order-api'), $customer, $currency, $order_total);

		$device_ides = get_option('wr_firebase_notification_device_ids');

		$ides_array = array();
		//if index not in serial due to unset 
		if (is_array($device_ides)) {
			foreach ($device_ides as $id) {
				array_push($ides_array, $id);
			}
		}

		$headers = array(
			'Content-Type: application/json',
			'Authorization: key=' . $this->fb_api_key_default
		);

		$fields = array(
			'registration_ids' => $ides_array,
			'data' => array(
				'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
				'order_id' => $payment_id,
				'title' => $title,
				'body' => $body
			),
			'notification' => array(
				'title' => $title,
				'body' => $body,
				'sound' => 'slow_spring_board.wav'
			),
			"content_available" =>  true,
			"priority" =>  "high",
			
		);

		$fields = json_encode($fields);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

		$result = curl_exec($ch);
		curl_close($ch);
	}



	/**
	 * Remove device ID from FCM once user loggedout from App
	 *
	 * @since 1.0
	 * @author RestroPress
	 */
	public function wr_app_logout_callback(WP_REST_Request $request)
	{
		$parameters = $request->get_body_params();

		// Return if no device ID received
		if (empty($parameters['device_id'])) {

			$response_array = array(
				'message' => __('Device ID is empty', 'wwro-order-api')
			);
			$response = new WP_REST_Response($response_array);
			$response->set_status(403);

			return $response;
		}
		if (empty($parameters['api_key'])) {

			$response_array = array(
				'message' => __('Please provide the API key to proceed.', 'wwro-order-api')
			);
			$response = new WP_REST_Response($response_array);
			$response->set_status(403);

			return $response;
		}
		$validate_api_key = $this->validate_api_key($parameters['api_key']);

		if ('success' !== $validate_api_key) {

			$response_array = array(
				'message' => $validate_api_key
			);
			$response = new WP_REST_Response($response_array);
			$response->set_status(403);

			return $response;
		}
		$device_id = $parameters['device_id'];
		if (get_option('wr_firebase_notification_device_ids')) {

			$stored_device_ids = get_option('wr_firebase_notification_device_ids');
			if (($key = array_search($device_id, $stored_device_ids)) !== false) {
				unset($stored_device_ids[$key]);
				update_option('wr_firebase_notification_device_ids', $stored_device_ids);
			}
		}


		$response_array = array(
			'message' => 'success',
		);
		$response = new WP_REST_Response($response_array);
		$response->set_status(200);

		return $response;
	}


	/**
	 * Validating API key received via a API call with the
	 * unique key stored in the website DB
	 *
	 * @since 1.0
	 * @access public
	 * @param str $api_key The key received from API Call
	 * @return bool
	 *
	 */
	public function validate_api_key($api_key)
	{
		// Validate the API Key
		if (get_option('wow_restro_api_key')) {
			$unique_key = get_option('wow_restro_api_key');
			if (sanitize_text_field($api_key) == $unique_key) {
				return 'success';
			} else {
				return __('You have given invalid API key. Kindly check again.', 'wwro-order-api');
			}
		} else {
			return __('You have given invalid API key. Kindly check again.', 'wwro-order-api');
		}
	}


	/**
	 * save user firebase token to a post meta
	 * IDs at one place.
	 *
	 * @since 1.0
	 */
	public function wr_firebase_generate_notification_group($device_id)
	{
		if (empty($device_id))
			return __('Please provide valid device ID', 'wwro-order-api');

		// Store device ID and Validate with existing IDs
		if (get_option('wr_firebase_notification_device_ids')) {

			$stored_device_ids = get_option('wr_firebase_notification_device_ids');
			if (in_array($device_id, $stored_device_ids)) {
				return __('Device ID already exist.', 'wwro-order-api');
			} else {
				array_push($stored_device_ids, $device_id);
				update_option('wr_firebase_notification_device_ids', $stored_device_ids);
				return 'success';
			}
		} else {
			update_option('wr_firebase_notification_device_ids', array($device_id));
			return 'success';
		}
	}

	/**
	 * Validating user login using WP Rest API. It validates the API Key
	 * as well as the username and password received by GET method
	 *
	 * @since 1.0
	 * @param arr $attr
	 * @return arr $response object with user ID and user meta
	 *
	 */
	public function wr_app_login(WP_REST_Request $request)
	{

		$parameters = $request->get_body_params();
		if (empty($parameters['api_key'])) {

			$response_array = array(
				'message' => __('Please provide the API key to proceed.', 'wwro-order-api')
			);
			$response = new WP_REST_Response($response_array);
			$response->set_status(403);

			return $response;
		}

		$validate_api_key = $this->validate_api_key($parameters['api_key']);

		if ('success' !== $validate_api_key) {

			$response_array = array(
				'message' => $validate_api_key
			);
			$response = new WP_REST_Response($response_array);
			$response->set_status(403);

			return $response;
		}
		$email = isset( $parameters['email'] ) ? $parameters['email'] : '';
		$deviceID = isset( $parameters['device_id'] ) ? $parameters['device_id'] : '';
		$password = isset( $parameters['password'] ) ? $parameters['password'] : '';



		if (empty($email) || empty($password)) {

			$response['message'] = __('Please provide a valid Email Id and Password.', 'wwro-order-api');
			return new WP_REST_Response($response, 403);
		} else {

			$username = isset($parameters['email']) ? $parameters['email'] : '';
			$password = isset($parameters['password']) ? $parameters['password'] : '';

			$creds = array();
			$creds['user_login'] 	= $username;
			$creds['user_password'] = $password;
			$creds['remember'] 		= true;

			$user = wp_signon($creds, true);

			if (is_wp_error($user)) {

				$response_array = array(
					'message' => wp_strip_all_tags($user->get_error_message())
				);
				$response = new WP_REST_Response($response_array);
				$response->set_status(401);

				return $response;
			} else {

				$firebase_message = '';

				// Firebase - Generate Notification Group
				if (isset($deviceID) && $deviceID != '') {
					$firebase_message = $this->wr_firebase_generate_notification_group($deviceID);
				}
				$user->firebase_status = $firebase_message;
				$user_meta
					= get_user_meta($user->ID);
				$user->first_name = $user_meta['first_name'][0];
				$user->last_name =
					$user_meta['last_name'][0];


				// Ger User Data (Non-Sensitive, Pass to front end.)
				$response_array = array(
					'message' => __("Registration was Successful", 'wwro-order-api'),
					'data' => $user,

				);
				return new WP_REST_Response($response_array, 200);
			}
		}
	}

	/**
	 * Validating API call using WP Rest API. It validates the API Key
	 * sent from the Android App and returns status.
	 *
	 * @since 1.0
	 * @param arr $attr
	 * @return arr $response object
	 *
	 */

	public function wow_restro_api_validate(WP_REST_Request $request)
	{

		// You can get the combined, merged set of parameters:
		$parameters = $request->get_body_params();
		if (empty($parameters['api_key'])) {

			$response_array = array(
				'message' => __('Please provide the API key to proceed.', 'wwro-order-api')
			);
			$response = new WP_REST_Response($response_array);
			$response->set_status(403);

			return $response;
		}
		$validate_api_key = $this->validate_api_key($parameters['api_key']);
		if (isset($parameters['device_id']) && $parameters['device_id'] != '') {
			$this->wr_firebase_generate_notification_group($parameters['device_id']);
		}
		if ('success' == $validate_api_key) {
			$response_array = array(
				'status'  => 'success',
				'message' => __('API key accepted', 'wwro-order-api'),
			);

			$response = new WP_REST_Response($response_array);
			$response->set_status(200);

			return $response;
		} else {
			$response_array = array(
				'message' => $validate_api_key
			);
			$response = new WP_REST_Response($response_array);
			$response->set_status(403);
			return $response;
		}
	}



	public function view_orders(WP_REST_Request $request)
	{

		$parameters = $request->get_body_params();

		if (isset($request['_embed'])) {
			return new WP_Error(500, 'there are some bugs this _embed parameter, don\'t use it');
		}

		if (empty($parameters['api_key'])) {

			$response_array = array(
				'message' => __('Please provide the API key to proceed.', 'wwro-order-api')
			);
			$response = new WP_REST_Response($response_array);
			$response->set_status(403);

			return $response;
		}

		$validate_api_key = $this->validate_api_key($parameters['api_key']);

		if ('success' !== $validate_api_key) {

			$response_array = array(
				'message' => $validate_api_key
			);
			$response = new WP_REST_Response($response_array);
			$response->set_status(403);

			return $response;
		}

		try {

			unset($parameters['api_key']);
			$fees_details =[];
			//Get all Orders
			$parameters['type'] = 'shop_order';
			$all_orders = wc_get_orders($parameters);

			$response = array();
			foreach ($all_orders as $order) {
                $items_detail=array();
				$service_time = get_post_meta($order->ID, '_wowrestro_service_time', true);
				$service_type = get_post_meta($order->ID, '_wowrestro_service_type', true);
				
				foreach ($order->get_items() as $item_id => $item) {
					$modifier_items = wc_get_order_item_meta($item_id, '_modifier_items', true);
					$special_note = wc_get_order_item_meta($item_id, '_special_note', true);

					$items_detail[] = array(
						'product_id' => $item->get_product_id(),
						'variation_id' => $item->get_variation_id(),
						'name' => $item->get_name(),
						'quantity' => $item->get_quantity(),
						'subtotal' => $item->get_subtotal(),
						'total' => $item->get_total(),
						'tax' => $item->get_subtotal_tax(),
						'taxstat' => $item->get_tax_status(),
						'total_tax' => $item->get_total_tax(),
						'item_id' => $item->get_id(),
						'special_note' => $special_note,
						'modifier_item' => $modifier_items,
					);
					
				}
				$order_tips_data = $order->get_items( 'fee' );

				$order_data = $order->get_data();
				
				unset( $order_data['line_items'] );

				$fees_details = array();
				foreach( $order->get_items('fee') as $item_id => $item_fee ){
					
               	   $fee_name = $item_fee->get_name();
               	   $order_tips = $item_fee->get_total();
	               
	  				$fees_details[] = array(
	  					'fee_name' => $fee_name,
	  					'fee_total' => $order_tips,
	  				);
	               
           		}
           		
				$order_data['fee_lines'] = $fees_details;

				unset($order_data['tax_lines']);

				$response[] = array('order_details' => array_merge( $order_data, array('subtotal' => $order->get_subtotal())), 'service_details' => ['service_time' => $service_time, 'service_type' => $service_type], 'items_details' => $items_detail,);
			}

			$response = new WP_REST_Response($response);
			$response->set_status(200);
			return $response;
		} catch (HttpClientException $e) {
			return new WP_Error($e->getCode(), $e->getMessage());
		}
	}



	public function update_order(WP_REST_Request $request)
	{

		$parameters = $request->get_body_params();

		if (empty($parameters['api_key'])) {

			$response_array = array(
				'message' => __('Please provide the API key to proceed.', 'wwro-order-api')
			);
			$response = new WP_REST_Response($response_array);
			$response->set_status(403);

			return $response;
		}

		$validate_api_key = $this->validate_api_key($parameters['api_key']);

		if ('success' !== $validate_api_key) {

			$response_array = array(
				'message' => $validate_api_key
			);
			$response = new WP_REST_Response($response_array);
			$response->set_status(403);

			return $response;
		}

		$id = $parameters['id'];
		$status = $parameters['order_status'];

		if (isset($request['_embed'])) {
			return new WP_Error(500, 'there are some bugs this _embed parameter, don\'t use it');
		}

		try {
			// Update Order by ID
			$order = wc_get_order($id);

			if (!empty($status) && !empty($id)) {
				$order->update_status($status);
				$order->save();
			}

			$response_array = array(
				'message' => 'Order status successfully updated.'
			);

			$response = new WP_REST_Response($response_array);
			$response->set_status(200);

			return $response;
		} catch (HttpClientException $e) {
			return new WP_Error($e->getCode(), $e->getMessage());
		}
	}

	public function order_status($request)
	{


		if (isset($request['_embed'])) {
			return new WP_Error(500, 'there are some bugs this _embed parameter, don\'t use it');
		}

		try {


			$data = array();
			$data = wc_get_order_statuses();
			$color_codes = array(
				'wc-pending'     	=> '#fcbdbd',
				'wc-pending_text' 	=> '#333333',
				'wc-failed'    	=> '#ffcd85',
				'wc-failed_text' 	=> '#92531b',
				'wc-processing'  	=> '#f7ae18',
				'wc-processing_text' => '#ffffff',
				'wc-on-hold' 			=> '#75A84C',
				'wc-on-hold_text' 		=> '#ffffff',
				'wc-refunded' 		=> '#cac300',
				'wc-refunded_text' 	=> '#464343',
				'wc-cancelled'   	=> '#eba3a3',
				'wc-cancelled_text' 	=> '#761919',
				'wc-completed' 		=> '#e0f0d7',
				'wc-completed_text'	=> '#3a773a',
			);
			$response_array = array(
				'statuses' => $data,
				'color_codes' => $color_codes,
			);

			$response = new WP_REST_Response($response_array);
			$response->set_status(200);

			return $response;
		} catch (HttpClientException $e) {
			return new WP_Error($e->getCode(), $e->getMessage());
		}
	}
	public function order_count(WP_REST_Request $request)
	{

		$parameters = $request->get_body_params();

		if (empty($parameters['api_key'])) {

			$response_array = array(
				'message' => __('Please provide the API key to proceed.', 'wwro-order-api')
			);
			$response = new WP_REST_Response($response_array);
			$response->set_status(403);

			return $response;
		}

		$validate_api_key = $this->validate_api_key($parameters['api_key']);

		if ('success' !== $validate_api_key) {

			$response_array = array(
				'message' => $validate_api_key
			);
			$response = new WP_REST_Response($response_array);
			$response->set_status(403);

			return $response;
		}

		try {
			$data = array();
			$statuses = array();
			$statuses = wc_get_order_statuses();
			foreach ($statuses
				as $key => $item) {
				$data[$item] = wc_orders_count(str_replace('wc-', '', $key));
			}
			$response_array = array(
				'message' => 'success',
				'data' => $data,
			);

			$response = new WP_REST_Response($response_array);
			$response->set_status(200);

			return $response;
		} catch (HttpClientException $e) {
			return new WP_Error($e->getCode(), $e->getMessage());
		}
	}
}
