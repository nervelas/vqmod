<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved.
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
if ( ! defined( 'ABSPATH' ) ) exit;

class UCOperations extends UniteElementsBaseUC{

	private static $arrGeneralSettings = null;
	private static $arrLayoutsGeneralSettings = null;
	private static $arrCustomSettingsCache = array();
	private static $arrUrlThumbCache = array();
	private static $lastRequestResponse = null;
	
	const GENERAL_SETTINGS_OPTION = "unitecreator_general_settings";

	private function a_______GENERAL_SETTING________(){
	}


	/**
	 * get general settings values based on custom object
	 */
	public function getGeneralSettingsCustomObject($filepathSettings){

		$arrValues = UniteProviderFunctionsUC::getOption(self::GENERAL_SETTINGS_OPTION);

		$objSettings = new UniteCreatorSettings();
		$objSettings->loadXMLFile($filepathSettings);

		if(!empty($arrValues))
			$objSettings->setStoredValues($arrValues);

		return ($arrValues);
	}

	/**
	 * get general settings key
	 */
	private function getGeneralSettingsKey($key){

		if($key == self::GENERAL_SETTINGS_OPTION)
			return ($key);

		if($key == "general_settings")
			return (self::GENERAL_SETTINGS_OPTION);

		$key = "unite_creator_" . $key;

		return ($key);
	}


	/**
	 * update general settings
	 */
	public function updateGeneralSettingsFromData($data, $isValidate = true, $settingsKey = self::GENERAL_SETTINGS_OPTION){

		$arrValues = UniteFunctionsUC::getVal($data, "settings_values");

		//validations:
		if($isValidate == true)
			UniteProviderFunctionsUC::doAction(UniteCreatorFilters::ACTION_VALIDATE_GENERAL_SETTINGS, $arrValues, $settingsKey);

		$arrCurrentSettings = UniteProviderFunctionsUC::getOption($settingsKey);
		if(empty($arrCurrentSettings))
			$arrCurrentSettings = array();

		if(empty($arrValues))
			$arrValues = array();

		$arrValues = array_merge($arrCurrentSettings, $arrValues);

		//clear cache
		if(isset(self::$arrCustomSettingsCache[$settingsKey]))
			unset(self::$arrCustomSettingsCache[$settingsKey]);

		UniteProviderFunctionsUC::updateOption($settingsKey, $arrValues);
	}

	/**
	 * validate that there are no keys in custom settings from general
	 */
	private function validateNoGeneralSettingsKeysInCustomSettings($arrValues){

		if(is_array($arrValues) == false)
			return (false);

		$arrSettings = $this->getGeneralSettings();
		if(is_array($arrSettings) == false)
			return (false);

		$arrIntersect = array_intersect_key($arrSettings, $arrValues);
		if(empty($arrIntersect))
			return (false);

		//----- invalid:

		$strIntersect = print_r($arrIntersect, true);
		UniteFunctionsUC::throwError("The custom settings should not contain general settings keys:" . $strIntersect);
	}

	/**
	 * update unlimited settings
	 */
	public function updateUnlimitedElementsGeneralSettings($arrValues){

		$key = "unlimited_elements_general_settings";
		$customSettingsKey = self::getGeneralSettingsKey($key);

		$data = array();
		$data["settings_values"] = $arrValues;
		$this->updateGeneralSettingsFromData($data, false, $customSettingsKey);
	}

	/**
	 * update custom settings from data
	 */
	public function updateCustomSettingsFromData($data){

		$arrValues = UniteFunctionsUC::getVal($data, "settings_values");
		$key = UniteFunctionsUC::getVal($data, "settings_key");

		//update general settings
		if($key == "general_settings"){
			$this->validateNoGeneralSettingsKeysInCustomSettings($arrValues);
			$this->updateGeneralSettingsFromData($data, false);
		}else{
			$customSettingsKey = self::getGeneralSettingsKey($key);

			$this->updateGeneralSettingsFromData($data, false, $customSettingsKey);
		}
	}


	private function a_________CUSTOM_SETTINGS___________(){
	}

	/**
	 * get raw values from general settings
	 */
	public function getCustomSettingsValues($customSettingsKey){

		$settingsKey = self::getGeneralSettingsKey($customSettingsKey);

		$arrValues = UniteProviderFunctionsUC::getOption($settingsKey);

		return ($arrValues);
	}

	/**
	 * get raw values from general settings
	 */
	public function getCustomSettingsObject($filepathSettings, $settingsKey){

		$objSettings = new UniteCreatorSettings();
		$objSettings->loadXMLFile($filepathSettings);

		$arrValues = $this->getCustomSettingsValues($settingsKey);

		if(!empty($arrValues))
			$objSettings->setStoredValues($arrValues);

		return ($objSettings);
	}

	/**
	 * get raw values from general settings
	 */
	public function getCustomSettingsObjectValues($filepathSettings, $settingsKey){
				
		if(isset(self::$arrCustomSettingsCache[$settingsKey]))
			return (self::$arrCustomSettingsCache[$settingsKey]);

		$objSettings = $this->getCustomSettingsObject($filepathSettings, $settingsKey);

		$arrValues = $objSettings->getArrValues();

		self::$arrCustomSettingsCache[$settingsKey] = $arrValues;

		return ($arrValues);
	}

	private function a__________OTHER_FUNCTIONS___________(){
	}

	/**
	 * check instagram update
	 */
	public function checkInstagramRenewToken(){

		try{
			//try to upgrade instagram if exists
			$objServices = new UniteServicesUC();
			$objServices->includeInstagramAPI();

			$isRenewed = HelperInstaUC::checkRenewAccessToken_onceInAWhile();
		}catch(Exception $e){
		}
	}

	/**
	 * get error message html
	 */
	public function getErrorMessageHtml($message, $trace = ""){

		$html = '<div class="unite-error-message">';
		$html .= '<div class="unite-error-message-inner">';
		$html .= $message;
		$html .= '</div>';

		if(!empty($trace)){
			$html .= '<div class="unite-error-trace">';
			$html .= "<pre>{$trace}</pre>";
			$html .= "</div>";
		}

		$html .= '</div>';

		return ($html);
	}

	/**
	 * put error mesage from the module
	 */
	public function putModuleErrorMessage($message, $trace = ""){
		uelm_echo(self::getErrorMessageHtml($message, $trace));
	}

	/**
	 * get thumb width from thumb size
	 */
	protected function getThumbWidthFromSize($sizeName){

		switch($sizeName){
			case GlobalsUC::THUMB_SIZE_NORMAL:
				$size = GlobalsUC::THUMB_WIDTH;
			break;
			case GlobalsUC::THUMB_SIZE_LARGE:
				$size = GlobalsUC::THUMB_WIDTH_LARGE;
			break;
			default:
				$size = GlobalsUC::THUMB_WIDTH;
			break;
		}

		return ($size);
	}

	/**
	 * create thumbs from image by url
	 * the image must be relative path to the platform base
	 */
	public function createThumbs($urlImage, $thumbSize = null){
		if(empty($urlImage))
			UniteFunctionsUC::throwError("empty image url");

		$thumbWidth = $this->getThumbWidthFromSize($thumbSize);

		$urlImage = HelperUC::URLtoRelative($urlImage);

		$info = HelperUC::getImageDetails($urlImage);

		//check thumbs path
		$pathThumbs = $info["path_thumbs"];

		if(!is_dir($pathThumbs))
			UniteFunctionsUC::mkdir($pathThumbs);

		if(!is_dir($pathThumbs))
			UniteFunctionsUC::throwError("Can't make thumb folder: {$pathThumbs}. Please check php and folder permissions");

		$filepathImage = $info["filepath"];
		
		try{
			$filenameThumb = $this->imageView->makeThumb($filepathImage, $pathThumbs, $thumbWidth);
		
		}catch(Exception $e){
			$filenameThumb = null;
		}
		
		$urlThumb = "";
		if(!empty($filenameThumb)){
			$urlThumbs = $info["url_dir_thumbs"];
			$urlThumb = $urlThumbs . $filenameThumb;
		}else
			$urlThumb = UniteFunctionsUC::getVal($info, "url_full");
		

		return ($urlThumb);
	}

	/**
	 * get title param array
	 */
	private function getParamTitle(){

		$arr = array();

		$arr["type"] = "uc_textfield";
		$arr["title"] = __("Title","unlimited-elements-for-elementor");
		$arr["name"] = "title";
		$arr["description"] = "";
		$arr["default_value"] = "";
		$arr["limited_edit"] = true;
		
		return ($arr);
	}

	/**
	 * check that params always have item param on top
	 */
	public function checkAddParamTitle($params){

		if(empty($params)){
			$paramTitle = $this->getParamTitle();
			$params[] = $paramTitle;

			return ($params);
		}

		//search for param title
		foreach($params as $param){
			$name = UniteFunctionsUC::getVal($param, "name");
			if($name == "title")
				return ($params);
		}

		//if no title param - add it to top
		$paramTitle = $this->getParamTitle();
		array_unshift($params, $paramTitle);

		return ($params);
	}

	/**
	 * get addon changelog from data
	 */
	public function getAddonChangelogFromData($data){

		require_once GlobalsUC::$pathViewsObjects . "addon_view.class.php";
		$objAddonView = new UniteCreatorAddonView();

		$response = $objAddonView->getChangelogContents($data);

		return ($response);
	}

	/**
	 * get addon revisions from data
	 */
	public function getAddonRevisionsFromData($data){

		require_once GlobalsUC::$pathViewsObjects . "addon_view.class.php";
		$objAddonView = new UniteCreatorAddonView();

		$response = $objAddonView->getRevisionsContents($data);

		return ($response);
	}

	/**
	 * get bulk addon dialog from data
	 */
	public function getAddonBulkDialogFromData($data){

		require_once GlobalsUC::$pathViewsObjects . "addon_view.class.php";
		$objAddonView = new UniteCreatorAddonView();

		$response = $objAddonView->getBulkDialogContents($data);

		return ($response);
	}

	/**
	 * get the cats
	 */
	private function getPostsFromTwig_getCats($strCats){

		if(empty($strCats))
			return (false);

		//get taxonomy

		$taxnonomy = "post";

		$arrData = explode("|", $strCats);

		if(count($arrData) == 2){
			$taxnonomy = $arrData[0];

			$strCats = $arrData[1];
		}

		$arrCats = explode(",", $strCats);

		$taxQuery = array();

		foreach($arrCats as $cat){
			$item = array();
			$item["taxonomy"] = $taxnonomy;
			$item["field"] = "name";
			$item["terms"] = $arrCats;

			$taxQuery[] = $item;
			$taxQuery["relation"] = "or";
		}

		return ($taxQuery);
	}

	/**
	 * get posts from twig
	 * simple function
	 */
	public function getPostsFromTwig($postType, $strCats, $strArgs){

		if(empty($postType))
			$postType = "post";

		$arrCats = $this->getPostsFromTwig_getCats($strCats);

		dmp("get posts");

		dmp($arrCats);
	}

	private function a____________DEBUG____________(){}
		
	/**
	 * modify field for debug
	 */
	public function modifyDebugField($field){

		if(is_string($field) == false)
			return ($field);

		$maxChars = 200;

		$field = trim($field);

		$numchars = strlen($field);

		$field = htmlspecialchars($field);

		if(strlen($field) < $maxChars)
			return ($field);

		//remove spaces
		$field = str_replace(" ", "", $field);
		$field = str_replace("\n", "", $field);

		if(strlen($field) > $maxChars)
			$field = substr($field, 0, $maxChars) . "... ($numchars chars)";

		return ($field);
	}

	/**
	 * put debug of post custom fields
	 */
	public function putPostCustomFieldsDebug($postID, $showCustomFields = false){

		if($postID == "current"){
			$post = get_post();
			$postID = $post->ID;
		}else
			$post = get_post($postID);

		if(empty($post))
			return (false);

		$postTitle = $post->post_title;

		if($showCustomFields == false)
			$arrCustomFields = UniteFunctionsWPUC::getPostMeta($postID);
		else{
			$arrCustomFields = UniteFunctionsWPUC::getPostCustomFields($postID, false);

			if(empty($arrCustomFields))
				$arrCustomFields = UniteFunctionsWPUC::getPostMeta($postID);
		}

		if(empty($arrCustomFields))
			$arrCustomFields = array();

		foreach($arrCustomFields as $key => $field){
			$arrCustomFields[$key] = $this->modifyDebugField($field);
		}

		$htmlFields = HelperHtmlUC::getHtmlArrayTable($arrCustomFields, "No Meta Fields Found");

		$fieldsTitle = "Meta";
		if($showCustomFields == true)
			$fieldsTitle = "Custom";
		
		uelm_echo("<br>{$fieldsTitle} fields for post: <b>$postTitle </b>, post id: $postID <br>");

		dmp($htmlFields);
	}

	/**
	 * put term custom fields - for debug
	 */
	public function putTermCustomFieldsDebug($term){

		if(empty($term))
			UniteFunctionsUC::throwError("print term debug: the termid option is empty");

		if(is_array($term)){
			$termID = UniteFunctionsUC::getVal($term, "id");
			$name = UniteFunctionsUC::getVal($term, "name");
		}else{
			$termID = $term->term_id;
			$name = $term->name;
		}

		$arrCustomFields = UniteFunctionsWPUC::getTermCustomFields($termID, false);

		foreach($arrCustomFields as $key => $field){
			$arrCustomFields[$key] = $this->modifyDebugField($field);
		}

		$htmlFields = HelperHtmlUC::getHtmlArrayTable($arrCustomFields, "No Meta Fields Found");

		$fieldsTitle = "Meta";

		uelm_echo("<br>{$fieldsTitle} fields for term: <b>$name </b>, term id: $termID <br>");

		dmp($htmlFields);
	}

	/**
	 * terms custom fields debug
	 */
	public function putTermsCustomFieldsDebug($arrTerms,$showCustomFields = false){

		if(empty($arrTerms))
			return (false);

		dmp("Show the terms meta fields. Please turn off this option before release.");

		foreach($arrTerms as $term){
			if(is_array($term))
				$termID = UniteFunctionsUC::getVal($term, "id");
			else
				$termID = $term->term_id;

			$this->putTermCustomFieldsDebug($term,$showCustomFields);
		}
	}

	/**
	 * put posts meta fields debug
	 */
	public function putPostsCustomFieldsDebug($arrPosts, $showCustomFields = false){
		
		if(empty($arrPosts))
			return (false);
		
		dmp("Show the posts meta fields. Please turn off this option before release.");

		foreach($arrPosts as $post){
			$postID = $post->ID;

			$this->putPostCustomFieldsDebug($postID, $showCustomFields);
		}
	}
	
	/**
	 * put posts meta fields debug
	 */
	public function putMenuCustomFieldsDebug($arrItems,$showCustomFields = false){

		if(empty($arrItems))
			return (false);

		dmp("Show the menu meta fields. Please turn off this option before release.");

		foreach($arrItems as $item){

			$menuItemID = UniteFunctionsUC::getVal($item, "id");

			$this->putPostCustomFieldsDebug($menuItemID, $showCustomFields);
		}
	}


	/**
	 * put custom fields array to debug
	 */
	public function putCustomFieldsArrayDebug($arrCustomFields, $title = null){

		if(!empty($title))
			dmp("$title custom fields debug. turn off before release");

		foreach($arrCustomFields as $key => $field){
			$arrCustomFields[$key] = $this->modifyDebugField($field);
		}

		$htmlFields = HelperHtmlUC::getHtmlArrayTable($arrCustomFields, "No Meta Fields Found");

		dmp($htmlFields);
	}
	
	/**
	 * put post terms debug
	 */
	public function putPostTermsDebug($postID){
		$strTerms = UniteFunctionsWPUC::getPostTermsTitlesString($postID, true);
		?>
		<div>Post Terms: <span style='color:green;'>"<?php echo esc_html($strTerms);?>"</span></div>
		<?php
	}
	
	/**
	 * put post object debug
	 */
	public function putPostObjectDebug($post){
		
		$post = (array)$post;
		
		$arrPostForShow = UniteFunctionsUC::modifyDataArrayForShow($post);
		
		dmp($arrPostForShow);
	}
	
	/**
	 * put posts full debug - show all info about each post
	 */
	public function putPostsFullDebug($arrPosts, $includePostObject = false){
		
		foreach($arrPosts as $post){
			$postID = $post->ID;
			
			$postTitle = $post->post_title;
			
			echo "<br><hr>";
			
			dmp("Post: <b>$postTitle</b>");
			
			if($includePostObject == true)
				$this->putPostObjectDebug($post, $includePostObject);
			
			$this->putPostCustomFieldsDebug($postID);
			$this->putPostTermsDebug($postID);
			
		}
		
		
	}
	
	private function a____________URL_CONTENTS____________(){
	}

	/**
	 * get local file contents
	 */
	public function getLocalFileContentsByUrl($url){

		$urlRelative = HelperUC::URLtoRelative($url);

		$isFile = $urlRelative != $url;

		if($isFile == false)
			return (null);

		$pathFile = HelperUC::urlToPath($url);

		if(empty($pathFile))
			return (null);

		$filetype = wp_check_filetype(basename($pathFile));
		$extIsImage = !empty($filetype["type"]) && strpos($filetype["type"], "image/") === 0;

		$mimeType = wp_get_image_mime($pathFile);
		if(empty($mimeType) && function_exists("mime_content_type"))
			$mimeType = mime_content_type($pathFile);

		$mimeIsImage = !empty($mimeType) && strpos($mimeType, "image/") === 0;

		if($extIsImage == false || $mimeIsImage == false)
			return (null);

		$content = UniteFunctionsUC::fileGetContents($pathFile);

		return ($content);
	}

	/**
	 * get url contents via HTTP with cache
	 *
	 * HTTP hooks: ue_http_pre_request, ue_http_response.
	 *
	 * @param mixed $httpContext Optional context passed to hooks (e.g. repeater / addon metadata).
	 */
	public function getUrlContents($url, $debug = false, $operateError = false, $httpContext = null){
		
		if($debug === true)
			dmp("get contents from url: $url");
		
		$url = UniteFunctionsUC::sanitize($url, UniteFunctionsUC::SANITIZE_URL_TRAVERSE);
		
		if(!empty($url))
			$url = UniteFunctionsUC::sanitize($url, UniteFunctionsUC::SANITIZE_URL);
		
		if(empty($url) && $debug == true){
			dmp("url don't pass the security");
			return(null);
		}

		try{
			$request = UEHttp::make();
			$request->debug($debug);
			$request->cacheTime(180); // 3 minutes

			UniteProviderFunctionsUC::doAction("ue_http_pre_request", $request, $url, $httpContext);

			$response = $request->get($url);
			
			//save last request
			self::$lastRequestResponse = $response;
			
			if($operateError){
				
				$responseError = $response->getError();
				
				if(!empty($responseError))
					UniteFunctionsUC::throwError($responseError);
				
			}
			
			$data = $response->body();

			$data = UniteProviderFunctionsUC::applyFilters("ue_http_response", $data, $url, $request, $response, $httpContext);
			
			return $data;
		}catch(Exception $e){
			
			if($operateError == true)
				throw($e);
		}

		return null;
	}
	
	/**
	 * get last request response
	 */
	public function getLastRequestResponse(){
		
		return self::$lastRequestResponse;
	}
	
	
	private function a____________DATE____________(){}

	/**
	 * get nice display of date ranges, ex. 4-5 MAR 2021
	 */
	public function getDateRangeString($startTimeStamp, $endTimeStamp, $pattern = null){

		$displayDate = "";

		$startDate = getDate($startTimeStamp);
		$endDate = getDate($endTimeStamp);

		//--- check same date

		if($startDate["year"] . $startDate["mon"] . $startDate["mday"] == $endDate["year"] . $endDate["mon"] . $endDate["mday"]){
			$displayDate = uelm_date('j M Y', $endTimeStamp);

			return ($displayDate);
		}

		//--- check different years

		if($startDate["year"] != $endDate["year"]){
			$displayDate = uelm_date('j M Y', $startTimeStamp) . " - " . uelm_date('j M Y', $endTimeStamp);

			return ($displayDate);
		}

		//--- check same year

		// diff days in the same month
		if($startDate["mon"] == $endDate["mon"])
			$displayDate = uelm_date('j', $startTimeStamp) . "-" . uelm_date('j M Y', $endTimeStamp);

		// diff months
		$displayDate = uelm_date('j M', $startTimeStamp) . " - " . uelm_date('j M Y', $endTimeStamp);

		return $displayDate;
	}


	/**
	 * get post list for select
	 */
	public function getPostListForSelectFromData($data, $addNotSelected = false){

		dmp("getPostListForSelect: function for overide");
		exit();
	}

	/**
	 * get post additions
	 */
	private function getPostAttributesFromData_getPostAdditions($data){

		$arrAdditions = array();

		$enableCustomFields = UniteFunctionsUC::getVal($data, "enable_custom_fields");
		$enableCustomFields = UniteFunctionsUC::strToBool($enableCustomFields);

		$enableCategory = UniteFunctionsUC::getVal($data, "enable_category");
		$enableCategory = UniteFunctionsUC::strToBool($enableCategory);

		$enableWoo = UniteFunctionsUC::getVal($data, "enable_woo");
		$enableWoo = UniteFunctionsUC::strToBool($enableWoo);

		if($enableCustomFields == true)
			$arrAdditions[] = GlobalsProviderUC::POST_ADDITION_CUSTOMFIELDS;

		if($enableCategory == true)
			$arrAdditions[] = GlobalsProviderUC::POST_ADDITION_CATEGORY;

		if($enableWoo == true)
			$arrAdditions[] = GlobalsProviderUC::POST_ADDITION_WOO;

		return ($arrAdditions);
	}

	/**
	 * get post list for select
	 */
	public function getPostAttributesFromData($data){

		$postID = UniteFunctionsUC::getVal($data, "postid");

		//UniteFunctionsUC::validateNotEmpty($postID, "post id");

		require_once GlobalsUC::$pathViewsObjects . "addon_view.class.php";
		require_once GlobalsUC::$pathProvider . "views/addon.php";

		$objAddonView = new UniteCreatorAddonViewProvider();

		$arrPostAdditions = $this->getPostAttributesFromData_getPostAdditions($data);

		$arrParams = $objAddonView->getChildParams_post($postID, $arrPostAdditions);

		$response = array();
		$response["child_params_post"] = $arrParams;

		return ($response);
	}

	/**
	 *
	 * modify the text from widget
	 */
	public function modifyTextFromWidget($text){

		//convert current page
		
		if(strpos($text, "%current_page_url%") !== false){
			$urlPage = UniteFunctionsWPUC::getUrlCurrentPage(true);

			$text = str_replace("%current_page_url%", $urlPage, $text);
		}

		if(strpos($text, "%current_page_title%") !== false){
			$post = get_post();
			if($post){
				$title = $post->post_title;
				$text = str_replace("%current_page_title%", $title, $text);
			}
		}

		return ($text);
	}

	/**
	 * get last query post ids
	 */
	public function getLastQueryPostIDs(){

		$query = GlobalsProviderUC::$lastPostQuery;

		if(empty($query)){
			return (null);
		}

		$posts = $query->posts;

		if(empty($posts))
			return (null);

		$arrPostIDs = array();

		foreach($posts as $post){
			$postID = $post->ID;

			$arrPostIDs[] = $postID;
		}

		if(empty($arrPostIDs))
			return (null);

		$strPostIDs = implode(",", $arrPostIDs);

		return ($arrPostIDs);
	}

	/**
	 * get last query data
	 */
	public function getLastQueryData(){

		$query = GlobalsProviderUC::$lastPostQuery;

		if(empty($query)){
			return (null);
		}

		$objPagination = new UniteCreatorElementorPagination();
		$data = $objPagination->getPagingData();

		$totalPages = UniteFunctionsUC::getVal($data, "total");

		if($totalPages == 0)
			$totalPages = 1;

		$numPosts = 0;
		if(isset($query->posts))
			$numPosts = count($query->posts);

		$totalPosts = 0;
		if(isset($query->found_posts))
			$totalPosts = $query->found_posts;

		$arrQuery = $query->query;

		$postType = UniteFunctionsUC::getVal($arrQuery, "post_type");

		$orderBy = UniteFunctionsUC::getVal($arrQuery, "orderby");
		$orderDir = UniteFunctionsUC::getVal($arrQuery, "order");

		if(is_array($orderBy)){
			$orderDir = UniteFunctionsUC::getArrFirstValue($orderBy);
			$orderBy = UniteFunctionsUC::getFirstNotEmptyKey($orderBy);
		}

		$orderBy = strtolower($orderBy);
		$orderDir = strtolower($orderDir);

		if($orderBy == "id")
			$orderBy = "ID";
		
		//fix by actual num posts
		if(GlobalsProviderUC::$lastNumPosts > $numPosts){
			$totalPosts += (GlobalsProviderUC::$lastNumPosts - $numPosts);
			$numPosts = GlobalsProviderUC::$lastNumPosts;
		}
			
		$output = array();
		$output["count_posts"] = $numPosts;
		$output["total_posts"] = $totalPosts;
		$output["page"] = UniteFunctionsUC::getVal($data, "current");
		$output["num_pages"] = $totalPages;

		if(!empty($orderBy)){

			if($orderBy == "meta_value"){

				$metaKey = UniteFunctionsUC::getVal($arrQuery, "meta_key");

				if(!empty($metaKey))
					$orderBy = "meta__{$metaKey}__text";
			}

			if($orderBy == "meta_value_num"){

				$metaKey = UniteFunctionsUC::getVal($arrQuery, "meta_key");

				if(!empty($metaKey))
					$orderBy = "meta__{$metaKey}__number";
			}


			$output["orderby"] = $orderBy;
		}


		if(!empty($orderDir))
			$output["orderdir"] = $orderDir;

		if($postType == "product")
			$output["woo"] = true;

		return ($output);
	}

}
