<?php

/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved.
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class UniteCreatorAPIIntegrations{

	const FORMAT_DATE = "d.m.Y";
	const FORMAT_DATETIME = "d.m.Y H:i";
	const FORMAT_MYSQL_DATETIME = "Y-m-d H:i:s";
	
	const TYPE_CURRENCY_EXCHANGE = "currency_exchange";
	const TYPE_GOOGLE_EVENTS = "google_events";
	const TYPE_GOOGLE_REVIEWS = "google_reviews";
	const TYPE_GOOGLE_SHEETS = "google_sheets";
	const TYPE_WEATHER_FORECAST = "weather_forecast";
	const TYPE_YOUTUBE_PLAYLIST = "youtube_playlist";

	const SETTINGS_OPEN_WEATHER_API_KEY = "openweather_api_key";
	const SETTINGS_EXCHANGE_RATE_API_KEY = "exchangerate_api_key";

	const CURRENCY_EXCHANGE_FIELD_EMPTY_API_KEY = "currency_exchange_empty_api_key";
	const CURRENCY_EXCHANGE_FIELD_CURRENCY = "currency_exchange_currency";
	const CURRENCY_EXCHANGE_FIELD_PRECISION = "currency_exchange_precision";
	const CURRENCY_EXCHANGE_FIELD_INCLUDE_CURRENCIES = "currency_exchange_include_currencies";
	const CURRENCY_EXCHANGE_FIELD_CACHE_TIME = "currency_exchange_cache_time";
	const CURRENCY_EXCHANGE_MIN_PRECISION = 2;
	const CURRENCY_EXCHANGE_MAX_PRECISION = 6;
	const CURRENCY_EXCHANGE_DEFAULT_PRECISION = 2;
	const CURRENCY_EXCHANGE_DEFAULT_CACHE_TIME = 60;

	const GOOGLE_EVENTS_FIELD_EMPTY_CREDENTIALS = "google_events_empty_credentials";
	const GOOGLE_EVENTS_FIELD_TIMEZONE = "google_events_timezone";
	const GOOGLE_EVENTS_FIELD_CALENDAR_ID = "google_events_calendar_id";
	const GOOGLE_EVENTS_FIELD_RANGE = "google_events_range";
	const GOOGLE_EVENTS_FIELD_ORDER = "google_events_order";
	const GOOGLE_EVENTS_FIELD_LIMIT = "google_events_limit";
	const GOOGLE_EVENTS_FIELD_CACHE_TIME = "google_events_cache_time";
	const GOOGLE_EVENTS_FIELD_REQUIRE_TITLE = "google_events_require_title";
	const GOOGLE_EVENTS_DEFAULT_LIMIT = 250;
	const GOOGLE_EVENTS_DEFAULT_CACHE_TIME = 10;
	const GOOGLE_EVENTS_RANGE_UPCOMING = "upcoming";
	const GOOGLE_EVENTS_RANGE_TODAY = "today";
	const GOOGLE_EVENTS_RANGE_TOMORROW = "tomorrow";
	const GOOGLE_EVENTS_RANGE_WEEK = "week";
	const GOOGLE_EVENTS_RANGE_MONTH = "month";
	const GOOGLE_EVENTS_ORDER_DATE_ASC = "date:asc";
	const GOOGLE_EVENTS_ORDER_DATE_DESC = "date:desc";

	const GOOGLE_REVIEWS_FIELD_EMPTY_API_KEY = "google_reviews_empty_api_key";
	const GOOGLE_REVIEWS_FIELD_PLACE_ID = "google_reviews_place_id";
	const GOOGLE_REVIEWS_FIELD_API_TEXT = "google_reviews_api_text";
	const GOOGLE_REVIEWS_FIELD_CACHE_TIME = "google_reviews_cache_time";
	const GOOGLE_REVIEWS_FIELD_LANG = "google_reviews_lang";
	const GOOGLE_REVIEWS_FIELD_SHOW_DEBUG = "google_reviews_show_debug";
	const GOOGLE_REVIEWS_SORT_BY = "google_reviews_sort_by";
	const GOOGLE_REVIEWS_SERP_CACHE_TIME = "google_reviews_serp_cache_time";
	const GOOGLE_REVIEWS_SHOW_DEMO_DATA = "google_reviews_show_demo_data";
	const GOOGLE_REVIEWS_SERP_NUM_REVIEWS = "google_reviews_serp_num_reviews";
	const GOOGLE_REVIEWS_SERP_DEFAULT_NUM_REQUESTS = 2;

	const GOOGLE_REVIEWS_DEFAULT_CACHE_TIME = 10;
	const GOOGLE_REVIEWS_CACHE_TIME_DAY = 86400; // 1 day in seconds
	const GOOGLE_REVIEWS_CACHE_TIME_WEEK = 604800; // 1 week in seconds
	const GOOGLE_REVIEWS_CACHE_TIME_MONTH = 2592000; // 1 month in seconds (30 days)
	

	const GOOGLE_SHEETS_FIELD_EMPTY_CREDENTIALS = "google_sheets_empty_credentials";
	const GOOGLE_SHEETS_FIELD_ID = "google_sheets_id";
	const GOOGLE_SHEETS_FIELD_SHEET_ID = "google_sheets_sheet_id";
	const GOOGLE_SHEETS_FIELD_CACHE_TIME = "google_sheets_cache_time";
	const GOOGLE_SHEETS_DEFAULT_CACHE_TIME = 10;
	const GOOGLE_SHEETS_EXCLUDE_FIRST_ROW = "google_sheets_exclude_first_row";
	const WEATHER_FORECAST_FIELD_EMPTY_API_KEY = "weather_forecast_empty_api_key";
	const WEATHER_FORECAST_FIELD_LOCALE = "weather_forecast_locale";
	const WEATHER_FORECAST_FIELD_COUNTRY = "weather_forecast_country";
	const WEATHER_FORECAST_FIELD_CITY = "weather_forecast_city";
	const WEATHER_FORECAST_FIELD_UNITS = "weather_forecast_units";
	const WEATHER_FORECAST_FIELD_CACHE_TIME = "weather_forecast_cache_time";
	const WEATHER_FORECAST_DEFAULT_CACHE_TIME = 60;
	const WEATHER_FORECAST_UNITS_METRIC = "metric";
	const WEATHER_FORECAST_UNITS_IMPERIAL = "imperial";

	const YOUTUBE_PLAYLIST_FIELD_EMPTY_CREDENTIALS = "youtube_playlist_empty_credentials";
	const YOUTUBE_PLAYLIST_FIELD_ID = "youtube_playlist_id";
	const YOUTUBE_PLAYLIST_FIELD_ORDER = "youtube_playlist_order";
	const YOUTUBE_PLAYLIST_FIELD_LIMIT = "youtube_playlist_limit";
	const YOUTUBE_PLAYLIST_FIELD_CACHE_TIME = "youtube_playlist_cache_time";
	const YOUTUBE_PLAYLIST_DEFAULT_LIMIT = 5;
	const YOUTUBE_PLAYLIST_DEFAULT_CACHE_TIME = 10;
	const YOUTUBE_PLAYLIST_ORDER_DEFAULT = "default";
	const YOUTUBE_PLAYLIST_ORDER_DATE_ADDED_ASC = "date_added:asc";
	const YOUTUBE_PLAYLIST_ORDER_DATE_ADDED_DESC = "date_added:desc";
	const YOUTUBE_PLAYLIST_ORDER_DATE_ADDED_RANDOM = "date_added:random";
	const YOUTUBE_PLAYLIST_ORDER_DATE_PUBLISHED_ASC = "date_published:asc";
	const YOUTUBE_PLAYLIST_ORDER_DATE_PUBLISHED_DESC = "date_published:desc";
	const YOUTUBE_PLAYLIST_ORDER_DATE_PUBLISHED_RANDOM = "date_published:random";

	const ORDER_FIELD = "__order_field";
	const ORDER_DIRECTION_ASC = "asc";
	const ORDER_DIRECTION_DESC = "desc";
	const ORDER_DIRECTION_RANDOM = "random";

	private static $instance = null;
	
	private $googleReviewsShowDebug = false;
	
	private $params = array();
	
	/**
	 * create a new instance
	 *
	 * @return void
	 */
	public function __construct(){
		
		$this->init();
	}

	/**
	 * get the class instance
	 *
	 * @return self
	 */
	public static function getInstance(){

		if(self::$instance === null)
			self::$instance = new self();

		return self::$instance;
	}

	/**
	 * get the api types
	 *
	 * @return array
	 */
	public function getTypes(){

		$types = array();

		if(GlobalsUnlimitedElements::$enableGoogleAPI === true){
			$types[self::TYPE_GOOGLE_EVENTS] = "Google Events";
			$types[self::TYPE_GOOGLE_REVIEWS] = "Google Reviews";
			$types[self::TYPE_GOOGLE_SHEETS] = "Google Sheets";
			$types[self::TYPE_YOUTUBE_PLAYLIST] = "Youtube Playlist";
		}

		if(GlobalsUnlimitedElements::$enableWeatherAPI === true)
			$types[self::TYPE_WEATHER_FORECAST] = "Weather Forecast";

		if(GlobalsUnlimitedElements::$enableCurrencyAPI === true)
			$types[self::TYPE_CURRENCY_EXCHANGE] = "Currency Exchange";

		return $types;
	}
	
	
	
	/**
	 * get the api data
	 *
	 * @return array
	 */
	public function getData($type, $params){
		
		// add api keys
		$params[self::SETTINGS_OPEN_WEATHER_API_KEY] = HelperProviderCoreUC_EL::getGeneralSetting(self::SETTINGS_OPEN_WEATHER_API_KEY);
		$params[self::SETTINGS_EXCHANGE_RATE_API_KEY] = HelperProviderCoreUC_EL::getGeneralSetting(self::SETTINGS_EXCHANGE_RATE_API_KEY);

		$this->params = $this->remapLegacyParams($params);
		
		// get data
		$data = array();

		switch($type){
			case self::TYPE_CURRENCY_EXCHANGE:
				$data = $this->getCurrencyExchangeData();
			break;
			case self::TYPE_GOOGLE_EVENTS:
				$data = $this->getGoogleEventsData();
			break;
			case self::TYPE_GOOGLE_REVIEWS:
				$data = $this->getGoogleReviewsData();
			break;
			case self::TYPE_GOOGLE_SHEETS:
				$data = $this->getGoogleSheetsData();
			break;
			case self::TYPE_WEATHER_FORECAST:
				$data = $this->getWeatherForecastData();
			break;
			case self::TYPE_YOUTUBE_PLAYLIST:
				$data = $this->getYoutubePlaylistData();
			break;
			default:
				
				if(UniteFunctionsUC::isMaxDebug()){
					UniteFunctionsUC::showTrace();
				}
				
				UniteFunctionsUC::throwError(__FUNCTION__ . " Error: API type \"$type\" is not implemented");
			break;
		}

		return $data;
	}

	/**
	 * get the api data for multisource
	 */
	public function getDataForMultisource($type, $params){
		
		$data = $this->getData($type, $params);
		
		switch($type){
			case self::TYPE_CURRENCY_EXCHANGE:
				$data = UniteFunctionsUC::getVal($data, "rates_chosen");
			break;
			case self::TYPE_WEATHER_FORECAST:
				$data = UniteFunctionsUC::getVal($data, "daily");
			break;
			case self::TYPE_GOOGLE_REVIEWS:
				$data = UniteFunctionsUC::getVal($data, "reviews");
			break;
			case self::TYPE_YOUTUBE_PLAYLIST:
				$data = UniteFunctionsUC::getVal($data, "videos");
			break;
			case self::TYPE_GOOGLE_EVENTS:
				$data = UniteFunctionsUC::getVal($data, "events");
			break;
		}

		return $data;
	}

	/**
	 * add the api data to params
	 *
	 * @return array
	 */
	public function addDataToParams($data, $name, $paramType = null){

		$params = UniteFunctionsUC::getVal($data, $name, array());

		if(!is_array($params))
			$params = array();

		foreach($data as $key => $value){
			if(is_string($key) && strpos($key, $name."_") === 0 && !array_key_exists($key, $params))
				$params[$key] = $value;
		}

		$params = UniteFunctionsUC::clearKeysFirstUnderscore($params);
				
		try{
			
			$apiType = UniteFunctionsUC::getVal($params, "api_type");
			
			
			//some small fix - get api type by special param type
			
			if(empty($apiType)){
				switch($paramType){
					case "reviews":
						$apiType = "google_reviews";
					break;
					case "youtube_playlist":
						$apiType = "youtube_playlist";
					break;
					case "google_events":
						$apiType = "google_events";
					break;
				}
			}
			
			$apiData = $this->getData($apiType, $params);
						
			$params["success"] = true;
			$params = array_merge($params, $apiData);
		}catch(Exception $exception){
			$params["success"] = false;
			$params["error"] = $exception->getMessage();
		}

		$data[$name] = $params;

		return $data;
	}

	/**
	 * get settings fields
	 *
	 * @return array
	 */
	public function getSettingsFields($isSingle = false){
		
		$fields = array();
		
		if(GlobalsUnlimitedElements::$enableGoogleAPI === true){
			$fields[self::TYPE_GOOGLE_EVENTS] = $this->getGoogleEventsSettingsFields();
			$fields[self::TYPE_GOOGLE_REVIEWS] = $this->getGoogleReviewsSettingsFields($isSingle);
			$fields[self::TYPE_GOOGLE_SHEETS] = $this->getGoogleSheetsSettingsFields();
			$fields[self::TYPE_YOUTUBE_PLAYLIST] = $this->getYoutubePlaylistSettingsFields();
		}
		
		if(GlobalsUnlimitedElements::$enableCurrencyAPI === true)
			$fields[self::TYPE_CURRENCY_EXCHANGE] = $this->getCurrencyExchangeSettingsFields();

		if(GlobalsUnlimitedElements::$enableWeatherAPI === true)
			$fields[self::TYPE_WEATHER_FORECAST] = $this->getWeatherForecastSettingsFields();

		return $fields;
	}


	/**
	 * add service settings fields
	 *
	 * @return void
	 */
	public function addServiceSettingsFields($settingsManager, $type, $name, $condition = null){

		$fields = $this->getSettingsFields(true);
		$fields = UniteFunctionsUC::getVal($fields, $type);
		
		if(empty($fields))
			return;
		
		// add api type
		$params = array();
		$params["origtype"] = UniteCreatorDialogParam::PARAM_HIDDEN;

		$settingsManager->addHiddenInput($name . "_api_type", $type, "API Type", $params);
		
		// add the fields
		HelperProviderUC::addSettingsFields($settingsManager, $fields, $name, $condition, true);
	}
		
	
	/**
	 * init the api integrations
	 */
	private function init(){
		
		$this->includeServices();
	}

	/**
	 * include service files
	 */
	private function includeServices(){

		$services = new UniteServicesUC();

		if(GlobalsUnlimitedElements::$enableGoogleAPI === true)
			$services->includeGoogleAPI();

		if(GlobalsUnlimitedElements::$enableCurrencyAPI === true)
			$services->includeExchangeRateAPI();

		if(GlobalsUnlimitedElements::$enableWeatherAPI === true)
			$services->includeOpenWeatherAPI();
	}
	
	/**
	 * remap legacy params
	 */
	private function remapLegacyParams($params){

		// TODO: Remove in the next major version
		$keysMap = array(
			"currency_exchange:currency" => self::CURRENCY_EXCHANGE_FIELD_CURRENCY,
			"currency_exchange:precision" => self::CURRENCY_EXCHANGE_FIELD_PRECISION,
			"currency_exchange:include_currencies" => self::CURRENCY_EXCHANGE_FIELD_INCLUDE_CURRENCIES,
			"currency_exchange:cache_time" => self::CURRENCY_EXCHANGE_FIELD_CACHE_TIME,

			"google_events:calendar_id" => self::GOOGLE_EVENTS_FIELD_CALENDAR_ID,
			"google_events:range" => self::GOOGLE_EVENTS_FIELD_RANGE,
			"google_events:order" => self::GOOGLE_EVENTS_FIELD_ORDER,
			"google_events:limit" => self::GOOGLE_EVENTS_FIELD_LIMIT,
			"google_events:cache_time" => self::GOOGLE_EVENTS_FIELD_CACHE_TIME,
			"google_events:require_title" => self::GOOGLE_EVENTS_FIELD_REQUIRE_TITLE,

			"google_reviews:place_id" => self::GOOGLE_REVIEWS_FIELD_PLACE_ID,
			"google_reviews:cache_time" => self::GOOGLE_REVIEWS_FIELD_CACHE_TIME,
			"google_reviews:lang" => self::GOOGLE_REVIEWS_FIELD_LANG,

			"google_sheets:id" => self::GOOGLE_SHEETS_FIELD_ID,
			"google_sheets:sheet_id" => self::GOOGLE_SHEETS_FIELD_SHEET_ID,
			"google_sheets:cache_time" => self::GOOGLE_SHEETS_FIELD_CACHE_TIME,

			"weather_forecast:country" => self::WEATHER_FORECAST_FIELD_COUNTRY,
			"weather_forecast:city" => self::WEATHER_FORECAST_FIELD_CITY,
			"weather_forecast:units" => self::WEATHER_FORECAST_FIELD_UNITS,
			"weather_forecast:cache_time" => self::WEATHER_FORECAST_FIELD_CACHE_TIME,

			"youtube_playlist:id" => self::YOUTUBE_PLAYLIST_FIELD_ID,
			"youtube_playlist:order" => self::YOUTUBE_PLAYLIST_FIELD_ORDER,
			"youtube_playlist:limit" => self::YOUTUBE_PLAYLIST_FIELD_LIMIT,
			"youtube_playlist:cache_time" => self::YOUTUBE_PLAYLIST_FIELD_CACHE_TIME,
		);

		foreach($keysMap as $legacyKey => $key){
			$value = UniteFunctionsUC::getVal($params, $key);
			$legacyValue = UniteFunctionsUC::getVal($params, $legacyKey);

			$params[$key] = $value === '' ? $legacyValue : $value;
		}

		return $params;
	}

	/**
	 * get the param value
	 */
	private function getParam($key, $fallback = null, $name = null){
		
		if(!empty($name))
			$key = $name."_".$key;
		
		$paramValue = UniteFunctionsUC::getVal($this->params, $key);

		if(empty($paramValue))
			$paramValue = $fallback;
		
		return $paramValue;
	}

	/**
	 * get the param value, otherwise throw an exception
	 */
	private function getRequiredParam($key, $label = null, $name = null){
				
		$value = $this->getParam($key, null, $name);
		
		if(empty($value) === false)
			return $value;

		if(empty($label) === true)
			$label = $key;

		// translators: %s is param name
		UniteFunctionsUC::throwError(sprintf(__("%s is required.", "unlimited-elements-for-elementor"), $label));
	}

	/**
	 * get the cache time param
	 */
	private function getCacheTimeParam($key, $default = 10, $name = null){

		$time = $this->getParam($key, $default, $name);
		$time = intval($time);
		$time = max($time, 1); // minimum is 1 minute
		$time *= 60; // convert to seconds

		return $time;
	}

	/**
	 * authorize google service
	 */
	private function authorizeGoogleService($service){
		
		try{
			$service->setAccessToken(UEGoogleAPIHelper::getFreshAccessToken());
		}catch(Exception $exception){
			$this->authorizeGoogleServiceWithApiKey($service);
		}
	}

	/**
	 * authorize google service with api key
	 */
	private function authorizeGoogleServiceWithApiKey($service){

		$service->setApiKey(UEGoogleAPIHelper::getApiKey());
	}

	/**
	 * has google credentials
	 */
	private function hasGoogleCredentials(){
		
		try{
			$token = UEGoogleAPIHelper::getFreshAccessToken();

			$hasCredentials = empty($token) === false;
		}catch(Exception $exception){
			$key = UEGoogleAPIHelper::getApiKey();

			$hasCredentials = empty($key) === false;
		}

		return $hasCredentials;
	}

	/**
	 * validate google api key
	 */
	private function validateGoogleApiKey(){

		$key = UEGoogleAPIHelper::getApiKey();

		if(empty($key) === true)
			UniteFunctionsUC::throwError(__("Google API key is missing.", "unlimited-elements-for-elementor"));
	}

	/**
	 * validate google credentials
	 */
	private function validateGoogleCredentials(){
		
		$hasCredentials = $this->hasGoogleCredentials();

		if($hasCredentials === false)
			UniteFunctionsUC::throwError(__("Google credentials are missing.", "unlimited-elements-for-elementor"));
	}

	/**
	 * get exchange rate api key
	 */
	private function getExchangeRateApiKey(){
		
		$key = $this->getRequiredParam(self::SETTINGS_EXCHANGE_RATE_API_KEY, "Exchange Rate API key");

		return $key;
	}

	/**
	 * get currency exchange settings fields
	 */
	private function getCurrencyExchangeSettingsFields(){

		$fields = array();

		$key = HelperProviderCoreUC_EL::getGeneralSetting(self::SETTINGS_EXCHANGE_RATE_API_KEY);

		$fields = $this->addEmptyApiKeyField($fields, $key, self::CURRENCY_EXCHANGE_FIELD_EMPTY_API_KEY, "Exchange Rate API");
		
		$fields = array_merge($fields, array(
			array(
				"id" => self::CURRENCY_EXCHANGE_FIELD_CURRENCY,
				"type" => UniteCreatorDialogParam::PARAM_TEXTFIELD,
				"text" => __("Currency Code", "unlimited-elements-for-elementor"),
				// translators: %s is page url
				"desc" => sprintf(__("Enter the three-letter <a href='%s' target='_blank'>currency code</a>.", "unlimited-elements-for-elementor"), "https://exchangerate-api.com/docs/supported-currencies"),
			),
			array(
				"id" => self::CURRENCY_EXCHANGE_FIELD_PRECISION,
				"type" => UniteCreatorDialogParam::PARAM_TEXTFIELD,
				"text" => __("Rate Precision", "unlimited-elements-for-elementor"),
				// translators: %1$d is a number, %2$d is a number, %3$d is a number
				"desc" => sprintf(__("Optional. You can specify the number of decimals for the rate: from %1\$d to %2\$d. The default value is %3\$d.", "unlimited-elements-for-elementor"), self::CURRENCY_EXCHANGE_MIN_PRECISION, self::CURRENCY_EXCHANGE_MAX_PRECISION, self::CURRENCY_EXCHANGE_DEFAULT_PRECISION),
			),
			array(
				"id" => self::CURRENCY_EXCHANGE_FIELD_INCLUDE_CURRENCIES,
				"type" => UniteCreatorDialogParam::PARAM_TEXTAREA,
				"text" => __("Include Currencies", "unlimited-elements-for-elementor"),
				"desc" => __("Optional. You can specify a comma separated list of currency codes to include, otherwise all currencies will be displayed.", "unlimited-elements-for-elementor"),
			),
			array(
				"id" => self::CURRENCY_EXCHANGE_FIELD_CACHE_TIME,
				"type" => UniteCreatorDialogParam::PARAM_TEXTFIELD,
				"text" => __("Cache Time", "unlimited-elements-for-elementor"),
				// translators: %d is a number
				"desc" => sprintf(__("Optional. You can specify the cache time of results in minutes. The default value is %d minutes.", "unlimited-elements-for-elementor"), self::CURRENCY_EXCHANGE_DEFAULT_CACHE_TIME),
				"default" => self::CURRENCY_EXCHANGE_DEFAULT_CACHE_TIME,
			),
		));

		return $fields;
	}

	/**
	 * get google events settings fields
	 */
	private function getGoogleEventsSettingsFields(){

		$fields = array();

		if(GlobalsUnlimitedElements::$enableGoogleCalendarScopes === true)
			$fields = $this->addGoogleEmptyCredentialsField($fields, self::GOOGLE_EVENTS_FIELD_EMPTY_CREDENTIALS);
		else
			$fields = $this->addGoogleEmptyApiKeyField($fields, self::GOOGLE_EVENTS_FIELD_EMPTY_CREDENTIALS);

		$fields = array_merge($fields, array(
			array(
				"id" => self::GOOGLE_EVENTS_FIELD_CALENDAR_ID,
				"type" => UniteCreatorDialogParam::PARAM_TEXTFIELD,
				"text" => __("Calendar ID", "unlimited-elements-for-elementor"),
				"desc" => __("You can find the calendar ID on a calendar's \"Settings\" page under \"Integrate Calendar\".", "unlimited-elements-for-elementor"),
			),
			array(
				"id" => self::GOOGLE_EVENTS_FIELD_RANGE,
				"type" => UniteCreatorDialogParam::PARAM_DROPDOWN,
				"text" => __("Date Range", "unlimited-elements-for-elementor"),
				"options" => array(
					self::GOOGLE_EVENTS_RANGE_UPCOMING => __("Upcoming", "unlimited-elements-for-elementor"),
					self::GOOGLE_EVENTS_RANGE_TODAY => __("Today's", "unlimited-elements-for-elementor"),
					self::GOOGLE_EVENTS_RANGE_TOMORROW => __("Tomorrow's", "unlimited-elements-for-elementor"),
					self::GOOGLE_EVENTS_RANGE_WEEK => __("This week", "unlimited-elements-for-elementor"),
					self::GOOGLE_EVENTS_RANGE_MONTH => __("This month", "unlimited-elements-for-elementor"),
				),
				"default" => self::GOOGLE_EVENTS_RANGE_UPCOMING,
			),
			array(
				"id" => self::GOOGLE_EVENTS_FIELD_ORDER,
				"type" => UniteCreatorDialogParam::PARAM_DROPDOWN,
				"text" => __("Order By", "unlimited-elements-for-elementor"),
				"options" => array(
					self::GOOGLE_EVENTS_ORDER_DATE_DESC => __("Date (newest)", "unlimited-elements-for-elementor"),
					self::GOOGLE_EVENTS_ORDER_DATE_ASC => __("Date (oldest)", "unlimited-elements-for-elementor"),
				),
				"default" => self::GOOGLE_EVENTS_ORDER_DATE_DESC,
			),
			array(
				"id" => self::GOOGLE_EVENTS_FIELD_LIMIT,
				"type" => UniteCreatorDialogParam::PARAM_TEXTFIELD,
				"text" => __("Events Limit", "unlimited-elements-for-elementor"),
				// translators: %d is a number
				"desc" => sprintf(__("Optional. You can specify the maximum number of events: from 1 to 2500. The default value is %d.", "unlimited-elements-for-elementor"), self::GOOGLE_EVENTS_DEFAULT_LIMIT),
				"default" => self::GOOGLE_EVENTS_DEFAULT_LIMIT,
			),
			array(
				"id" => self::GOOGLE_EVENTS_FIELD_REQUIRE_TITLE,
				"type" => UniteCreatorDialogParam::PARAM_RADIOBOOLEAN,
				"text" => __("Hide Events Without Title", "unlimited-elements-for-elementor"),
				"desc" => __("Skip events that have no title in the calendar.", "unlimited-elements-for-elementor"),
				"default" => true,
			),
			array(
				"id" => self::GOOGLE_EVENTS_FIELD_TIMEZONE,
				"type" => UniteCreatorDialogParam::PARAM_TEXTFIELD,
				"text" => __("Timezone", "unlimited-elements-for-elementor"),
				"desc" => __("Example: Europe/Rome, UTC, -06:30, +08:45. Leave empty for timezone set in wp settings", "unlimited-elements-for-elementor"),
			),
			array(
				"id" => self::GOOGLE_EVENTS_FIELD_CACHE_TIME,
				"type" => UniteCreatorDialogParam::PARAM_TEXTFIELD,
				"text" => __("Cache Time", "unlimited-elements-for-elementor"),
				// translators: %d is a number
				"desc" => sprintf(__("Optional. You can specify the cache time of results in minutes. The default value is %d minutes.", "unlimited-elements-for-elementor"), self::GOOGLE_EVENTS_DEFAULT_CACHE_TIME),
				"default" => self::GOOGLE_EVENTS_DEFAULT_CACHE_TIME,
			),
		));

		return $fields;
	}

	


	/**
	 * get youtube playlist settings fields
	 */
	private function getYoutubePlaylistSettingsFields(){

		$fields = array();

		if(GlobalsUnlimitedElements::$enableGoogleYoutubeScopes === true)
			$fields = $this->addGoogleEmptyCredentialsField($fields, self::YOUTUBE_PLAYLIST_FIELD_EMPTY_CREDENTIALS);
		else
			$fields = $this->addGoogleEmptyApiKeyField($fields, self::YOUTUBE_PLAYLIST_FIELD_EMPTY_CREDENTIALS);

		$fields = array_merge($fields, array(
			array(
				"id" => self::YOUTUBE_PLAYLIST_FIELD_ID,
				"type" => UniteCreatorDialogParam::PARAM_TEXTFIELD,
				"text" => __("Playlist ID", "unlimited-elements-for-elementor"),
				// translators: %1$s is page url, %2$s is page url
				"desc" => sprintf(__("You can find the playlist ID in a YouTube URL: <br />— %1\$s<br />— %2\$s", "unlimited-elements-for-elementor"), "https://youtube.com/playlist?list=<b>[YOUR_PLAYLIST_ID]</b>", "https://youtube.com/watch?v=aBC-123xYz&list=<b>[YOUR_PLAYLIST_ID]</b>"),
				"default" => "PLRz741VTawEo"
			),
			array(
				"id" => self::YOUTUBE_PLAYLIST_FIELD_ORDER,
				"type" => UniteCreatorDialogParam::PARAM_DROPDOWN,
				"text" => __("Order By", "unlimited-elements-for-elementor"),
				"options" => array(
					self::YOUTUBE_PLAYLIST_ORDER_DEFAULT => __("Default", "unlimited-elements-for-elementor"),
					self::YOUTUBE_PLAYLIST_ORDER_DATE_ADDED_DESC => __("Date added (newest)", "unlimited-elements-for-elementor"),
					self::YOUTUBE_PLAYLIST_ORDER_DATE_ADDED_ASC => __("Date added (oldest)", "unlimited-elements-for-elementor"),
					self::YOUTUBE_PLAYLIST_ORDER_DATE_ADDED_RANDOM => __("Date added (random)", "unlimited-elements-for-elementor"),
					self::YOUTUBE_PLAYLIST_ORDER_DATE_PUBLISHED_DESC => __("Date published (newest)", "unlimited-elements-for-elementor"),
					self::YOUTUBE_PLAYLIST_ORDER_DATE_PUBLISHED_ASC => __("Date published (oldest)", "unlimited-elements-for-elementor"),
					self::YOUTUBE_PLAYLIST_ORDER_DATE_PUBLISHED_RANDOM => __("Date published (random)", "unlimited-elements-for-elementor"),
				),
				"default" => self::YOUTUBE_PLAYLIST_ORDER_DEFAULT,
			),
			array(
				"id" => self::YOUTUBE_PLAYLIST_FIELD_LIMIT,
				"type" => UniteCreatorDialogParam::PARAM_TEXTFIELD,
				"text" => __("Videos Limit", "unlimited-elements-for-elementor"),
				// translators: %d is a number
				"desc" => sprintf(__("Optional. You can specify the maximum number of videos: from 1 to 50. The default value is %d.", "unlimited-elements-for-elementor"), self::YOUTUBE_PLAYLIST_DEFAULT_LIMIT),
				"default" => self::YOUTUBE_PLAYLIST_DEFAULT_LIMIT,
			),
			array(
				"id" => self::YOUTUBE_PLAYLIST_FIELD_CACHE_TIME,
				"type" => UniteCreatorDialogParam::PARAM_TEXTFIELD,
				"text" => __("Cache Time", "unlimited-elements-for-elementor"),
				// translators: %d is a number
				"desc" => sprintf(__("Optional. You can specify the cache time of results in minutes. The default value is %d minutes.", "unlimited-elements-for-elementor"), self::YOUTUBE_PLAYLIST_DEFAULT_CACHE_TIME),
				"default" => self::YOUTUBE_PLAYLIST_DEFAULT_CACHE_TIME,
			),
		));

		return $fields;
	}

	/**
	 * get currency exchange data
	 */
	private function getCurrencyExchangeData(){

		$data = array();

		$currency = $this->getRequiredParam(self::CURRENCY_EXCHANGE_FIELD_CURRENCY, "Currency");
		$precision = $this->getParam(self::CURRENCY_EXCHANGE_FIELD_PRECISION, self::CURRENCY_EXCHANGE_DEFAULT_PRECISION);
		$includeCurrencies = $this->getParam(self::CURRENCY_EXCHANGE_FIELD_INCLUDE_CURRENCIES, "");
		$cacheTime = $this->getCacheTimeParam(self::CURRENCY_EXCHANGE_FIELD_CACHE_TIME, self::CURRENCY_EXCHANGE_DEFAULT_CACHE_TIME);

		$precision = intval($precision);
		$precision = max($precision, self::CURRENCY_EXCHANGE_MIN_PRECISION);
		$precision = min($precision, self::CURRENCY_EXCHANGE_MAX_PRECISION);

		$includeCurrencies = strtoupper($includeCurrencies);
		$includeCurrencies = explode(",", $includeCurrencies);
		$includeCurrencies = array_map("trim", $includeCurrencies);
		$includeCurrencies = array_filter($includeCurrencies);
		$includeCurrencies = array_unique($includeCurrencies);

		$exchangeService = new UEExchangeRateAPIClient($this->getExchangeRateApiKey());
		$exchangeService->setCacheTime($cacheTime);

		$rates = $exchangeService->getRates($currency);

		foreach($rates as $rate){
			$code = $rate->getCode();
			$symbol = $rate->getSymbol();
			$formattedRate = $rate->getRate($precision);

			$data[$code] = array(
				"id" => $rate->getId(),
				"code" => $code,
				"name" => $rate->getName(),
				"symbol" => $symbol,
				"flag" => $rate->getFlagUrl(),
				"rate" => $formattedRate,
				"value_before" => "$symbol$formattedRate",
				"value_after" => "$formattedRate$symbol",
			);
		}

		$filteredData = array();

		if(empty($includeCurrencies) === true)
			$filteredData = UniteFunctionsUC::assocToArray($data);

		foreach($includeCurrencies as $code){
			$rate = UniteFunctionsUC::getVal($data, $code);

			if(empty($rate) === true)
				continue;

			$filteredData[] = $rate;
		}

		$baseCode = strtoupper($currency);
		$baseRate = UniteFunctionsUC::getVal($data, $baseCode);

		$data = UniteFunctionsUC::assocToArray($data);
		$filteredDataJson = UniteFunctionsUC::jsonEncodeForClientSide($filteredData);

		$output = array();
		$output["base"] = $baseRate;
		$output["rates_all"] = $data;
		$output["rates_chosen"] = $filteredData;
		$output["rates_chosen_json"] = $filteredDataJson;

		return $output;
	}

	/**
	 * get google events data
	 */
	private function getGoogleEventsData(){

		$data = array();

		if(GlobalsUnlimitedElements::$enableGoogleCalendarScopes === true)
			$this->validateGoogleCredentials();
		else
			$this->validateGoogleApiKey();
		

		$calendarId = $this->getRequiredParam(self::GOOGLE_EVENTS_FIELD_CALENDAR_ID, "Calendar ID");

		$eventsRange = $this->getParam(self::GOOGLE_EVENTS_FIELD_RANGE);
		$eventsRange = $this->getGoogleEventsDatesRange($eventsRange);
		$eventsOrder = $this->getParam(self::GOOGLE_EVENTS_FIELD_ORDER);
		$eventsLimit = $this->getParam(self::GOOGLE_EVENTS_FIELD_LIMIT, self::GOOGLE_EVENTS_DEFAULT_LIMIT);
		$eventsLimit = intval($eventsLimit);
		$cacheTime = $this->getCacheTimeParam(self::GOOGLE_EVENTS_FIELD_CACHE_TIME, self::GOOGLE_EVENTS_DEFAULT_CACHE_TIME);
		$timezone = $this->getParam(self::GOOGLE_EVENTS_FIELD_TIMEZONE);
		$requireTitle = $this->getParam(self::GOOGLE_EVENTS_FIELD_REQUIRE_TITLE, true);
		$requireTitle = UniteFunctionsUC::strToBool($requireTitle);

				
		$orderFieldMap = array(
			self::GOOGLE_EVENTS_ORDER_DATE_ASC => "date",
			self::GOOGLE_EVENTS_ORDER_DATE_DESC => "date",
		);

		$orderDirectionMap = array(
			self::GOOGLE_EVENTS_ORDER_DATE_ASC => self::ORDER_DIRECTION_ASC,
			self::GOOGLE_EVENTS_ORDER_DATE_DESC => self::ORDER_DIRECTION_DESC,
		);

		$orderField = isset($orderFieldMap[$eventsOrder]) ? $orderFieldMap[$eventsOrder] : null;
		$orderDirection = isset($orderDirectionMap[$eventsOrder]) ? $orderDirectionMap[$eventsOrder] : null;

		$calendarService = new UEGoogleAPICalendarService();
		$calendarService->setCacheTime($cacheTime);

		if(GlobalsUnlimitedElements::$enableGoogleCalendarScopes === true)
			$this->authorizeGoogleService($calendarService);
		else
			$this->authorizeGoogleServiceWithApiKey($calendarService);

		$eventsParams = array(
			"singleEvents" => "true",
			"orderBy" => "startTime",
			"maxResults" => $eventsLimit,
		);

		if(isset($eventsRange["start"]) === true)
			$eventsParams["timeMin"] = $eventsRange["start"];

		if(isset($eventsRange["end"]) === true)
			$eventsParams["timeMax"] = $eventsRange["end"];
					
		$events = $calendarService->getEvents($calendarId, $eventsParams, $timezone);
		
		foreach($events as $event){

			if($requireTitle === true && $event->getTitle() === "")
				continue;

			$orderValue = ($orderField === "date")
				? $event->getStartDate(self::FORMAT_MYSQL_DATETIME)
				: null;

			$startMysql = $event->getStartDate(self::FORMAT_MYSQL_DATETIME);
			$endMysql = $event->getEndDate(self::FORMAT_MYSQL_DATETIME);
			$startStamp = empty($startMysql) ? "" : strtotime($startMysql);
			$endStamp = empty($endMysql) ? "" : strtotime($endMysql);

			$data[] = array(
				"id" => $event->getId(),
				"start_date" => $event->getStartDate(self::FORMAT_DATETIME),
				"end_date" => $event->getEndDate(self::FORMAT_DATETIME),
				"start_date_stamp" => $startStamp,
				"end_date_stamp" => $endStamp,
				"start_time" => $event->getStartDate("H:i"),
				"end_time" => $event->getEndDate("H:i"),
				"title" => $event->getTitle(),
				"description" => $event->getDescription(true),
				"location" => $event->getLocation(),
				"link" => $event->getUrl(),
				self::ORDER_FIELD => $orderValue,
			);
		}

		$data = $this->sortData($data, $orderDirection);

		return array(
			"events" => $data,
		);
	}

	/**
	 * get google events dates range
	 */
	private function getGoogleEventsDatesRange($key){

		$currentTime = current_time("timestamp");
		$startTime = null;
		$endTime = null;

		switch($key){
			case self::GOOGLE_EVENTS_RANGE_UPCOMING:
				$startTime = strtotime("now", $currentTime);
			break;
			case self::GOOGLE_EVENTS_RANGE_TODAY:
				$startTime = strtotime("today", $currentTime);
				$endTime = strtotime("tomorrow", $startTime);
			break;
			case self::GOOGLE_EVENTS_RANGE_TOMORROW:
				$startTime = strtotime("tomorrow", $currentTime);
				$endTime = strtotime("tomorrow", $startTime);
			break;
			case self::GOOGLE_EVENTS_RANGE_WEEK:
				$startTime = strtotime("this week midnight", $currentTime);
				$endTime = strtotime("next week midnight", $currentTime);
			break;
			case self::GOOGLE_EVENTS_RANGE_MONTH:
				$startTime = strtotime("first day of this month midnight", $currentTime);
				$endTime = strtotime("first day of next month midnight", $currentTime);
			break;
		}

		$range = array(
			"start" => $startTime ? uelm_date("c", $startTime) : null,
			"end" => $endTime ? uelm_date("c", $endTime) : null,
		);

		return $range;
	}

	
	private function _________GOOGLE_REVIEWS_________(){}
	
	/**
	 * get serp api key
	 */
	private function getSerpAPIKey(){
		
		if(GlobalsUnlimitedElements::$enableSerpAPI == false)
			return(null);
		
		$serpKey = HelperProviderCoreUC_EL::getGeneralSetting("serpapi_key");
		$serpKey = trim($serpKey);
		
		return($serpKey);
	}
	
	/**
	 * is serp api enabled
	 */
	private function isGoogleReviewsSerpEnabled(){
						
		$apiKey = $this->getSerpAPIKey();
		
		if(!empty($apiKey))
			return(true);
			
		return(false);		
	}
	
	/**
	 * get official google reviews
	 */
	private function getGoogleReviewsData_official($placeId){
		
		$this->validateGoogleApiKey();
		
		$cacheTime = $this->getCacheTimeParam(self::GOOGLE_REVIEWS_FIELD_CACHE_TIME, self::GOOGLE_REVIEWS_DEFAULT_CACHE_TIME);
		
		$placesService = new UEGoogleAPIPlacesService();
		$placesService->setCacheTime($cacheTime);

		$this->authorizeGoogleServiceWithApiKey($placesService);
		
		$reviewsLanguage = $this->getParam(self::GOOGLE_REVIEWS_FIELD_LANG);
		
		$placeParams = array(
			"fields" => "reviews",
			"reviews_sort" => "newest",
			"lang" => $reviewsLanguage,
		);
		
		$place = $placesService->getDetails($placeId, $placeParams, $this->googleReviewsShowDebug);
		
		return($place);
	}
	
	/**
	 * get google reviews from serp service
	 */
	private function getGoogleReviewsData_serp($placeId){
		
		$placesService = new UEGoogleAPIPlacesService();

		$placeParams = array();
		
		$apiKey = $this->getSerpAPIKey();
		
		$reviewsLanguage = $this->getParam(self::GOOGLE_REVIEWS_FIELD_LANG);
		$placeParams["hl"] = $reviewsLanguage;
		
		$reviewsSortBy = $this->getParam(self::GOOGLE_REVIEWS_SORT_BY);
		$placeParams["sort_by"] = $reviewsSortBy;
		
		//get cache time option and convert to seconds
		$cacheTimeOption = $this->getParam(self::GOOGLE_REVIEWS_SERP_CACHE_TIME, "week");
		$cacheTime = self::GOOGLE_REVIEWS_CACHE_TIME_WEEK; // default: 1 week in seconds
		
		switch($cacheTimeOption){
			case "day":
				$cacheTime = self::GOOGLE_REVIEWS_CACHE_TIME_DAY;
				break;
			case "week":
				$cacheTime = self::GOOGLE_REVIEWS_CACHE_TIME_WEEK;
				break;
			case "month":
				$cacheTime = self::GOOGLE_REVIEWS_CACHE_TIME_MONTH;
				break;
		}
		
		$showDemoData = $this->getParam(self::GOOGLE_REVIEWS_SHOW_DEMO_DATA, false);

		$numRequests = (int)$this->getParam(self::GOOGLE_REVIEWS_SERP_NUM_REVIEWS, self::GOOGLE_REVIEWS_SERP_DEFAULT_NUM_REQUESTS);
		if($numRequests < 1)
			$numRequests = self::GOOGLE_REVIEWS_SERP_DEFAULT_NUM_REQUESTS;
		
		if($showDemoData == true && GlobalsProviderUC::$isInsideEditor == true){
			$place = $placesService->getDetailsDemo();
		}else{
			$place = $placesService->getDetailsSerp($placeId, $apiKey, $placeParams, $this->googleReviewsShowDebug, $cacheTime, $numRequests);
		}

		return($place);
	}
	
	
	/**
	 * get google reviews data
	 */
	private function getGoogleReviewsData(){
		
		$data = array();
		
		$showDebug = $this->getParam(self::GOOGLE_REVIEWS_FIELD_SHOW_DEBUG);
		
		if($showDebug == true)
			$this->googleReviewsShowDebug = true;
		
		try{
					
			$serpAPIKey = $this->getSerpAPIKey();
			
			$isSerp = !empty($serpAPIKey);
			
			if($this->googleReviewsShowDebug == true){
				
				$message = "Reviews API Debug";
				
				if($isSerp == true)
					$message .= "<br> Output google reviews data using serp.com API";
				else
					$message .= "<br> Output google reviews data using Official Google API";
				
				echo HelperHtmlUC::getDebugWarningMessageHtml($message);
			} 
		
			$placeId = $this->getRequiredParam(self::GOOGLE_REVIEWS_FIELD_PLACE_ID, "Place ID");
		
		
			if($isSerp == false)
				$place = $this->getGoogleReviewsData_official($placeId);
			else
				$place = $this->getGoogleReviewsData_serp($placeId);
		
			if(empty($place))
				return(array());
			
			
		//add api source
			
			$apiSourceText = ($isSerp == true)?"serp":"official";
			
			$data["reviews_api_type"] = $apiSourceText;
			
			//add place info
			
			$arrInfo = $place->getPlaceInfo();
			
			if(!empty($arrInfo))
				$data["place_info"] = $arrInfo;
			
			//add search link
			
			if($isSerp == true){
				
				$arrMetaData = $place->getMetaData();
				
				$data["api_search_link_for_debug"] = UniteFunctionsUC::getVal($arrMetaData, "json_endpoint");
				$data["api_search_html_link_for_debug"] = UniteFunctionsUC::getVal($arrMetaData, "prettify_html_file");
				
			}
		
		
			//add reviews
				
			$arrReviews = $place->getReviews();
			
			$arrReviewsOutput = array();
			
			foreach($arrReviews as $index=>$review){
				
				if($isSerp == true)
					$review->setSerpSource();

				// Skip rating-only reviews with no written text
				$textPlain = trim(strip_tags((string)$review->getText(false)));
				if($textPlain === "")
					continue;
				
				$arrReview = array(
					"id" => $review->getId(),
					"date" => $review->getDate(self::FORMAT_DATETIME),
					"time_ago" => $review->getTimeAgoText(),
					"text" => $review->getText(true),
					"rating" => $review->getRating(),
					"author_name" => $review->getAuthorName(),
					"author_photo" => $review->getAuthorPhotoUrl(),
				);
 
				$arrReviewsOutput[] = $arrReview;
			}
			
			$data["reviews"] = $arrReviewsOutput;
		
		}catch(Exception $e){
			
			if($this->googleReviewsShowDebug == true){
				
				$message = $e->getMessage();
				
				echo HelperHtmlUC::getErrorMessageHtml($message,"",true);
			}
			
			throw $e;
		}
		
		return $data;
	}
	
	
	/**
	 * get google reviews settings fields
	 */
	private function getGoogleReviewsSettingsFields($isSingle = false){
		
		$fields = array();

		$fields = $this->addGoogleEmptyApiKeyField($fields, self::GOOGLE_REVIEWS_FIELD_EMPTY_API_KEY);
		
		$fields[] = array(
				"id" => self::GOOGLE_REVIEWS_FIELD_PLACE_ID,
				"type" => UniteCreatorDialogParam::PARAM_TEXTFIELD,
				"text" => __("Place ID", "unlimited-elements-for-elementor"),
				// translators: %s is page url
				"desc" => sprintf(__("You can find the place ID by using <a href='%s' target='_blank'>Place ID Finder</a>.", "unlimited-elements-for-elementor"), "https://developers.google.com/maps/documentation/javascript/examples/places-placeid-finder"),
				"default"=>"ChIJmeeg4mbcQUcRIOHu3gDTJDs"
		);
		
		$isSerpEnabled = $this->isGoogleReviewsSerpEnabled();
		
		if($isSerpEnabled == false)
			$text = sprintf(__("To get more then 5 reviews, enter %s key in general settings", "unlimited-elements-for-elementor"), "<a href='https://serpapi.com' target='_blank'>serpapi.com</a>");
		else
			$text = sprintf(__("Fetching google reviews using %s service.", "unlimited-elements-for-elementor"), "<a href='https://serpapi.com' target='_blank'>serpapi.com</a>");
		
		//if there is no option - no need for text
		if(GlobalsUnlimitedElements::$enableSerpAPI == true){
			
			$fields[] = array(
					"id" => self::GOOGLE_REVIEWS_FIELD_API_TEXT,
					"type" => UniteCreatorDialogParam::PARAM_STATIC_TEXT,
					"text" => $text,
			);
		}
		
		//for serp api the cache is configurable
				
		if($isSerpEnabled == false){
			
			$fields[] = array(
					"id" => self::GOOGLE_REVIEWS_FIELD_CACHE_TIME,
					"type" => UniteCreatorDialogParam::PARAM_TEXTFIELD,
					"text" => __("Cache Time", "unlimited-elements-for-elementor"),
					// translators: %d is a number
					"desc" => sprintf(__("Optional. You can specify the cache time of results in minutes. The default value is %d minutes.", "unlimited-elements-for-elementor"), self::GOOGLE_REVIEWS_DEFAULT_CACHE_TIME),
					"default" => self::GOOGLE_REVIEWS_DEFAULT_CACHE_TIME,
				);
		}else{
			
			$fields[] = array(
				"id" => self::GOOGLE_REVIEWS_SERP_CACHE_TIME,
				"type" => UniteCreatorDialogParam::PARAM_DROPDOWN,
				"text" => __("Cache Time", "unlimited-elements-for-elementor"),
				"desc" => __("Select how often the reviews should be refreshed.", "unlimited-elements-for-elementor"),
				"options" => array(
					"day" => __("Once a day", "unlimited-elements-for-elementor"),
					"week" => __("Once a week", "unlimited-elements-for-elementor"),
					"month" => __("Once a month", "unlimited-elements-for-elementor"),
				),
				"default" => "week"
			);

			$fields[] = array(
				"id" => self::GOOGLE_REVIEWS_SHOW_DEMO_DATA,
				"type" => UniteCreatorDialogParam::PARAM_RADIOBOOLEAN,
				"text" => __("Show Demo Data in Editor", "unlimited-elements-for-elementor"),
				"desc" => __("Show demo data in editor to save serp credits.", "unlimited-elements-for-elementor"),
				"default" => "false"
			);
			
		}
		
		$fields[] = array(
				"id" => self::GOOGLE_REVIEWS_FIELD_LANG,
				"type" => UniteCreatorDialogParam::PARAM_TEXTFIELD,
				"text" => __("Language Code", "unlimited-elements-for-elementor"),
				"desc" => __("Optional. Specify a language code. Example: de and google will translate the review.", "unlimited-elements-for-elementor"),
				"default" => "",
			);
		
		if($isSerpEnabled == true){
			
			$fields[] = array(
				"id" => self::GOOGLE_REVIEWS_SORT_BY,
				"type" => UniteCreatorDialogParam::PARAM_DROPDOWN,
				"text" => __("Sort By", "unlimited-elements-for-elementor"),
				"options" => array(
					"qualityScore"=>"Most Relevant",
					"newestFirst"=>"Newest",
					"ratingHigh"=>"Highest Rating",
					"ratingLow"=>"Lowest Rating",
				),
				"default" => "qualityScore"
			);

			$fields[] = array(
				"id" => self::GOOGLE_REVIEWS_SERP_NUM_REVIEWS,
				"type" => UniteCreatorDialogParam::PARAM_NUMBER,
				"text" => __("Number of API Requests", "unlimited-elements-for-elementor"),
				// translators: %d is a number
				"desc" => sprintf(__("Not the number of reviews. This is how many Serp API calls to make. The first request returns about 8 reviews; each extra request loads up to 20 more. Default is %d (~28 reviews), maximum is 5. Use the widget Max Reviews Number to limit how many are shown. Reviews without text are skipped. Each request uses Serp API quota.", "unlimited-elements-for-elementor"), self::GOOGLE_REVIEWS_SERP_DEFAULT_NUM_REQUESTS),
				"default" => self::GOOGLE_REVIEWS_SERP_DEFAULT_NUM_REQUESTS,
			);
		}
		

		//add fields for single mode
		
		if($isSingle == true){
			
			$fields[] = array(
				"id" => "google_reviews_hr_beore_debug",
				"type" => UniteCreatorDialogParam::PARAM_HR,
			);
			
			$fields[] = array(
				"id" => self::GOOGLE_REVIEWS_FIELD_SHOW_DEBUG,
				"type" => UniteCreatorDialogParam::PARAM_RADIOBOOLEAN,
				"text" => __("Show Debug", "unlimited-elements-for-elementor"),
			);
			
		}
			

		return $fields;
	}
	
	
	private function _________GOOGLE_SHEETS_________(){}
	
	
	/**
	 * get google sheets settings fields
	 */
	public function getGoogleSheetsSettingsFields(){

		$fields = array();

		$fields = $this->addGoogleEmptyCredentialsField($fields, self::GOOGLE_SHEETS_FIELD_EMPTY_CREDENTIALS);
		
		$fields = array_merge($fields, array(
			array(
				"id" => self::GOOGLE_SHEETS_FIELD_ID,
				"type" => UniteCreatorDialogParam::PARAM_TEXTFIELD,
				"text" => __("Spreadsheet ID", "unlimited-elements-for-elementor"),
				// translators: %s is a string
				"desc" => sprintf(__("You can find the spreadsheet ID in a Google Sheets URL: %s", "unlimited-elements-for-elementor"), "https://docs.google.com/spreadsheets/d/<b>[YOUR_SPREADSHEET_ID]</b>/edit#gid=0"),
			),
			array(
				"id" => self::GOOGLE_SHEETS_FIELD_SHEET_ID,
				"type" => UniteCreatorDialogParam::PARAM_TEXTFIELD,
				"text" => __("Sheet ID", "unlimited-elements-for-elementor"),
				// translators: %s is page url
				"desc" => sprintf(__("Optional. You can find the sheet ID in a Google Sheets URL: %s", "unlimited-elements-for-elementor"), "https://docs.google.com/spreadsheets/d/aBC-123_xYz/edit#gid=<b>[YOUR_SHEET_ID]</b>"),
			),
			array(
				"id" => self::GOOGLE_SHEETS_FIELD_CACHE_TIME,
				"type" => UniteCreatorDialogParam::PARAM_TEXTFIELD,
				"text" => __("Cache Time", "unlimited-elements-for-elementor"),
				// translators: %d is a number
				"desc" => sprintf(__("Optional. You can specify the cache time of results in minutes. The default value is %d minutes.", "unlimited-elements-for-elementor"), self::GOOGLE_SHEETS_DEFAULT_CACHE_TIME),
				"default" => self::GOOGLE_SHEETS_DEFAULT_CACHE_TIME,
			),
			array(
				"id" => self::GOOGLE_SHEETS_EXCLUDE_FIRST_ROW,
				"type" => UniteCreatorDialogParam::PARAM_RADIOBOOLEAN,
				"text" => __("Exclude First Row", "unlimited-elements-for-elementor"),
				// translators: %s is a string
				"desc" => __("You can exclude first row from Google Sheet Table", "unlimited-elements-for-elementor"),
			),
		));

		return $fields;
	}
	
	
	/**
	 * get google sheets data
	 */
	public function getGoogleSheetsData($params = null, $name = null){
		
		if(!empty($params))
			$this->params = $params;
		
		$data = array();
		
		$this->validateGoogleCredentials();
				
		$spreadsheetId = $this->getRequiredParam(self::GOOGLE_SHEETS_FIELD_ID, "Spreadsheet ID", $name);

		$spreadsheetId = $this->getSpreadsheetIdByUrl($spreadsheetId);

		$sheetId = $this->getParam(self::GOOGLE_SHEETS_FIELD_SHEET_ID, 0, $name);
		$sheetId = $this->getSheetIdByUrl($sheetId);
		$sheetId = intval($sheetId);

		$cacheTime = $this->getCacheTimeParam(self::GOOGLE_SHEETS_FIELD_CACHE_TIME, self::GOOGLE_SHEETS_DEFAULT_CACHE_TIME,$name);
	
		$sheetsService = new UEGoogleAPISheetsService();
		$sheetsService->setCacheTime($cacheTime);

		$this->authorizeGoogleService($sheetsService);

		// get sheet title for the range
		$spreadsheet = $sheetsService->getSpreadsheet($spreadsheetId);

		$range = null;

		foreach($spreadsheet->getSheets() as $sheet){
			if($sheet->getId() === $sheetId){
				$range = $sheet->getTitle();
				break;
			}
		}


		// get spreadsheet values


		/*$spreadsheet = $sheetsService->getSpreadsheetValues($spreadsheetId, $range);
		$values = $spreadsheet->getValues();*/

		$spreadsheet = $sheetsService->getSpreadsheetValuesWithLinksAndAttributes($spreadsheetId, $range);
		$values = $spreadsheet->getValuesWithLinksAndAttributes();

		$headers = $values[0]; // extract first row as headers


		$excludeFirstRow = $this->getParam(self::GOOGLE_SHEETS_EXCLUDE_FIRST_ROW,0, $name);
		if($excludeFirstRow == true)
			unset($values[0]);


		foreach($values as $rowIndex => $row){
			$attributes = array("id" => $rowIndex + 1);

			foreach($headers as $columnIndex => $header){
				if(empty($row[$columnIndex]))
					$attributes[$header] = ' ';
				else
					$attributes[$header] = $row[$columnIndex];
			}

			$data[] = $attributes;
		}


		return $data;
	}

	
	/**
	 * get spreadsheetId by url
	 */
	private function getSpreadsheetIdByUrl($spreadsheetId) {
		$pattern = '~spreadsheets/d/([^/]+)~';

		if (preg_match($pattern, $spreadsheetId, $matches)) {
			$spreadsheetId = $matches[1];
			return $spreadsheetId;
		}

		return $spreadsheetId;
	}


	/**
	 * get sheetId by url
	 */
	private function getSheetIdByUrl($sheetId) {
		$pattern = '/#gid=([^&]+)/';

		if (preg_match($pattern, $sheetId, $matches)) {
			$sheetId = $matches[1];
			return $sheetId;
		}

		return $sheetId;
	}

	private function _________WEATHER_________(){}

	/**
	 * get open weather api key
	 */
	private function getOpenWeatherApiKey(){

		$key = $this->getRequiredParam(self::SETTINGS_OPEN_WEATHER_API_KEY, "OpenWeather API key");

		return $key;
	}
	
	
	/**
	 * get weather forecast settings fields
	 */
	private function getWeatherForecastSettingsFields(){

		$fields = array();

		$key = HelperProviderCoreUC_EL::getGeneralSetting(self::SETTINGS_OPEN_WEATHER_API_KEY);

		$fields = $this->addEmptyApiKeyField($fields, $key, self::WEATHER_FORECAST_FIELD_EMPTY_API_KEY, "OpenWeather API");

		$fields = array_merge($fields, array(
			array(
				"id" => self::WEATHER_FORECAST_FIELD_COUNTRY,
				"type" => UniteCreatorDialogParam::PARAM_TEXTFIELD,
				"text" => __("Country Code", "unlimited-elements-for-elementor"),
				// translators: %s is page url
				"desc" => sprintf(__("Specify the two-letter <a href='%s' target='_blank'>country code</a>.", "unlimited-elements-for-elementor"), "https://en.wikipedia.org/wiki/ISO_3166-2#Current_codes"),
				"default" => "GB",
			),
			array(
				"id" => self::WEATHER_FORECAST_FIELD_CITY,
				"type" => UniteCreatorDialogParam::PARAM_TEXTFIELD,
				"text" => __("City Name", "unlimited-elements-for-elementor"),
				"default" => "London",
			),
			array(
				"id" => self::WEATHER_FORECAST_FIELD_UNITS,
				"type" => UniteCreatorDialogParam::PARAM_DROPDOWN,
				"text" => __("Units", "unlimited-elements-for-elementor"),
				"options" => array(
					self::WEATHER_FORECAST_UNITS_METRIC => __("Metric", "unlimited-elements-for-elementor"),
					self::WEATHER_FORECAST_UNITS_IMPERIAL => __("Imperial", "unlimited-elements-for-elementor"),
				),
				"default" => self::WEATHER_FORECAST_UNITS_METRIC,
			),
			array(
				"id" => self::WEATHER_FORECAST_FIELD_LOCALE,
				"type" => UniteCreatorDialogParam::PARAM_TEXTFIELD,
				"text" => __("Language Code", "unlimited-elements-for-elementor"),
				"default" => "",
				"desc" => __("2 digits of language code. for example: 'fr' (french) for the list of all codes: <a href='https://en.wikipedia.org/wiki/List_of_ISO_639_language_codes'>Press Here</a>. Leave empty for auto select. ", "unlimited-elements-for-elementor"),
			),
			
			array(
				"id" => self::WEATHER_FORECAST_FIELD_CACHE_TIME,
				"type" => UniteCreatorDialogParam::PARAM_TEXTFIELD,
				"text" => __("Cache Time", "unlimited-elements-for-elementor"),
				// translators: %d is a number
				"desc" => sprintf(__("Optional. You can specify the cache time of results in minutes. The default value is %d minutes.", "unlimited-elements-for-elementor"), self::CURRENCY_EXCHANGE_DEFAULT_CACHE_TIME),
				"default" => self::WEATHER_FORECAST_DEFAULT_CACHE_TIME,
			),
		));

		return $fields;
	}

	/**
	 * get weather forecast data
	 */
	private function getWeatherForecastData(){

		$country = $this->getRequiredParam(self::WEATHER_FORECAST_FIELD_COUNTRY, "Country");
		$city = $this->getRequiredParam(self::WEATHER_FORECAST_FIELD_CITY, "City");
		$units = $this->getRequiredParam(self::WEATHER_FORECAST_FIELD_UNITS, "Units");
		$cacheTime = $this->getCacheTimeParam(self::WEATHER_FORECAST_FIELD_CACHE_TIME, self::WEATHER_FORECAST_DEFAULT_CACHE_TIME);
		$locale = $this->getParam(self::WEATHER_FORECAST_FIELD_LOCALE, "");
		
		$weatherService = new UEOpenWeatherAPIClient($this->getOpenWeatherApiKey());
		$weatherService->setCacheTime($cacheTime);
		
		$forecasts = $weatherService->getForecasts($country, $city, $units, $locale);
		
		$currentForecast = UniteFunctionsUC::getVal($forecasts, "current");
		$hourlyForecasts = UniteFunctionsUC::getVal($forecasts, "hourly");
		$dailyForecasts = UniteFunctionsUC::getVal($forecasts, "daily");
		
		$data = array(
			"current" => $this->getWeatherForecastCurrentItem($currentForecast),
			"hourly" => $this->getWeatherForecastHourlyItems($hourlyForecasts),
			"daily" => $this->getWeatherForecastDailyItems($dailyForecasts),
		);
		
		
		return $data;
	}
	
	
	/**
	 * get weather forecasts basic item
	 */
	private function getWeatherForecastBasicItem($forecast){
		
		$item = array(
			"id" => $forecast->getId(),
			"date" => $forecast->getDate(self::FORMAT_MYSQL_DATETIME),
			"dow_full" => $forecast->getDate("l"),
			"dow_short" => $forecast->getDate("D"),
			"state" => $forecast->getState(),
			"description" => $forecast->getDescription(),
			"icon_name" => $forecast->getIconName(),
			"icon_url" => $forecast->getIconUrl(),
			"wind_speed" => $forecast->getWindSpeed(),
			"wind_degree" => $forecast->getWindDegrees(),
			"wind_gust" => $forecast->getWindGust(),
			"pressure" => $forecast->getPressure(),
			"humidity" => $forecast->getHumidity(),
			"cloudiness" => $forecast->getCloudiness(),
			"rain" => $forecast->getRain(),
			"snow" => $forecast->getSnow(),
			"uvi" => $forecast->getUvi(),
		);
		
		return $item;
	}

	/**
	 * get weather forecasts sun time item
	 */
	private function getWeatherForecastSunTimeItem($forecast){

		$item = array(
			"sunrise" => $forecast->getSunrise(),
			"sunset" => $forecast->getSunset(),
		);

		return $item;
	}

	/**
	 * get weather forecasts inline temperature item
	 */
	private function getWeatherForecastInlineTemperatureItem($forecast){

		$item = array(
			"temp" => $forecast->getTemperature(),
			"feels_like" => $forecast->getFeelsLike(),
		);

		return $item;
	}

	/**
	 * get weather forecast current item
	 */
	private function getWeatherForecastCurrentItem($forecast){

		$item = array_merge(
			$this->getWeatherForecastBasicItem($forecast),
			$this->getWeatherForecastSunTimeItem($forecast),
			$this->getWeatherForecastInlineTemperatureItem($forecast)
		);

		return $item;
	}

	/**
	 * get weather forecasts hourly items
	 */
	private function getWeatherForecastHourlyItems($forecasts){

		$items = array();

		foreach($forecasts as $forecast){
			$extra = array(
				"date_hours" => $forecast->getDate("H:00"),
			);

			$items[] = array_merge(
				$this->getWeatherForecastBasicItem($forecast),
				$this->getWeatherForecastInlineTemperatureItem($forecast),
				$extra
			);
		}

		return $items;
	}

	/**
	 * get weather forecasts daily items
	 */
	private function getWeatherForecastDailyItems($forecasts){

		$items = array();

		foreach($forecasts as $forecast){
			$items[] = array_merge(
				$this->getWeatherForecastBasicItem($forecast),
				$this->getWeatherForecastSunTimeItem($forecast),
				array(
					"temp_min" => $forecast->getMinTemperature(),
					"temp_max" => $forecast->getMaxTemperature(),
					"temp_morning" => $forecast->getMorningTemperature(),
					"temp_day" => $forecast->getDayTemperature(),
					"temp_evening" => $forecast->getEveningTemperature(),
					"temp_night" => $forecast->getNightTemperature(),
					"feels_like_morning" => $forecast->getMorningFeelsLike(),
					"feels_like_day" => $forecast->getDayFeelsLike(),
					"feels_like_evening" => $forecast->getEveningFeelsLike(),
					"feels_like_night" => $forecast->getNightFeelsLike(),
				)
			);
		}

		return $items;
	}
	
	
    private function _________OTHERS_________(){}
    
	/**
	 * get youtube playlist data
	 */
	private function getYoutubePlaylistData(){

		$data = array();

		if(GlobalsUnlimitedElements::$enableGoogleYoutubeScopes === true)
			$this->validateGoogleCredentials();
		else
			$this->validateGoogleApiKey();

		$playlistId = $this->getRequiredParam(self::YOUTUBE_PLAYLIST_FIELD_ID, "Playlist ID");
		$itemsOrder = $this->getParam(self::YOUTUBE_PLAYLIST_FIELD_ORDER);
		$itemsLimit = $this->getParam(self::YOUTUBE_PLAYLIST_FIELD_LIMIT, self::YOUTUBE_PLAYLIST_DEFAULT_LIMIT);
		$itemsLimit = intval($itemsLimit);
		$cacheTime = $this->getCacheTimeParam(self::YOUTUBE_PLAYLIST_FIELD_CACHE_TIME, self::YOUTUBE_PLAYLIST_DEFAULT_CACHE_TIME);

		$orderFieldMap = array(
			self::YOUTUBE_PLAYLIST_ORDER_DATE_ADDED_ASC => "date",
			self::YOUTUBE_PLAYLIST_ORDER_DATE_ADDED_DESC => "date",
			self::YOUTUBE_PLAYLIST_ORDER_DATE_ADDED_RANDOM => "date",
			self::YOUTUBE_PLAYLIST_ORDER_DATE_PUBLISHED_ASC => "video_date",
			self::YOUTUBE_PLAYLIST_ORDER_DATE_PUBLISHED_DESC => "video_date",
			self::YOUTUBE_PLAYLIST_ORDER_DATE_PUBLISHED_RANDOM => "video_date",
		);

		$orderDirectionMap = array(
			self::YOUTUBE_PLAYLIST_ORDER_DATE_ADDED_ASC => self::ORDER_DIRECTION_ASC,
			self::YOUTUBE_PLAYLIST_ORDER_DATE_ADDED_DESC => self::ORDER_DIRECTION_DESC,
			self::YOUTUBE_PLAYLIST_ORDER_DATE_ADDED_RANDOM => self::ORDER_DIRECTION_RANDOM,
			self::YOUTUBE_PLAYLIST_ORDER_DATE_PUBLISHED_ASC => self::ORDER_DIRECTION_ASC,
			self::YOUTUBE_PLAYLIST_ORDER_DATE_PUBLISHED_DESC => self::ORDER_DIRECTION_DESC,
			self::YOUTUBE_PLAYLIST_ORDER_DATE_PUBLISHED_RANDOM => self::ORDER_DIRECTION_RANDOM,
		);

		$orderField = isset($orderFieldMap[$itemsOrder]) ? $orderFieldMap[$itemsOrder] : null;
		$orderDirection = isset($orderDirectionMap[$itemsOrder]) ? $orderDirectionMap[$itemsOrder] : null;

		$youtubeService = new UEGoogleAPIYouTubeService();
		$youtubeService->setCacheTime($cacheTime);

		if(GlobalsUnlimitedElements::$enableGoogleYoutubeScopes === true)
			$this->authorizeGoogleService($youtubeService);
		else
			$this->authorizeGoogleServiceWithApiKey($youtubeService);

		$items = $youtubeService->getPlaylistItems($playlistId, array("maxResults" => $itemsLimit));

		foreach($items as $item){
			$orderValue = ($orderField === "date")
				? $item->getDate(self::FORMAT_MYSQL_DATETIME)
				: $item->getVideoDate(self::FORMAT_MYSQL_DATETIME);

			$data[] = array(
				"id" => $item->getId(),
				"date" => $item->getDate(self::FORMAT_DATETIME),
				"title" => $item->getTitle(),
				"description" => $item->getDescription(true),
				"image" => $item->getImageUrl(UEGoogleAPIPlaylistItem::IMAGE_SIZE_MAX),
				"video_id" => $item->getVideoId(),
				"video_date" => $item->getVideoDate(self::FORMAT_DATETIME),
				"video_link" => $item->getVideoUrl(),
				self::ORDER_FIELD => $orderValue,
			);
		}

		$data = $this->sortData($data, $orderDirection);

		return array(
			"videos" => $data,
		);
	}

	/**
	 * sort the data
	 */
	private function sortData($data, $direction){

		$field = self::ORDER_FIELD;

		usort($data, function($a, $b) use ($field, $direction){

			if(isset($a[$field]) === false || isset($b[$field]) === false)
				return 0;

			if($a[$field] == $b[$field])
				return 0;

			switch($direction){
				case self::ORDER_DIRECTION_RANDOM:
					// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
					$results = array(mt_rand(-1, 1), mt_rand(-1, 1));
				break;
				case self::ORDER_DIRECTION_DESC:
					$results = array(1, -1);
				break;
				default: // asc
					$results = array(-1, 1);
				break;
			}

			if(is_numeric($a[$field]) && is_numeric($b[$field]))
				return ($a[$field] < $b[$field]) ? $results[0] : $results[1];

			return (strcmp($a[$field], $b[$field]) <= 0) ? $results[0] : $results[1];
		});

		foreach($data as &$values){
			unset($values[$field]);
		}

		return $data;
	}

	/**
	 * add empty api key field
	 */
	private function addEmptyApiKeyField($fields, $key, $id, $name){

		if(empty($key) === true){
			$fields[] = array(
				"id" => $id,
				"type" => UniteCreatorDialogParam::PARAM_STATIC_TEXT,
				// translators: %s is key name
				"text" => sprintf(__("%s key is missing. Please add the key in the \"General Settings > Integrations\".", "unlimited-elements-for-elementor"), $name),
			);
		}

		return $fields;
	}

	/**
	 * add google empty api key field
	 */
	private function addGoogleEmptyApiKeyField($fields, $id){

		$key = UEGoogleAPIHelper::getApiKey();

		$fields = $this->addEmptyApiKeyField($fields, $key, $id, "Google API");

		return $fields;
	}

	/**
	 * add google empty credentials field
	 */
	private function addGoogleEmptyCredentialsField($fields, $id){
		
		$hasCredentials = $this->hasGoogleCredentials();

		if($hasCredentials === false){
			$fields[] = array(
				"id" => $id,
				"type" => UniteCreatorDialogParam::PARAM_STATIC_TEXT,
				"text" => __("Google credentials are missing. Please connect to Google or add an API key in the \"General Settings > Integrations\".", "unlimited-elements-for-elementor"),
			);
		}

		return $fields;
	}
	
	/**
	 * output google reviews refresh button
	 */
	private function outputGoogleReviewsRefreshButton(){
		
		// Check if user is admin
		if(!current_user_can('manage_options'))
			return;
		
		$placeId = $this->getParam(self::GOOGLE_REVIEWS_FIELD_PLACE_ID);
		
		if(empty($placeId))
			return;
		
		$ajaxUrl = admin_url('admin-ajax.php');
		$nonce = wp_create_nonce('uc_refresh_google_reviews');
		$widgetID = "uc_google_reviews_" . md5($placeId);
		
		$html = '<div class="uc-google-reviews-refresh-wrapper" style="margin: 10px 0; padding: 10px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">';
		$html .= '<button type="button" class="uc-google-reviews-refresh-btn" ';
		$html .= 'data-widget-id="' . esc_attr($widgetID) . '" ';
		$html .= 'data-place-id="' . esc_attr($placeId) . '" ';
		$html .= 'data-nonce="' . esc_attr($nonce) . '" ';
		$html .= 'data-ajax-url="' . esc_attr($ajaxUrl) . '" ';
		$html .= 'style="padding: 8px 16px; background: #0073aa; color: #fff; border: none; border-radius: 3px; cursor: pointer; font-size: 14px;">';
		$html .= __('Manual Refresh Reviews', 'unlimited-elements-for-elementor');
		$html .= '</button>';
		$html .= '<span class="uc-google-reviews-refresh-status" style="margin-left: 10px; display: none;"></span>';
		$html .= '</div>';
		
		echo $html;
	}

}
