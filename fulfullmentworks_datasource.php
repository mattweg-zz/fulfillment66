<?php
/**
 * FulfillmentworksDatasource class
 *
 * @package fulfillment66
 * @author Matt Weg
 **/
class FulfillmentworksDatasource {
	private $WSDL;
	private $API_USER;
	private $API_PASSWORD;
	private $transactionId;
	private $request;
	private $response;
	private $errors;
	private $xml;
	private $client;
	private $debug;
	
	function __construct($wsdl=null,$username=null,$password=null,$debug=false) {
		$this->WSDL = $wsdl;
		$this->API_USER = $username;
		$this->API_PASSWORD = $password;
		$this->debug = ($debug) ? true:false;
		$this->errors = array();
	}
	
	public function execute($method,$params) {
		$response = null;
		$options = array();
		if($this->debug) $options['trace'] = true;
		try { 
			$client = new SoapClient($this->WSDL,$options);
			$headers = array(
				'Username' => $this->API_USER,
				'Password' => $this->API_PASSWORD,
			);
			$header = new SoapHeader('http://sma-promail/', 'AuthenticationHeader', $headers);
			$client->__setSoapHeaders($header);
			$result = $client->$method($params); 
		} catch (Exception $e) {
			$this->errors[] = $result = $e->getMessage(); 
		}
		if($this->debug) {
			$this->params = $params;
			$this->request = $client->__getLastRequest();
			$this->response = $client->__getLastResponse();
		}
		unset($client);
		return $result;
	}

	function addOrder($orderInfo,$items,$shipping) {

		$params = array(
			'order' => array(
				'Header'	=> array(
					'ID'				=> $orderInfo['id'],
					'EntryDate'		 => date('c'),
					'OrderEntryView'  => array('Description' => 'Default'),
					'Comments'		=> 'Cart66 Transaction ID: '.$orderInfo['trans_id'],
					'IpAddress'		 => $this->getIp()
				),
				'Shipping' => array(
					'FreightCode' => $shipping['code'],
					'FreightCodeDescription' => $shipping['description']
				),
				'OrderedBy' => array(
					'Prefix' => '',
					'FirstName'	 => $orderInfo['bill_first_name'],
					'LastName'		=> $orderInfo['bill_last_name'],
					'Address1'		=> $orderInfo['bill_address'],
					'Address2'		=> $orderInfo['bill_address2'],
					'City'			=> $orderInfo['bill_city'],
					'State'		 => $orderInfo['bill_state'],
					'PostalCode'	=> $orderInfo['bill_zip'],
					'Country'		 => 'US',
					'Phone'		 => $orderInfo['phone'],
					'Email'		 => $orderInfo['email'],
					'UID'			 => $orderInfo['account_id'],
					'TaxExempt'		 => 'false',
					'TaxExemptApproved' => 'false',
					'Commercial'		=> 'false',
				),
				'ShipTo'	=> array(
					'OrderShipTo' => array(
						'Prefix' => '',
						'FirstName'	 => $orderInfo['ship_first_name'],
						'LastName'		=> $orderInfo['ship_last_name'],
						'Address1'		=> $orderInfo['ship_address'],
						'Address2'		=> $orderInfo['ship_address2'],
						'City'			=> $orderInfo['ship_city'],
						'State'		 => $orderInfo['ship_state'],
						'PostalCode'	=> $orderInfo['ship_zip'],
						'Country'		 => 'US',
						'Phone'		 => $orderInfo['phone'],
						'Email'		 => $orderInfo['email'],
						'UID'			 => $orderInfo['account_id'],
						'TaxExempt'	 => 'false',
						'TaxExemptApproved' => 'false',
						'Commercial'	=> 'false',
						'Flag'			=> 'Other',
						'Key'			 => '0'
					),
				),
				'BillTo'  => array( // we're not sending billing info
					'TaxExempt'		 => 'false',
					'TaxExemptApproved' => 'false',
					'Commercial'		=> 'false',
					'Flag' => 'OrderedBy',
				),
				'Offers' => array(
					'OfferOrdered' => array(),
				),
			),
		);
		
		foreach ($items as $item) {
			$item_id = $item->item_number;
			if(strstr($item_id,'_')){
				list($item_id) = explode('_',$item_id);
			}
			
			$params['order']['Offers']['OfferOrdered'][] = array(
				'Offer' => array(
					'Header' => array(
						'ID' => $item_id,
					),
				),
				'Quantity' => $item->quantity,
				'OrderShipToKey' => array('Key' => '0'), //match the key above under ShipTo->OrderShipTo
				'UnitPrice' => $item->product_price,
				'ShippingHandling' => (float) 0.00, //TODO break out shipping quote by product
			);
		}
		
		$order = $this->execute('AddOrder', $params);
		
		return $order->AddOrderResult;

	}

	function getDebugString() {
		$str .= "\n\nAPI Params:\n";
		$str .= print_r($this->params,true);

		$str .= "\n\nErrors:\n";
		$str .= print_r($this->errors,true);

		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;
		$doc->strictErrorChecking = false;
		if(@$doc->loadXML($this->request)) {
			$str .= "\n\nSOAP API Request:\n";
			$str .= $doc->saveXML();
		}
		if(@$doc->loadXML($this->response)) {
			$str .= "\n\nSOAP API Response:\n";
			$str .= $doc->saveXML();
		}
		return $str;
	}
	
	function getIp(){
		if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"]) && strlen($_SERVER["HTTP_X_FORWARDED_FOR"]) > 0) { 
			$f = $_SERVER["HTTP_X_FORWARDED_FOR"];
			$reserved = false;
			if (substr($f, 0, 3) == "10.") {
				$reserved = true;
			}
			if (substr($f, 0, 4) == "172." && substr($f, 4, 2) > 15 && substr($f, 4, 2) < 32) {
				$reserved = true;
			}
			if (substr($f, 0, 8) == "192.168.") {
				$reserved = true;
			}
			if (!$reserved) {
				$ip = $f;
			}
		} 
		if (!isset($value)) {
			$ip = $_SERVER["REMOTE_ADDR"];
		}
		return $ip;
	}
	
} // END class 

?>