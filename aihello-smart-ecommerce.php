<?php
/**
 * Plugin Name: AiHello Smart Ecommerce
 * Description: Multi-channel, Multi-country inventory management solution by aihello.com
 * Version: 2.0
 * Author: AiHello Smart Fulfillment
 * Author URI: https://www.aihello.com/
 * Developer: AiHello Team
 * Developer URI: https://www.aihello.com/
 * Text Domain: aihello-smart-ecommerce
 * License: GPL2
 *
*/

defined( 'ABSPATH' ) || exit;
define('AIHELLO_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define('AIHELLO_PLUGIN_ASSETS', plugin_dir_url( __FILE__ ).'assets/' );
register_activation_hook( __FILE__, array( 'AiHello_SmartFulfillment', 'aihello_plugin_activate' ) );
register_uninstall_hook( __FILE__, array( 'AiHello_SmartFulfillment', 'aihello_plugin_uninstall' ) );
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	
    $aihello_enable 			= 	get_option('wc_settings_aihello_enable');
	$wc_settings_aihello_api 	= get_option('wc_settings_aihello_api');
	
	if($wc_settings_aihello_api=="yes") //check api is enabled
	require(AIHELLO_PLUGIN_PATH .'includes/inventoriesapi.php');
	
	if($aihello_enable=="yes"): //check geo location enabled
	require(AIHELLO_PLUGIN_PATH .'includes/currency_symbols.php');
	require(AIHELLO_PLUGIN_PATH .'includes/functions.php');	
	endif;
	
	class AiHello_SmartFulfillment {
		public function init() {
			add_action( 'admin_menu', array($this, 'AiHello_menu' ));	
			add_filter( 'woocommerce_settings_tabs_array', array($this,'add_aihello_tab'), 50 );
			add_action( 'woocommerce_settings_tabs_settings_tab_aihello', array($this, 'aihello_tab') );
			add_action( 'woocommerce_update_options_settings_tab_aihello', array($this, 'update_aihello_settings' ));	
		}
		
		//plugin uninstall
		public function aihello_plugin_uninstall(){
			global $wpdb;
			$table_name = $wpdb->prefix . 'aihello_inventory_countries';
			$wpdb->query("DROP TABLE IF EXISTS $table_name");
			unset ($_SESSION["aihello_usercountry"]);
			unset ($_SESSION["aihello_currency_rate"]);
			unset ($_SESSION["aihello_currency"]);
			delete_option('wc_settings_aihello_enable');
			delete_option('wc_settings_aihello_geolocationsapi');
			delete_option('wc_settings_aihello_currency_switcher');
			delete_option('wc_settings_aihello_currency_switcher_pos');
			delete_option('wc_settings_aihello_specific_currencies');
			delete_option('wc_settings_aihello_inventory_country');
		}
		
		//call activation		
		public function aihello_plugin_activate(){
			global $wpdb;
			//(sku, country, lat, lon, quantity, label)
			$table_name = $wpdb->prefix . 'aihello_inventory_countries';
			
			#Check to see if the table exists already, if not, then create it
			$settings_aihello_api = sanitize_text_field('yes');
			update_option('wc_settings_aihello_api',$settings_aihello_api);
            
			
			if($wpdb->get_var( "show tables like '$table_name'" ) != $table_name) 
			{				
				$sql = "CREATE TABLE `". $table_name . "` ( 
				`ID` INT(11) NOT NULL AUTO_INCREMENT ,
				`sku` VARCHAR(128) NOT NULL ,  
				`label` VARCHAR(255) NOT NULL default '',
				`country` VARCHAR(255) NOT NULL default '', 
				`quantity` INT(11) NOT NULL default '0',
				`latitude` VARCHAR(255) NOT NULL , 
				`longitude` VARCHAR(255) NOT NULL ,  
				PRIMARY KEY  (`ID`))";
				
				require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
				dbDelta($sql);
			}
		}
		
		 /* Add a new settings tab to the WooCommerce settings tabs array. */
		public function add_aihello_tab( $settings_tabs ) {
			$settings_tabs['settings_tab_aihello'] = __( 'AiHello Settings', 'aihello-smart-ecommerce' );
			return $settings_tabs;
		}
		
		/* Uses the WooCommerce admin fields API to output settings via the @see woocommerce_admin_fields() function.
     	*
     	* @uses woocommerce_admin_fields()
     	* @uses self::get_settings()
     	*/
		public function aihello_tab() {
			woocommerce_admin_fields( self::get_aihello_settings() );
		}
		
		/**
		 * Uses the WooCommerce options API to save settings via the @see woocommerce_update_options() function.
		 *
		 * @uses woocommerce_update_options()
		 * @uses self::get_aihello_settings()
		*/
		public function update_aihello_settings() {
			woocommerce_update_options( self::get_aihello_settings() );
		}
		
		function AiHello_menu(){
			global $submenu;
			$page_title = 'AiHello Smart Ecommerce';   
			$menu_title = 'AiHello';   
			$capability = 'manage_options';   
			$menu_slug  = 'aihello';   
			$function   = 'aihello_iframe';   
			$icon_url   = AIHELLO_PLUGIN_ASSETS .'aihelloicon.png';  
			$position   = 4;    
			add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
		}

	

		
		public function get_aihello_settings() {
			$settings = array(
				'section_title' => array(
					'name'     => esc_html( 'AiHello Currency', 'aihello-smart-ecommerce' ),
					'type'     => 'title',
					'desc'     => esc_html('AiHello Smart Fulfillment settings. You can use shortcode to display currency switcher on desired position.','aihello-smart-ecommerce').'<br>
					<strong>[aihello-choose-shipping-country]</strong>',
					'id'       => 'wc_settings_aihello_section_title'
				),
				array(
					'name' => esc_html( 'Enable', 'aihello-smart-ecommerce' ),
					'type' => 'checkbox',
					'desc'     => esc_html('Enable currency switcher','aihello-smart-ecommerce'),
					'id'   => 'wc_settings_aihello_enable'
				),
				array(
					'name'    => esc_html( 'Currency Switcher', 'aihello-smart-ecommerce' ),
					'desc'    => esc_html( 'This controls the visibility of the currency switcher on top of the header.', 'aihello-smart-ecommerce' ),
					'id'      => 'wc_settings_aihello_currency_switcher',
					'std'     => 'left', // WooCommerce < 2.0
					'default' => 'left', // WooCommerce >= 2.0
					'type'    => 'select',
					'options' => array(
					  'show'        => esc_html( 'Show', 'aihello-smart-ecommerce' ),
					  'hide'       => esc_html( 'Hide', 'aihello-smart-ecommerce' ),
					),
					'desc_tip' =>  true,
				),				
				array(
					'name'    => esc_html( 'Currency Switcher Position', 'aihello-smart-ecommerce' ),
					'desc'    => esc_html( 'This controls the position of the currency switcher.', 'aihello-smart-ecommerce' ),
					'id'      => 'wc_settings_aihello_currency_switcher_pos',
					'type'    => 'select',
					'options' => array(
					  'float-left'        => esc_html( 'Left', 'aihello-smart-ecommerce' ),
					  'float-right'       => esc_html( 'Right', 'aihello-smart-ecommerce' )
					),
					'desc_tip' =>  true,
				),	
				array(
					'name'    => esc_html( 'Select specific currencies', 'aihello-smart-ecommerce'),
					'desc'    => esc_html( 'This option let you limit which currencies you are willing to show.', 'aihello-smart-ecommerce' ),
					'id'      => 'wc_settings_aihello_specific_currencies',
					'placeholder' => 'Select currencies',
					'type'    => 'multi_select_countries',
					'multiple' => 'multiple',
					'options' => aihello_currencies(),
					'desc_tip' =>  true,
				),	
				array(
					'name' => esc_html( 'Enable', 'aihello-smart-ecommerce' ),
					'type' => 'checkbox',
					'desc'     => esc_html('Inventory by Country (Connect aihello.com first)','aihello-smart-ecommerce'),
					'id'   => 'wc_settings_aihello_inventory_country'
				),
				array(
					'name' => esc_html( 'API', 'aihello-smart-ecommerce'),
					'type' => 'checkbox',
					'desc'     => esc_html('Enable Inventory API','aihello-smart-ecommerce'),
					'id'   => 'wc_settings_aihello_api'
				),			
				'section_end' => array(
					 'type' => 'sectionend',
					 'id' => 'wc_settings_aihello_section_end'
				)
			);
			return apply_filters( 'wc_settings_aihello_settings', $settings );
		}
	}
	
	function aihello_iframe() { 
	
		echo "<div class='wrap'><iframe width='100%' height='900px' src=".AIHELLO_PLUGIN_ASSETS."html/home/index.html></iframe></div>";  
	}
	
	function aihello_currencies(){
		$currencylist = array(
			'AF' => 'AFN',
			'AL' => 'ALL',
			'DZ' => 'DZD',
			'AS' => 'USD',
			'AD' => 'EUR',
			'AO' => 'AOA',
			'AI' => 'XCD',
			'AQ' => 'XCD',
			'AG' => 'XCD',
			'AR' => 'ARS',
			'AM' => 'AMD',
			'AW' => 'AWG',
			'AU' => 'AUD',
			'AT' => 'EUR',
			'AZ' => 'AZN',
			'BS' => 'BSD',
			'BH' => 'BHD',
			'BD' => 'BDT',
			'BB' => 'BBD',
			'BY' => 'BYR',
			'BE' => 'EUR',
			'BZ' => 'BZD',
			'BJ' => 'XOF',
			'BM' => 'BMD',
			'BT' => 'BTN',
			'BO' => 'BOB',
			'BA' => 'BAM',
			'BW' => 'BWP',
			'BV' => 'NOK',
			'BR' => 'BRL',
			'IO' => 'USD',
			'BN' => 'BND',
			'BG' => 'BGN',
			'BF' => 'XOF',
			'BI' => 'BIF',
			'KH' => 'KHR',
			'CM' => 'XAF',
			'CA' => 'CAD',
			'CV' => 'CVE',
			'KY' => 'KYD',
			'CF' => 'XAF',
			'TD' => 'XAF',
			'CL' => 'CLP',
			'CN' => 'CNY',
			'HK' => 'HKD',
			'CX' => 'AUD',
			'CC' => 'AUD',
			'CO' => 'COP',
			'KM' => 'KMF',
			'CG' => 'XAF',
			'CD' => 'CDF',
			'CK' => 'NZD',
			'CR' => 'CRC',
			'HR' => 'HRK',
			'CU' => 'CUP',
			'CY' => 'EUR',
			'CZ' => 'CZK',
			'DK' => 'DKK',
			'DJ' => 'DJF',
			'DM' => 'XCD',
			'DO' => 'DOP',
			'EC' => 'ECS',
			'EG' => 'EGP',
			'SV' => 'SVC',
			'GQ' => 'XAF',
			'ER' => 'ERN',
			'EE' => 'EUR',
			'ET' => 'ETB',
			'FK' => 'FKP',
			'FO' => 'DKK',
			'FJ' => 'FJD',
			'FI' => 'EUR',
			'FR' => 'EUR',
			'GF' => 'EUR',
			'TF' => 'EUR',
			'GA' => 'XAF',
			'GM' => 'GMD',
			'GE' => 'GEL',
			'DE' => 'EUR',
			'GH' => 'GHS',
			'GI' => 'GIP',
			'GR' => 'EUR',
			'GL' => 'DKK',
			'GD' => 'XCD',
			'GP' => 'EUR',
			'GU' => 'USD',
			'GT' => 'QTQ',
			'GG' => 'GGP',
			'GN' => 'GNF',
			'GW' => 'GWP',
			'GY' => 'GYD',
			'HT' => 'HTG',
			'HM' => 'AUD',
			'HN' => 'HNL',
			'HU' => 'HUF',
			'IS' => 'ISK',
			'IN' => 'INR',
			'ID' => 'IDR',
			'IR' => 'IRR',
			'IQ' => 'IQD',
			'IE' => 'EUR',
			'IM' => 'GBP',
			'IL' => 'ILS',
			'IT' => 'EUR',
			'JM' => 'JMD',
			'JP' => 'JPY',
			'JE' => 'GBP',
			'JO' => 'JOD',
			'KZ' => 'KZT',
			'KE' => 'KES',
			'KI' => 'AUD',
			'KP' => 'KPW',
			'KR' => 'KRW',
			'KW' => 'KWD',
			'KG' => 'KGS',
			'LA' => 'LAK',
			'LV' => 'EUR',
			'LB' => 'LBP',
			'LS' => 'LSL',
			'LR' => 'LRD',
			'LY' => 'LYD',
			'LI' => 'CHF',
			'LT' => 'EUR',
			'LU' => 'EUR',
			'MK' => 'MKD',
			'MG' => 'MGF',
			'MW' => 'MWK',
			'MY' => 'MYR',
			'MV' => 'MVR',
			'ML' => 'XOF',
			'MT' => 'EUR',
			'MH' => 'USD',
			'MQ' => 'EUR',
			'MR' => 'MRO',
			'MU' => 'MUR',
			'YT' => 'EUR',
			'MX' => 'MXN',
			'FM' => 'USD',
			'MD' => 'MDL',
			'MC' => 'EUR',
			'MN' => 'MNT',
			'ME' => 'EUR',
			'MS' => 'XCD',
			'MA' => 'MAD',
			'MZ' => 'MZN',
			'MM' => 'MMK',
			'NA' => 'NAD',
			'NR' => 'AUD',
			'NP' => 'NPR',
			'NL' => 'EUR',
			'AN' => 'ANG',
			'NC' => 'XPF',
			'NZ' => 'NZD',
			'NI' => 'NIO',
			'NE' => 'XOF',
			'NG' => 'NGN',
			'NU' => 'NZD',
			'NF' => 'AUD',
			'MP' => 'USD',
			'NO' => 'NOK',
			'OM' => 'OMR',
			'PK' => 'PKR',
			'PW' => 'USD',
			'PA' => 'PAB',
			'PG' => 'PGK',
			'PY' => 'PYG',
			'PE' => 'PEN',
			'PH' => 'PHP',
			'PN' => 'NZD',
			'PL' => 'PLN',
			'PT' => 'EUR',
			'PR' => 'USD',
			'QA' => 'QAR',
			'RE' => 'EUR',
			'RO' => 'RON',
			'RU' => 'RUB',
			'RW' => 'RWF',
			'SH' => 'SHP',
			'KN' => 'XCD',
			'LC' => 'XCD',
			'PM' => 'EUR',
			'VC' => 'XCD',
			'WS' => 'WST',
			'SM' => 'EUR',
			'ST' => 'STD',
			'SA' => 'SAR',
			'SN' => 'XOF',
			'RS' => 'RSD',
			'SC' => 'SCR',
			'SL' => 'SLL',
			'SG' => 'SGD',
			'SK' => 'EUR',
			'SI' => 'EUR',
			'SB' => 'SBD',
			'SO' => 'SOS',
			'ZA' => 'ZAR',
			'GS' => 'GBP',
			'SS' => 'SSP',
			'ES' => 'EUR',
			'LK' => 'LKR',
			'SD' => 'SDG',
			'SR' => 'SRD',
			'SJ' => 'NOK',
			'SZ' => 'SZL',
			'SE' => 'SEK',
			'CH' => 'CHF',
			'SY' => 'SYP',
			'TW' => 'TWD',
			'TJ' => 'TJS',
			'TZ' => 'TZS',
			'TH' => 'THB',
			'TG' => 'XOF',
			'TK' => 'NZD',
			'TO' => 'TOP',
			'TT' => 'TTD',
			'TN' => 'TND',
			'TR' => 'TRY',
			'TM' => 'TMT',
			'TC' => 'USD',
			'TV' => 'AUD',
			'UG' => 'UGX',
			'UA' => 'UAH',
			'AE' => 'AED',
			'GB' => 'GBP',
			'US' => 'USD',
			'UM' => 'USD',
			'UY' => 'UYU',
			'UZ' => 'UZS',
			'VU' => 'VUV',
			'VE' => 'VEF',
			'VN' => 'VND',
			'VI' => 'USD',
			'WF' => 'XPF',
			'EH' => 'MAD',
			'YE' => 'YER',
			'ZM' => 'ZMW',
			'ZW' => 'ZWD',
		);
		foreach($currencylist as $key=>$value){
			$currencylistNew[$value] = $value;
		}
		return $currencylistNew;
	}
	
	//initilise AiHello_SmartFulfillment object
	$AiHello_SmartFulfillment = new AiHello_SmartFulfillment();
	$AiHello_SmartFulfillment->init();
} else {
	function AiHello_SmartFulfillment_admin_notice(){
		echo '<div class="notice notice-warning is-dismissible"><p>'.esc_html('Install woocommerce to Use AiHello Smart Fulfillment','aihello-smart-ecommerce').'</p></div>';
	}
	add_action('admin_notices', 'AiHello_SmartFulfillment_admin_notice');
}
