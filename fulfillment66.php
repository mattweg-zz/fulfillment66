<?php
/*
Plugin Name: Fulfillment66
Plugin URI: 
Description: Integrates Filfillmentworks API with Cart66 
Version: 1.0
Author: Matt Weghorst 
Author URI: http://wegweb.net
License: GNU

*/
if (!defined('DS')) {
	define('DS', DIRECTORY_SEPARATOR);
}

function f66_process_order($orderInfo) {	
	//Get the cart items
	$order = new Cart66Order($orderInfo['id']); 
	$items = $order->getItems();  //Provides the cart...
	
	include_once(dirname(__FILE__) . DS ."fulfullmentworks_datasource.php");
	if(!isset($fwds)) {
		$username = f66_get_option('f66_uname');
		$password = f66_get_option('f66_pwd');
		$wsdl = f66_get_option('f66_wsdl');
		$fwds = new FulfillmentworksDatasource($wsdl,$username,$password,f66_get_option('f66_debug'));
	}
	$cart = new Cart66Cart();
	$f66_shipping = json_decode(f66_get_option('f66_shipping'),true);
	$shippingMethods = $cart->getShippingMethods();
	$shipping = array();
	if(!empty($shippingMethods[$orderInfo['shipping_method']])) {
		$shippingMethodId = $shippingMethods[$orderInfo['shipping_method']];
		if(!empty($f66_shipping[$shippingMethodId])) {
			$shipping = $f66_shipping[$shippingMethodId];
		}
	}
	$res = $fwds->addOrder($orderInfo,$items,$shipping);
	if(!empty($res)){
		$order->updateStatus('Order Complete');
	}

	if(f66_get_option('f66_debug')){
		$debug_str = "############### Debug Info ###############\n\nOrderInfo:\n";
		$debug_str .= print_r($orderInfo,true);
		$debug_str .= "\n\nItems:\n";
		$debug_str .= print_r($items,true);
		$debug_str .= "\n\nFulfillmentworks Response:\n";
		$debug_str .= print_r($res,true);
		$debug_str .= $fwds->getDebugString();
		wp_mail( f66_get_option('f66_debug_email'), "Fulfillment66 API debug information", $debug_str );
	}
}

add_action('cart66_after_order_saved', 'f66_process_order', 10, 1);

// set an  Fulfillment66 option in the options table of WordPress
function f66_set_option($option_name, $option_value) {
	$f66_options = get_option('f66_admin_options');
	$f66_options[$option_name] = $option_value;
	update_option('f66_admin_options', $f66_options);
}

function f66_get_option($option_name) {
	$f66_options = get_option('f66_admin_options'); 
	if (!$f66_options || !array_key_exists($option_name, $f66_options)) {
		$f66_default_options=array();
	
		$f66_default_options['f66_uname'] = '';	 
		$f66_default_options['f66_pwd'] = '';
		$f66_default_options['f66_wsdl'] = 'https://pm.orders.fulfillmentworks.com/pmomsws/order.asmx?WSDL';
		$f66_default_options['f66_shipping'] = '[]';

		add_option('f66_admin_options', $f66_default_options, 'Settings for Fulfillment66 plugin');

		$result = $f66_default_options[$option_name];
	} else {
		$result = stripslashes($f66_options[$option_name]);
		
		if(substr($result, -2) == '\\/') {
			$result = substr($result,0,-1);
		}
	}
	
	return $result;
}


add_action('admin_menu', 'f66_plugin_menu');

function f66_plugin_menu() {
	add_options_page('Fulfillment66 Plugin Options', 'Fulfillment66', 'manage_options', 'Fulfillment66', 'f66_options');
}

function f66_options() {
	if (!current_user_can('manage_options'))  {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}
	if (isset($_POST['submit'])):
		// process options form
		$f66_options = get_option('f66_admin_options');
		$f66_options['f66_uname'] = $_POST['f66_uname'];  
		$f66_options['f66_pwd'] = $_POST['f66_pwd'];
		$f66_options['f66_debug'] = $_POST['f66_debug'];
		$f66_options['f66_debug_email'] = $_POST['f66_debug_email'];  
		$f66_options['f66_request_url'] = $_POST['f66_request_url'];
		$f66_options['f66_shipping'] = json_encode($_POST['f66_shipping']);
	
		update_option('f66_admin_options', $f66_options);
	?>
<div class="updated"><p><strong>Options saved</strong></p></div>
<?php endif; 
	// Admin Page Form
?>
<div class=wrap>
	<form method="post">
	<h2>Fulfillment66</h2>
	<p>Integrates Cart66 with the Fulfillmentworks AddOrder API</p>
	<h3>Fulfillmentworks API Settings</h3>
	<table width="100%" cellspacing="2" cellpadding="5" class="form-table"><tbody>
		<tr valign="top">
			<th scope="row"><label for="f66_uname">Username</label></th>
			<td><input name="f66_uname" type="text" id="f66_uname" value="<?php echo f66_get_option('f66_uname'); ?>" class="regular-text" />
			<span class="description">Enter the Username that has been provided to you by fulfillmentworks.</span>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="f66_pwd">Password</label></th>
			<td><input name="f66_pwd" type="password" id="f66_pwd" value="<?php echo f66_get_option('f66_pwd'); ?>" class="regular-text" />
			<span class="description">Enter the password that has been provided to you by fulfillmentworks.</span>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="f66_wsdl">WSDL Url</label></th>
			<td><input name="f66_wsdl" type="text" id="f66_wsdl" value="<?php echo f66_get_option('f66_wsdl'); ?>" class="regular-text" />
			<span class="description">The WSDL URL used for the Fulfillmentwork API.</span>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><span>Debug</span></th>
			<td><fieldset>
			<legend class="screen-reader-text"><span>Debug</span></legend>
			<input name="f66_debug" type="hidden" value="0">
			<label for="f66_debug"><input name="f66_debug" type="checkbox" id="f66_debug" value="1" <?php echo (f66_get_option('f66_debug'))?'checked':''; ?> >Turn on API debug emails</label>
			<br>
			</fieldset></td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="f66_debug_email">Debug Email</label></th>
			<td><input name="f66_debug_email" type="text" id="f66_debug_email" value="<?php echo f66_get_option('f66_debug_email'); ?>" class="regular-text" />
			<span class="description">The email used to send debug information.</span>
			</td>
		</tr>
	</tbody></table>
	<h3>Shipping Method Configuration</h3>
	<p>Configure your Cart66 shipping methods so that the correspond with the Fulfillmentworks Freight Codes and Descriptions</p>
	<h4>Fulfillmentworks Freight Codes and Descriptions</h4>
	<table>
		<tr><th>Carrier</th><th>Freight Code</th><th>Freight Description</th></tr>
		<tr><td>USPS</td><td>P01</td><td>First Class Mail</td></tr>
		<tr><td>USPS</td><td>P03</td><td>Priority Mail</td></tr>
		<tr><td>USPS</td><td>P04</td><td>Express</td></tr>
		<tr><td>USPS</td><td>S01</td><td>Parcels</td></tr>
		<tr><td>USPS</td><td>S03</td><td>STD Irregular</td></tr>
		<tr><td>USPS</td><td>P60</td><td>First Class Mail International</td></tr>
		<tr><td>USPS</td><td>P63</td><td>Priority Mail International</td></tr>
		<tr><td>USPS</td><td>P78</td><td>Global Express Mail</td></tr>
		<tr><td>UPS</td><td>U01</td><td>Next Day</td></tr>
		<tr><td>UPS</td><td>U07</td><td>2nd Day</td></tr>
		<tr><td>UPS</td><td>U11</td><td>Ground</td></tr>
		<tr><td>UPS</td><td>U21 </td><td>3 Day</td></tr>
		<tr><td>UPS</td><td>U48</td><td>Standard to Canada</td></tr>
		<tr><td>UPS</td><td>U49</td><td>Express International</td></tr>
		<tr><td>UPS</td><td>U54</td><td>Expedited International</td></tr>
	</table>
	<h4>Shipping Method Translations</h4>
	<p>For each shipping method, fill in the values from the available freight codes and descriptions in the table above.</p>
	<table>
	<tr><th>Cart66<br/> Shipping Method</th><th>Fulfillmentworks<br/> Freight Code</th><th>Fulfillmentworks<br/> Freight Description</th></tr>
	<?php
		$cart = new Cart66Cart();
		$f66_shipping = json_decode(f66_get_option('f66_shipping'),true);
		foreach($cart->getShippingMethods() as $methodName=>$shippingId):
			$description = (!empty($f66_shipping[$shippingId]['description']))?$f66_shipping[$shippingId]['description']:'';
			$code = (!empty($f66_shipping[$shippingId]['code']))?$f66_shipping[$shippingId]['code']:'';
	?>
	<tr><td><?php echo $methodName; ?></td><td><input type="text" name="f66_shipping[<?php echo $shippingId ?>][code]" value="<?php echo $code; ?>" /></td><td><input type="text" name="f66_shipping[<?php echo $shippingId ?>][description]" value="<?php echo $description; ?>" /></td></tr>
	<?php
		endforeach;
	?>
	</table>
	<p class="submit">
		<input type="submit" name="submit" id="submit" value="Save Changes" class="button-primary" />
	</p>
	</form>
</div><?php
 
}


// WARNING! Do not allow any space after the closing PHP Tag!
?>