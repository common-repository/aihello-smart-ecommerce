<?php
class AiHello_Inventory_Stock_Countries extends WP_REST_Controller {
	private $api_namespace;
	private $base;
	private $api_version;
	private $api_name;
	private $db_table;
	
	public function __construct() {
		global $wpdb;
		$this->api_namespace = 'wc/v';
		$this->api_version = '3';
		$this->api_name='/aihello_inventory-stock-countries';

		$this->db_table = $wpdb->prefix.'aihello_inventory_countries';		
		$this->init();
	}
	
	public function aihello_register_routes() {
		$namespace = $this->api_namespace . $this->api_version . $this->api_name;		
		register_rest_route( $namespace, '/update', array(
				array( 
					'methods' 				=> WP_REST_SERVER::CREATABLE,
					'callback' 				=> array( $this, 'aihello_update_inventory_stock' ), 
					'permission_callback'	=> array( $this, 'aihello_create_inventory_permissions_check' ),
				),
			) 
		);
		register_rest_route( $namespace, '/list', array(
				array( 
					'methods' 			=> WP_REST_Server::READABLE, 
					'callback'			=> array( $this, 'aihello_list_inventory_stock' ),
					'permission_callback'	=> array( $this, 'aihello_create_inventory_permissions_check' ), 
				),
			)  
		);
		register_rest_route( $namespace, '/delete', array(
				array( 
					'methods'		=> WP_REST_Server::READABLE, 
					'callback' 		=> array( $this, 'aihello_delete_inventory_stock' ),
					'permission_callback'	=> array( $this, 'aihello_create_inventory_permissions_check' ), 
				),
			)  
		);
	}
	
	// Register our REST Server
	public function init(){
		add_action( 'rest_api_init', array( $this, 'aihello_register_routes' ) );
	}
		
	public function aihello_update_inventory_stock( WP_REST_Request $request_data ){
		global $wpdb;
		$data = array();
		$tableName        = $this->db_table;
		
		// Fetching values from API
		$parameters = $request_data->get_params();
		$aihello_sku 		= esc_sql($parameters['sku']);
		$aihello_country 	= esc_sql($parameters['country']);
		$inventory_ID 		= $wpdb->get_var("SELECT ID FROM {$tableName} WHERE country='".$aihello_country."' and sku = '".$aihello_sku."' LIMIT 1");
		$inventory_ID 		= stripslashes_deep($inventory_ID);
		$data = array(
			"label" 	=> $parameters['label'],
			"sku" 		=> $parameters['sku'],
			"country" 	=> $parameters['country'],
			"quantity" 	=> $parameters['quantity'],
			"latitude" 	=> $parameters['latitude'],
			"longitude" => $parameters['longitude']
		);
		if ( $inventory_ID > 0 ){
			$where=array('ID'=>$inventory_ID);
			$format=("%d");
			$whereFormat=("%d");
			$success = $wpdb->update( $tableName, $data, $where); 
			$wpdb->last_quer;
			$wpdb->last_error;
			$response = (false === $success ? 'Inventory failed..' : 'Updated Successfully');
		} else {	
			$success = $wpdb->insert($tableName, $data);
			$response = (false === $success ? 'Inventory failed..' : 'Added Successfully');		
		}	
		return $response;		
	}
	
	public function aihello_list_inventory_stock( WP_REST_Request $request ){
		global $wpdb;
		$tableName        = $this->db_table;
		$query = "SELECT * FROM {$tableName}";
		$list = $wpdb->get_results($query) or die(mysql_error());
		$response = new WP_REST_Response($list);
		return $response;
	}
	
	public function aihello_delete_inventory_stock( WP_REST_Request $request_data ){	
		global $wpdb;
		$tableName      = $this->db_table;;
		$parameters 	= $request_data->get_params();
		$aihello_sku 	= esc_sql($parameters['sku']);		
		$inventory_ID 	= $wpdb->get_var("SELECT ID FROM {$tableName} WHERE sku = '".$aihello_sku."' LIMIT 1");
		$inventory_ID 	= stripslashes_deep($inventory_ID);
		
		if ( $inventory_ID > 0 ){
			$where=array('ID'=>$inventory_ID);
			$success = $wpdb->delete( $tableName, $where); 
			$response = (false === $success ? 'Action failed..' : 'Deleted Successfully');
		} else {	
			$response = 'record does not exits';		
		}	
		return $response;
	}
	
	public function aihello_create_inventory_permissions_check( $request ) {
        if ( ! wc_rest_check_user_permissions( 'create' ) ) {
            return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Sorry, you are not allowed to create resources.', 'woocommerce' ), 
			array( 'status' => rest_authorization_required_code() ) );
        }
        return true;
    }	

}
$inventory_productstock = new AiHello_Inventory_Stock_Countries();