<?php

abstract class UEGoogleAPIClient{

	const PARAM_QUERY = "__query__";

	const PLACES_API_NEW_BASE_URL = "https://places.googleapis.com/v1";

	private $accessToken;
	private $apiKey;
	private $cacheTime = 0; // in seconds

	/**
	 * Set the access token.
	 *
	 * @param string $token
	 *
	 * @return void
	 */
	public function setAccessToken($token){

		$this->accessToken = $token;
	}

	/**
	 * Set the API key.
	 *
	 * @param string $key
	 *
	 * @return void
	 */
	public function setApiKey($key){

		$this->apiKey = $key;
	}

	/**
	 * Set the cache time.
	 *
	 * @param int $seconds
	 *
	 * @return void
	 */
	public function setCacheTime($seconds){

		$this->cacheTime = $seconds;
	}

	/**
	 * Get the base URL for the API.
	 *
	 * @return string
	 */
	abstract protected function getBaseUrl();

	/**
	 * Make a GET request to the API.
	 *
	 * @param $endpoint
	 * @param $params
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function get($endpoint, $params = array()){
		
		return $this->request(UEHttpRequest::METHOD_GET, $endpoint, $params);
	}

	/**
	 * Make a GET request to Places API (New).
	 *
	 * @link https://developers.google.com/maps/documentation/places/web-service/place-details
	 *
	 * @param string $endpoint e.g. "/places/ChIJ..."
	 * @param array $params Query parameters (e.g. languageCode).
	 * @param string $fieldMask Comma-separated value for the X-Goog-FieldMask header.
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function getPlacesNew($endpoint, $params = array(), $fieldMask = ""){

		return $this->requestPlacesNew(UEHttpRequest::METHOD_GET, $endpoint, $params, $fieldMask);
	}

	/**
	 * Make a PUT request to the API.
	 *
	 * @param $endpoint
	 * @param $params
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function put($endpoint, $params = array()){

		return $this->request(UEHttpRequest::METHOD_PUT, $endpoint, $params);
	}

	/**
	 * Make a POST request to the API.
	 *
	 * @param $endpoint
	 * @param $params
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function post($endpoint, $params = array()){

		return $this->request(UEHttpRequest::METHOD_POST, $endpoint, $params);
	}

	/**
	 * Make a request to the API.
	 *
	 * @param string $method
	 * @param string $endpoint
	 * @param array $params
	 * @param array $options Optional: base_url, auth_headers (bool), headers (array).
	 *
	 * @return array
	 * @throws Exception
	 */
	private function request($method, $endpoint, $params = array(), $options = array()){

		$baseUrl = UniteFunctionsUC::getVal($options, "base_url");
		if(empty($baseUrl))
			$baseUrl = $this->getBaseUrl();

		$url = $baseUrl . $endpoint;
		$query = ($method === UEHttpRequest::METHOD_GET) ? $params : array();
		$body = ($method !== UEHttpRequest::METHOD_GET) ? $params : array();

		if(empty($params[self::PARAM_QUERY]) === false){
			$query = array_merge($query, $params[self::PARAM_QUERY]);

			unset($params[self::PARAM_QUERY]);
		}

		$useAuthHeaders = UniteFunctionsUC::getVal($options, "auth_headers", false);
		$headers = UniteFunctionsUC::getVal($options, "headers", array());

		if($useAuthHeaders === true)
			$headers = array_merge($headers, $this->getAuthHeaders());
		else
			$query = array_merge($query, $this->getAuthParams());

		$request = UEHttp::make();

		if(empty($headers) === false)
			$request->withHeaders($headers);
				
		$request->asJson();
		$request->acceptJson();
		
		$request->cacheTime($this->cacheTime);
		$request->withQuery($query);
		$request->withBody($body);
		
		
		$request->validateResponse(function($response){

			$data = $response->json();
			
			if(empty($data["error"]) === false){
				$error = $data["error"];
				$message = $error["message"];
				$status = isset($error["status"]) ? $error["status"] : $error["code"];

				$this->throwError("$message ($status)");
			}elseif(empty($data["error_message"]) === false){
				$message = $data["error_message"];
				$status = isset($data["status"]) ? $data["status"] : $data["code"];

				$this->throwError("$message ($status)");
			}
		});
		
		
		$response = $request->request($method, $url);
		$data = $response->json();

		return $data;
	}

	/**
	 * Make a request to Places API (New).
	 *
	 * @param string $method
	 * @param string $endpoint
	 * @param array $params
	 * @param string $fieldMask
	 *
	 * @return array
	 * @throws Exception
	 */
	private function requestPlacesNew($method, $endpoint, $params = array(), $fieldMask = ""){

		$headers = array();

		if(empty($fieldMask) === false)
			$headers["X-Goog-FieldMask"] = $fieldMask;

		return $this->request($method, $endpoint, $params, array(
			"base_url" => self::PLACES_API_NEW_BASE_URL,
			"auth_headers" => true,
			"headers" => $headers,
		));
	}

	/**
	 * Get authorization headers for Places API (New).
	 *
	 * @return array
	 * @throws Exception
	 */
	private function getAuthHeaders(){

		if(empty($this->accessToken) === false)
			return array("Authorization" => "Bearer " . $this->accessToken);

		if(empty($this->apiKey) === false)
			return array("X-Goog-Api-Key" => $this->apiKey);

		$this->throwError("Either an access token or an API key must be specified.");
	}

	/**
	 * Get parameters for the authorization.
	 *
	 * @return array
	 * @throws Exception
	 */
	private function getAuthParams(){

		if(empty($this->accessToken) === false)
			return array("access_token" => $this->accessToken);

		if(empty($this->apiKey) === false)
			return array("key" => $this->apiKey);

		$this->throwError("Either an access token or an API key must be specified.");
	}

	/**
	 * Thrown an exception with the given message.
	 *
	 * @param string $message
	 *
	 * @return void
	 * @throws Exception
	 */
	private function throwError($message){

		UniteFunctionsUC::throwError("Google API Error: $message");
	}

}
