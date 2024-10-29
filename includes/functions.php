<?php
add_action('wp_head','aihello_custom_header_section');
add_action('wp_enqueue_scripts', 'aihello_currency_scripts');
add_action( 'init', 'get_aihello_currency_rate' );

if(!is_admin()) add_filter( 'woocommerce_currency_symbol', 'aihello_change_currency_symbol', 10, 2 );
add_shortcode( 'aihello-choose-shipping-country', 'aihello_change_shipping_address');

// Simple, grouped and external products
add_filter('woocommerce_product_get_price', 'aihello_currency_price', 99, 2 );
add_filter('woocommerce_product_get_regular_price', 'aihello_currency_price', 99, 2 );

// Variations
add_filter('woocommerce_product_variation_get_regular_price', 'aihello_currency_price', 99, 2 );
add_filter('woocommerce_product_variation_get_price', 'aihello_currency_price', 99, 2 );

// Variable (price range)
add_filter('woocommerce_variation_prices_price', 'aihello_variable_price', 99, 3 );
add_filter('woocommerce_variation_prices_regular_price', 'aihello_variable_price', 99, 3 );

// Handling price caching (see explanations at the end)
add_filter( 'woocommerce_get_variation_prices_hash', 'aihello_add_price_multiplier_to_variation_prices_hash', 99, 1 );

if(!is_admin()) add_filter('woocommerce_product_is_in_stock', 'aihello_woocommerce_product_is_in_stock' );

function aihello_woocommerce_product_is_in_stock(){
	global $product, $wpdb;
	$check_inventory_country = get_option('wc_settings_aihello_inventory_country');
	if(isset($check_inventory_country) && $check_inventory_country=='yes'):
		$aihello_country_code = (isset($_SESSION['aihello_usercountry']) ? $_SESSION['aihello_usercountry'] : aihello_userCountry());
		$aihello_country_code 	= esc_sql($aihello_country_code);
		$aihello_product_sku 	= esc_sql($product->get_sku());
		$aihello_table = $wpdb->prefix . 'aihello_inventory_countries';
		$aihello_quantity = $wpdb->get_var("SELECT quantity FROM ".$aihello_table." WHERE country = '".$aihello_country_code."' AND sku='".$aihello_product_sku."'");
		return (isset($aihello_quantity) && ($aihello_quantity>0) ? true : false);
	else:
		return true;
	endif;
}
function aihello_currency_price( $price, $product ) {
	return $price * aihello_get_price_multiplier();
}
function aihello_variable_price( $price, $variation, $product ) {
    return $price * aihello_get_price_multiplier();
}
function aihello_add_price_multiplier_to_variation_prices_hash( $hash ) {
    $hash[] = aihello_get_price_multiplier();
    return $hash;
}
function aihello_custom_header_section(){
	echo '<style>#aihello_currencyfrm { float:left; }.aihellocurrency { padding: 2px 5px 2px 5px; margin: 0px 2px 0px 2px; border: 1px solid #ccc; }</style>';
	$aihello_selected_currencies = get_option('wc_settings_aihello_specific_currencies');
	if(count($aihello_selected_currencies)>0): 
		$aihello_currencies = $aihello_selected_currencies;
	else: 
		$aihello_currencies = aihello_currencies();
	endif;
	$aihello_currencydropdown = '<form id="aihello_currencyfrm" name="currencyfrm" method="post"><select name="aihello_currency" id="aihello_currency" class="aihellocurrency">';
	foreach($aihello_currencies as $key=>$value){
		if(isset($_SESSION['aihello_currency'])&& $_SESSION['aihello_currency']==$value){
			$aihello_currencydropdown .= '<option value="'.$value.'" selected="selected">'.$value.'</option>';
		} else {
			$aihello_currencydropdown .= '<option value="'.$value.'">'.$value.'</option>';
		}
	}
	$aihello_currencydropdown .= '</select></form>';
	
	$currency_switcher = get_option('wc_settings_aihello_currency_switcher');
	$currency_switcher_pos = get_option('wc_settings_aihello_currency_switcher_pos');
	if($currency_switcher=='show'){
		$choosecurrencybar = '<div class="row"><div class="col-md-12"><div class="'.$currency_switcher_pos.'" style="width:100%">';
		$choosecurrencybar .= '<div class="col-md-3 '.$currency_switcher_pos.'">'.$aihello_currencydropdown.do_shortcode('[aihello-choose-shipping-country]');
		$choosecurrencybar .= '</div></div></div>';
		echo $choosecurrencybar;
	}
}
function aihello_currency_scripts() {	
	
	wp_enqueue_style('aihello_bootstrap', AIHELLO_PLUGIN_ASSETS.'bootstrap.min.css');
	wp_enqueue_script( 'aihello_bootstrap', AIHELLO_PLUGIN_ASSETS.'bootstrap.min.js');
	wp_enqueue_script( 'aihello_customscripts', AIHELLO_PLUGIN_ASSETS.'customscripts.js');
}
function aihello_userCountry(){
	$aihelloGeo      		= new WC_Geolocation();
	$user_ip  		 		= $aihelloGeo->get_external_ip_address();
	$user_geodata 	 		= $aihelloGeo->geolocate_ip( $user_ip );
	$aihello_country_code 	= $user_geodata['country'];
	return $aihello_country_code;
}
function get_aihello_currency_rate(){
	$aihello_curency_rate 	= 0;
	$base_currency 			= get_option('woocommerce_currency');	// woocommerce base currency
	$aihello_country_code 	= aihello_userCountry();
	$to_currency 			= aihello_get_country_currency($aihello_country_code);	
	
	if(!isset($_SESSION['aihello_usercountry'])) {
		$aihello_curency_rate 				= aihello_convertCurrency($base_currency, $to_currency);
		$_SESSION['aihello_currency_rate'] 	= $aihello_curency_rate;
		$_SESSION['aihello_currency'] 		= $to_currency;
		$_SESSION['aihello_usercountry'] 	= $aihello_country_code;
	}
	if(isset($_POST['aihellochooselocationbtn']) ){
		$aihello_country_code 				= sanitize_text_field($_REQUEST['aihello_shippingcountry']);
		$to_currency 						= aihello_get_country_currency($aihello_country_code);
		$aihello_curency_rate 				= aihello_convertCurrency($base_currency, $to_currency);
		$_SESSION['aihello_currency_rate'] 	= $aihello_curency_rate;
		$_SESSION['aihello_currency'] 		= $to_currency;
		$_SESSION['aihello_usercountry'] 	= $aihello_country_code;
	}
	if(isset($_POST['aihello_currency'])){ 
		$to_currency 						= sanitize_text_field($_POST['aihello_currency']);
		$aihello_curency_rate 				= aihello_convertCurrency($base_currency, $to_currency);
		$_SESSION['aihello_currency_rate'] 	= $aihello_curency_rate;
		$_SESSION['aihello_currency'] 		= $to_currency;
	}
}
function aihello_countrieslist(){
	$countries = array
	(
		'AF' => 'Afghanistan',
		'AX' => 'Aland Islands',
		'AL' => 'Albania',
		'DZ' => 'Algeria',
		'AS' => 'American Samoa',
		'AD' => 'Andorra',
		'AO' => 'Angola',
		'AI' => 'Anguilla',
		'AQ' => 'Antarctica',
		'AG' => 'Antigua And Barbuda',
		'AR' => 'Argentina',
		'AM' => 'Armenia',
		'AW' => 'Aruba',
		'AU' => 'Australia',
		'AT' => 'Austria',
		'AZ' => 'Azerbaijan',
		'BS' => 'Bahamas',
		'BH' => 'Bahrain',
		'BD' => 'Bangladesh',
		'BB' => 'Barbados',
		'BY' => 'Belarus',
		'BE' => 'Belgium',
		'BZ' => 'Belize',
		'BJ' => 'Benin',
		'BM' => 'Bermuda',
		'BT' => 'Bhutan',
		'BO' => 'Bolivia',
		'BA' => 'Bosnia And Herzegovina',
		'BW' => 'Botswana',
		'BV' => 'Bouvet Island',
		'BR' => 'Brazil',
		'IO' => 'British Indian Ocean Territory',
		'BN' => 'Brunei Darussalam',
		'BG' => 'Bulgaria',
		'BF' => 'Burkina Faso',
		'BI' => 'Burundi',
		'KH' => 'Cambodia',
		'CM' => 'Cameroon',
		'CA' => 'Canada',
		'CV' => 'Cape Verde',
		'KY' => 'Cayman Islands',
		'CF' => 'Central African Republic',
		'TD' => 'Chad',
		'CL' => 'Chile',
		'CN' => 'China',
		'CX' => 'Christmas Island',
		'CC' => 'Cocos (Keeling) Islands',
		'CO' => 'Colombia',
		'KM' => 'Comoros',
		'CG' => 'Congo',
		'CD' => 'Congo, Democratic Republic',
		'CK' => 'Cook Islands',
		'CR' => 'Costa Rica',
		'CI' => 'Cote D\'Ivoire',
		'HR' => 'Croatia',
		'CU' => 'Cuba',
		'CY' => 'Cyprus',
		'CZ' => 'Czech Republic',
		'DK' => 'Denmark',
		'DJ' => 'Djibouti',
		'DM' => 'Dominica',
		'DO' => 'Dominican Republic',
		'EC' => 'Ecuador',
		'EG' => 'Egypt',
		'SV' => 'El Salvador',
		'GQ' => 'Equatorial Guinea',
		'ER' => 'Eritrea',
		'EE' => 'Estonia',
		'ET' => 'Ethiopia',
		'FK' => 'Falkland Islands (Malvinas)',
		'FO' => 'Faroe Islands',
		'FJ' => 'Fiji',
		'FI' => 'Finland',
		'FR' => 'France',
		'GF' => 'French Guiana',
		'PF' => 'French Polynesia',
		'TF' => 'French Southern Territories',
		'GA' => 'Gabon',
		'GM' => 'Gambia',
		'GE' => 'Georgia',
		'DE' => 'Germany',
		'GH' => 'Ghana',
		'GI' => 'Gibraltar',
		'GR' => 'Greece',
		'GL' => 'Greenland',
		'GD' => 'Grenada',
		'GP' => 'Guadeloupe',
		'GU' => 'Guam',
		'GT' => 'Guatemala',
		'GG' => 'Guernsey',
		'GN' => 'Guinea',
		'GW' => 'Guinea-Bissau',
		'GY' => 'Guyana',
		'HT' => 'Haiti',
		'HM' => 'Heard Island & Mcdonald Islands',
		'VA' => 'Holy See (Vatican City State)',
		'HN' => 'Honduras',
		'HK' => 'Hong Kong',
		'HU' => 'Hungary',
		'IS' => 'Iceland',
		'IN' => 'India',
		'ID' => 'Indonesia',
		'IR' => 'Iran, Islamic Republic Of',
		'IQ' => 'Iraq',
		'IE' => 'Ireland',
		'IM' => 'Isle Of Man',
		'IL' => 'Israel',
		'IT' => 'Italy',
		'JM' => 'Jamaica',
		'JP' => 'Japan',
		'JE' => 'Jersey',
		'JO' => 'Jordan',
		'KZ' => 'Kazakhstan',
		'KE' => 'Kenya',
		'KI' => 'Kiribati',
		'KR' => 'Korea',
		'KW' => 'Kuwait',
		'KG' => 'Kyrgyzstan',
		'LA' => 'Lao People\'s Democratic Republic',
		'LV' => 'Latvia',
		'LB' => 'Lebanon',
		'LS' => 'Lesotho',
		'LR' => 'Liberia',
		'LY' => 'Libyan Arab Jamahiriya',
		'LI' => 'Liechtenstein',
		'LT' => 'Lithuania',
		'LU' => 'Luxembourg',
		'MO' => 'Macao',
		'MK' => 'Macedonia',
		'MG' => 'Madagascar',
		'MW' => 'Malawi',
		'MY' => 'Malaysia',
		'MV' => 'Maldives',
		'ML' => 'Mali',
		'MT' => 'Malta',
		'MH' => 'Marshall Islands',
		'MQ' => 'Martinique',
		'MR' => 'Mauritania',
		'MU' => 'Mauritius',
		'YT' => 'Mayotte',
		'MX' => 'Mexico',
		'FM' => 'Micronesia, Federated States Of',
		'MD' => 'Moldova',
		'MC' => 'Monaco',
		'MN' => 'Mongolia',
		'ME' => 'Montenegro',
		'MS' => 'Montserrat',
		'MA' => 'Morocco',
		'MZ' => 'Mozambique',
		'MM' => 'Myanmar',
		'NA' => 'Namibia',
		'NR' => 'Nauru',
		'NP' => 'Nepal',
		'NL' => 'Netherlands',
		'AN' => 'Netherlands Antilles',
		'NC' => 'New Caledonia',
		'NZ' => 'New Zealand',
		'NI' => 'Nicaragua',
		'NE' => 'Niger',
		'NG' => 'Nigeria',
		'NU' => 'Niue',
		'NF' => 'Norfolk Island',
		'MP' => 'Northern Mariana Islands',
		'NO' => 'Norway',
		'OM' => 'Oman',
		'PK' => 'Pakistan',
		'PW' => 'Palau',
		'PS' => 'Palestinian Territory, Occupied',
		'PA' => 'Panama',
		'PG' => 'Papua New Guinea',
		'PY' => 'Paraguay',
		'PE' => 'Peru',
		'PH' => 'Philippines',
		'PN' => 'Pitcairn',
		'PL' => 'Poland',
		'PT' => 'Portugal',
		'PR' => 'Puerto Rico',
		'QA' => 'Qatar',
		'RE' => 'Reunion',
		'RO' => 'Romania',
		'RU' => 'Russian Federation',
		'RW' => 'Rwanda',
		'BL' => 'Saint Barthelemy',
		'SH' => 'Saint Helena',
		'KN' => 'Saint Kitts And Nevis',
		'LC' => 'Saint Lucia',
		'MF' => 'Saint Martin',
		'PM' => 'Saint Pierre And Miquelon',
		'VC' => 'Saint Vincent And Grenadines',
		'WS' => 'Samoa',
		'SM' => 'San Marino',
		'ST' => 'Sao Tome And Principe',
		'SA' => 'Saudi Arabia',
		'SN' => 'Senegal',
		'RS' => 'Serbia',
		'SC' => 'Seychelles',
		'SL' => 'Sierra Leone',
		'SG' => 'Singapore',
		'SK' => 'Slovakia',
		'SI' => 'Slovenia',
		'SB' => 'Solomon Islands',
		'SO' => 'Somalia',
		'ZA' => 'South Africa',
		'GS' => 'South Georgia And Sandwich Isl.',
		'ES' => 'Spain',
		'LK' => 'Sri Lanka',
		'SD' => 'Sudan',
		'SR' => 'Suriname',
		'SJ' => 'Svalbard And Jan Mayen',
		'SZ' => 'Swaziland',
		'SE' => 'Sweden',
		'CH' => 'Switzerland',
		'SY' => 'Syrian Arab Republic',
		'TW' => 'Taiwan',
		'TJ' => 'Tajikistan',
		'TZ' => 'Tanzania',
		'TH' => 'Thailand',
		'TL' => 'Timor-Leste',
		'TG' => 'Togo',
		'TK' => 'Tokelau',
		'TO' => 'Tonga',
		'TT' => 'Trinidad And Tobago',
		'TN' => 'Tunisia',
		'TR' => 'Turkey',
		'TM' => 'Turkmenistan',
		'TC' => 'Turks And Caicos Islands',
		'TV' => 'Tuvalu',
		'UG' => 'Uganda',
		'UA' => 'Ukraine',
		'AE' => 'United Arab Emirates',
		'GB' => 'United Kingdom',
		'US' => 'United States',
		'UM' => 'United States Outlying Islands',
		'UY' => 'Uruguay',
		'UZ' => 'Uzbekistan',
		'VU' => 'Vanuatu',
		'VE' => 'Venezuela',
		'VN' => 'Viet Nam',
		'VG' => 'Virgin Islands, British',
		'VI' => 'Virgin Islands, U.S.',
		'WF' => 'Wallis And Futuna',
		'EH' => 'Western Sahara',
		'YE' => 'Yemen',
		'ZM' => 'Zambia',
		'ZW' => 'Zimbabwe',
	);	
	$aihello_countriesList = '<form action="" method="post">';	
	$aihello_countriesList .= '<div class="form-group">';
	$aihello_countriesList .= '<select name="aihello_shippingcountry" class="form-control">';
	foreach($countries as $key=>$value){ 
		if(isset($_SESSION['aihello_usercountry'])&& $_SESSION['aihello_usercountry']==$key){
			$aihello_countriesList .= '<option value="'.$key.'" selected="selected">'.$value.'</option>';
		} else {
			$aihello_countriesList .= '<option value="'.$key.'">'.$value.'</option>';
		}
	}
	$aihello_countriesList .= '</select></div>';
	$aihello_countriesList .= '<div class="form-group"><button type="submit" name="aihellochooselocationbtn" 
	class="btn btn-primary">'.esc_html('Done','aihello-smart-ecommerce').'</button></div>';
	$aihello_countriesList .= '</form>';
	return $aihello_countriesList;
}
function aihello_change_currency_symbol($currency_symbol, $currency){
	if(isset($_SESSION['aihello_currency'])) {
		$currency_code 		= $_SESSION['aihello_currency'];
		$currency_symbol 	= aihello_get_currency_symbol_by_code($currency_code);
	}
	return $currency_symbol;
}

function get_user_geo_country(){
	$aihello_geo      = new WC_Geolocation(); // Get AiHello_SmartFulfillment instance object
    $user_ip  = $aihello_geo->get_external_ip_address(); // Get user IP
    $user_geo = $aihello_geo->geolocate_ip( $user_ip ); // Get geolocated user data.
    $country  = $user_geo['country']; // Get the country code
    return WC()->countries->countries[ $country ]; // return the country name
}

function aihello_change_shipping_address(){
	
	if(isset($_SESSION['aihello_usercountry'])):
		$aihello_countryName = WC()->countries->countries[$_SESSION['aihello_usercountry']];
	else:
		$aihello_countryName = esc_html('Your Country','aihello-smart-ecommerce');
	endif;
	
	$aihello_myshippingcountry = '<button type="button" class="btn btn-light" data-toggle="modal" 
	data-target="#aihelloshippingcountry">'.esc_html('Delivery to ','aihello-smart-ecommerce').$aihello_countryName.'</button>
	<!-- Modal -->
	  <div class="modal fade" id="aihelloshippingcountry" role="dialog" style="display:none">
		<div class="modal-dialog modal-md">
		  <div class="modal-content">
			<div class="modal-header">
			 <h4 class="modal-title">'.esc_html('Choose your location','aihello-smart-ecommerce').'</h4>
			  <button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body">'.aihello_countrieslist().'</div>
		  </div>
		</div>
	  </div>
	</div>';
	return $aihello_myshippingcountry;
}