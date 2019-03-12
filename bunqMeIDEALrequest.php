<?php
	header('Content-Type: application/json');
	$postData = json_decode(file_get_contents('php://input'), true);

	//Change this. Your bunq.me URL 
	$bunqMeUrl = 'https://bunq.me/pay';
	
	$amount = $postData['amount'];
	$paymentDescription = $postData['description'];
	$idealIsssuer = $postData['issuer'];
	$GLOBALS['bunqMeUuid'] = '';
	
	$generateIdealUrl = generateIdealUrl($bunqMeUrl, $amount, $paymentDescription, $idealIsssuer);
	echo $generateIdealUrl;

	function httpPost($url, $headers, $postData){ 
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

		// HTTP Headers for POST request 
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		 
		$response = curl_exec($ch);
		$jsonData = json_decode($response, true);
		
		if (empty($response) OR (curl_getinfo($ch, CURLINFO_HTTP_CODE == 500))) {
			die(curl_error($ch));
			curl_close($ch); 
			return false;
		} else if (isset($jsonData['Error'])){
			var_dump($jsonData);
			die('Error in response');
			return false;
		} 
		
		curl_close($ch);

		return $jsonData;
	}
	
	function httpGet($url, $headers){ 
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		// HTTP Headers for GET request 
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		 
		$response = curl_exec($ch);
		$jsonData = json_decode($response, true);
		
		if (empty($response) OR (curl_getinfo($ch, CURLINFO_HTTP_CODE == 500))) {
			die(curl_error($ch));
			curl_close($ch); 
			return false;
		} else if (isset($jsonData['Error'])){
			var_dump($jsonData);
			die('Error in response');
			return false;
		} 
		
		curl_close($ch);

		return $jsonData;
	}

	function getBunqMeMerchantRequest($url){
		// HTTP Headers for GET request 
		$headers = array(
			'Content-Type: application/json',
			'Content-Length: 0',
			'X-Bunq-Client-Request-Id: ' . uniqid(),
			'X-Bunq-Geolocation: 0 0 0 0 NL',
			'X-Bunq-Language: en_US',
			'X-Bunq-Region: en_US'
		);
		
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		 
		$response = curl_exec($ch);
		$jsonData = json_decode($response, true);
		
		if (empty($response) OR (curl_getinfo($ch, CURLINFO_HTTP_CODE == 500))) {
			die(curl_error($ch));
			curl_close($ch); 
			return false;
		} else if (isset($jsonData['Error'])){
			var_dump($jsonData);
			die('Error in response');
			return false;
		} 
		
		curl_close($ch);

		return $jsonData;
	}
	
	function getBunqMeQrCode($amount, $description){
		// POST data
		$postArray = array(
			'amount' => [
				'currency' => 'EUR',
				'value' => $amount
			],
			'description' => $description
		);
		$postData = json_encode($postArray);
				
		// HTTP Headers for GET request 
		$headers = array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($postData),
			'X-Bunq-Client-Request-Id: ' . uniqid(),
			'X-Bunq-Geolocation: 0 0 0 0 NL',
			'X-Bunq-Language: en_US',
			'X-Bunq-Region: en_US'
		);

		$requestUuidQrCode = httpPost('https://api.bunq.me/v1/bunqme-fundraiser-profile/'.$GLOBALS['bunqMeUuid'].'/qr-code-content', $headers, $postData);
		$QrUuid = $requestUuidQrCode['Response'][0]['Uuid']['uuid'];
		
		$getHeaders = array(
			'Content-Type: application/json',
			'Content-Length: 0',
			'X-Bunq-Client-Request-Id: ' . uniqid(),
			'X-Bunq-Geolocation: 0 0 0 0 NL',
			'X-Bunq-Language: en_US',
			'X-Bunq-Region: en_US'
		);
		$requestQrCodeContent = httpGet('https://api.bunq.me/v1/bunqme-fundraiser-profile/'.$GLOBALS['bunqMeUuid'].'/qr-code-content/'.$QrUuid, $getHeaders);
		$base64QrCode = $requestQrCodeContent['Response'][0]['QrCodeImage']['base64'];
		$bunqToken = $requestQrCodeContent['Response'][0]['QrCodeImage']['token'];
		
		$response[0] = array(
			'base64QrCode' => $base64QrCode,
			'bunqToken' => $bunqToken
		);
		
		return $response[0];
	}

	function generateIdealUrl($bunqmeUrl, $amount, $description, $issuer) {
		$bunqMeRequestUuid = getBunqMe($bunqmeUrl, $amount, $description, $issuer);
		if($issuer == 'BUNQNL2A'){			
			$bunqQrCode = getBunqMeQrCode($amount, $description);
			$response[0] = array(
				'url' => '',
				'bunqQrCode' => $bunqQrCode['base64QrCode'],
				'bunqToken' => $bunqQrCode['bunqToken'],
			);
		}else{
			$reqCount = 0;
			do {
				$reqCount++;
				$merchantRequest = getBunqMeMerchantRequest('https://api.bunq.me/v1/bunqme-merchant-request/'.$bunqMeRequestUuid.'?_='.uniqid());	
				$status = $merchantRequest['Response'][0]['BunqMeMerchantRequest']['status'];
			} while ($status == 'PAYMENT_WAITING_FOR_CREATION' && $reqCount < 10);
			$merchantRequest = getBunqMeMerchantRequest('https://api.bunq.me/v1/bunqme-merchant-request/'.$bunqMeRequestUuid.'?_='.uniqid());	
			$issuerUrl = $merchantRequest['Response'][0]['BunqMeMerchantRequest']['issuer_authentication_url'];
			$response[0] = array(
				'url' => $issuerUrl,
				'bunqQrCode' => '',
				'bunqToken' => '',
			);
		}
	
		return json_encode($response[0]);
	}

	function getBunqMe($bunqmeUrl, $amount, $description, $issuer) {
		// POST data
		$postArray = array(
			'pointer' => [
				'type' => 'URL',
				'value' => $bunqmeUrl
			]
		);
		$postData = json_encode($postArray);
		 
		$headers = array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($postData),
			'X-Bunq-Client-Request-Id: ' . uniqid(),
			'X-Bunq-Geolocation: 0 0 0 0 NL',
			'X-Bunq-Language: en_US',
			'X-Bunq-Region: en_US'
		); 
		
		$jsonData = httpPost('https://api.bunq.me/v1/bunqme-fundraiser-profile', $headers, $postData);
		$bunqmeUuid = $jsonData['Response'][0]['BunqMeFundraiserProfile']['uuid'];
		$GLOBALS['bunqMeUuid'] = $bunqmeUuid;
		
		if($issuer == 'BUNQNL2A'){
			return true;
		}else{
			return getRequestUuid($bunqmeUuid, $amount, $description, $issuer);
		}
	}

	function getRequestUuid($bunqmeUuid, $amount, $description, $issuer) {
		// POST data
		$postArray = array(
			'amount_requested' => [
				'currency' => 'EUR',
				'value' => $amount
			],
			'issuer' => $issuer,
			'merchant_type' => 'IDEAL',
			'bunqme_type' => 'FUNDRAISER',
			'bunqme_uuid' => $bunqmeUuid,
			'description' => $description
		);
		$postData = json_encode($postArray);
		
		$headers = array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($postData),
			'X-Bunq-Client-Request-Id: ' . uniqid(),
			'X-Bunq-Geolocation: 0 0 0 0 NL',
			'X-Bunq-Language: en_US',
			'X-Bunq-Region: en_US'
		);
			
		$jsonData = httpPost('https://api.bunq.me/v1/bunqme-merchant-request', $headers, $postData);
		return $jsonData['Response'][0]['BunqMeMerchantRequest']['uuid'];
	}