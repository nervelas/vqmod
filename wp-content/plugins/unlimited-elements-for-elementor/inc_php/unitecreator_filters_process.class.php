<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved.
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
if ( ! defined( 'ABSPATH' ) ) exit;

class UniteCreatorFiltersProcess{

	const DEBUG_MAIN_QUERY = false;

	const DEBUG_FILTER = false;

	const DEBUG_PARSED_TERMS = false;

	private static $showDebug = false;

	private static $filters = null;
	private static $arrInputFiltersCache = null;
	private static $arrFiltersAssocCache = null;
	private static $currentTermCache = null;
	private static $isModeInit = false;
	private static $lastFiltersInitRequest = null;
	
	private static $isGutenberg = false;
	private static $platform = false;
	private static $objGutenberg = null;

	private static $isScriptAdded = false;
	private static $isFilesAdded = false;
	private static $isStyleAdded = false;
	private static $isAjaxCache = null;
	private static $isModeReplace = false;
	private static $numTotalPosts;

	private static $originalQueryVars = null;
	private $contentWidgetsDebug = array();
	private static $lastArgs = null;
	private static $isUnderAjaxSearch = false;
	public static $isUnderAjax = false;
	private static $showEchoDebug = false;
	
	private $hasSelectedByRequest = false;
	private $hasSelectedTerm = false;
	
	private static $searchElementID = null;
	private static $arrContent = null;		//last page content
	
	private $titleStart = false;	//title start string
	
	const TYPE_TABS = "tabs";
	const TYPE_SELECT = "select";
	const TYPE_CHECKBOX = "checkbox";

	const ROLE_CHILD = "child";
	const ROLE_TERM_CHILD = "term_child";
	const ROLE_CHILD_AUTO_TERMS = "child_auto";
	
	
	private function _______SORT_FILTER_WIDGET_DATA__________(){}

	/**
	 * get sort filter data
	 * type - regular / woo
	 * data - widget data
	 */
	public static function getSortFilterData($filterType, $params){

		//get fields

		$arrFields = UniteFunctionsUC::getVal($params, "fields");
		
		$arrFields = UniteFunctionsUC::getVal($arrFields, "fields_fields");

		if(empty($arrFields))
			$arrFields = array(
				array("title"=>"Default","type"=>"default"),
			);

		$isForWooProducts = false;
		if($filterType == "woo")
			$isForWooProducts = true;

		$arrWooTypes = array("sale_price","sales","rating");

		$output = array();

		$isEnableMeta = UniteFunctionsUC::getVal($params, "enable_meta");
		$isEnableMeta = UniteFunctionsUC::strToBool($isEnableMeta);

		//find if the meta enabled - old way

		if($isEnableMeta == true){
			$metaName = UniteFunctionsUC::getVal($params, "meta_name");

			$metaName = trim($metaName);

			if(empty($metaName))
				$isEnableMeta = false;
		}
		
		$isWPML = UniteCreatorWpmlIntegrate::isWpmlExists();
		
		$activeLang = null;
		
		if($isWPML == true){
			
			$objWPML = new UniteCreatorWpmlIntegrate();
			$activeLang = $objWPML->getActiveLanguage();
		}
		
		foreach($arrFields as $field){
			
			$title = UniteFunctionsUC::getVal($field, "title");
			$type = UniteFunctionsUC::getVal($field, "type");

			//replace the title with language related title
			
			if(!empty($activeLang)){
								
				$titleLang = UniteFunctionsUC::getVal($field, "title_" . $activeLang);
				
				if(!empty($titleLang))
					$title = $titleLang;
				
			}
						
			//disable meta if not selected

			$typeForOutput = null;

			if($type == "meta"){

				$fieldMetaName = UniteFunctionsUC::getVal($field, "meta_name");
				$fieldMetaType = UniteFunctionsUC::getVal($field, "meta_type");

				if(empty($fieldMetaName) && $isEnableMeta == true){
					$fieldMetaName = $metaName;
				}

				if(empty($fieldMetaName))
					continue;

				if(empty($fieldMetaType))
					continue;

				$fieldMetaName = trim($fieldMetaName);

				$typeForOutput = "meta__{$fieldMetaName}__{$fieldMetaType}";
			}

			//filter woo types
			if($isForWooProducts == false&& in_array($type, $arrWooTypes) == true)
				continue;

			if(!empty($typeForOutput))
				$type = $typeForOutput;

			$output[$type] = $title;
		}

		
		return($output);
	}




	/**
	 * get fitler url from the given slugs
	 */
	private function getUrlFilter_term($term, $taxonomyName){

		$key = "filter-term";

		$taxPrefix = $taxonomyName."--";

		if($taxonomyName == "category"){
			$taxPrefix = "";
			$key="filter-category";
		}

		$slug = $term->slug;

		$value = $taxPrefix.$slug;

		$urlAddition = "{$key}=".urlencode($value);

		$urlCurrent = GlobalsUC::$current_page_url;

		$url = UniteFunctionsUC::addUrlParams($urlCurrent, $urlAddition);

		return($url);
	}

	/**
	 * check if the term is acrive
	 */
	private function isTermActive($term, $arrActiveFilters = null){

		if(empty($term))
			return(false);

		if($arrActiveFilters === null)
			$arrActiveFilters = $this->getRequestFilters();

		if(empty($arrActiveFilters))
			return(false);

		$taxonomy = $term->taxonomy;

		$selectedTermID = UniteFunctionsUC::getVal($arrActiveFilters, $taxonomy);

		if(empty($selectedTermID))
			return(false);

		if($selectedTermID === $term->term_id)
			return(true);

		return(false);
	}

	/**
	 * get current term by query vars
	 */
	private function getCurrentTermByQueryVars($queryVars){

		if(is_array($queryVars) == false)
			return(null);

		if(empty($queryVars))
			return(null);

		if(count($queryVars) > 1)
			return(null);

		$postType = null;
		if(isset($queryVars["post_type"])){

			$postType = $queryVars["post_type"];
			unset($queryVars["post_type"]);
		}

		$args = array();
		if(!empty($postType))
			$args["post_type"] = $postType;

		if(!empty($queryVars)){
			$taxonomy = null;
			$slug = null;

			foreach($queryVars as $queryTax=>$querySlug){

				$taxonomy = $queryTax;
				$slug = $querySlug;
			}

			$args = array();
			$args["taxonomy"] = $taxonomy;
			$args["slug"] = $slug;
		}


		$arrTerms = get_terms($args);

		$isError = is_wp_error($arrTerms);

		if($isError == true){
			if(self::$showDebug == true){
				
				dmp("error get terms");
				dmp($args);
				dmp($arrTerms);
			}

			UniteFunctionsUC::throwError("cannot get the terms");
		}

		if(empty($arrTerms))
			return(null);

		$term = $arrTerms[0];

		return($term);
	}


	/**
	 * get current term
	 */
	private function getCurrentTerm(){

		if(!empty(self::$currentTermCache))
			return(self::$currentTermCache);

		if(is_archive() == false)
			return(null);

		if(!empty(self::$originalQueryVars)){

			$currentTerm = $this->getCurrentTermByQueryVars(self::$originalQueryVars);
		}else{
			$currentTerm = get_queried_object();



			if($currentTerm instanceof WP_Term == false)
				$currentTerm = null;
		}

		self::$currentTermCache = $currentTerm;

		return($currentTerm);
	}

	private function _______PARSE_INPUT_FILTERS__________(){}

	/**
	 * get request array
	 */
	private function getArrRequest(){
		
		$request = $_GET;
		if(!empty($_POST))
			$request = array_merge($request, $_POST);
		
		//add from query vars:
		
		$queryVars = UniteFunctionsWPUC::getCurrentQueryVars();
		
		$keys = array("ucterms");
		
		foreach($keys as $key)
			if(!isset($request[$key]) && isset($queryVars[$key]))
				$request[$key] = $queryVars[$key];
		
		return($request);
	}
	

	/**
	 * parse the values groups
	 */
	private function parseStrTerms_groups($strValues){

		preg_match_all('/\|(.*?)\|/', $strValues, $matches);

		if(empty($matches))
			return(array());

		$arrGroups = $matches[0];
		$arrGroupValues = $matches[1];

		$arrReplace = array();

		//break into groups

		foreach($arrGroups as $index => $group){

			$strReplace = "group".($index+1)."_".UniteFunctionsUC::getRandomString();

			$strGroupValues = $arrGroupValues[$index];

			$arrReplace[$strReplace] = $strGroupValues;

			$strValues = str_replace($group, $strReplace, $strValues);
		}

		//get the array
		
		$arrValues = $this->parseStrTerms_values($strValues);
			
		foreach($arrValues as $key => $value){

			if(isset($arrReplace[$value])){

				$strGroupValue = $arrReplace[$value];

				$arrGroupValue = $this->parseStrTerms_values($strGroupValue);
				
				if(isset($arrGroupValue["relation"]) == false)
					$arrGroupValue["relation"] = "OR";
				
				if(count($arrGroupValue) == 1)
					$arrGroupValue = $arrGroupValue[0];

				$arrValues[$key] = $arrGroupValue;
			}

		}

		$arrValues["relation"] = "AND";
				
		return($arrValues);
	}



	/**
	 * parse the values
	 */
	private function parseStrTerms_values($strValues){

		//get the groups instead

		if(strpos($strValues,"|") !== false){

			$arrValues = $this->parseStrTerms_groups($strValues);
			
			return($arrValues);
		}

		$arrValues = explode(".", $strValues);

		$isTermsAnd = false;
		foreach($arrValues as $valueKey=>$value){
			if($value === "*"){
				unset($arrValues[$valueKey]);
				$isTermsAnd = true;
			}
		}

		if($isTermsAnd == true)
			$arrValues["relation"] = "AND";


		return($arrValues);
	}

	/**
	 * get term id from terms string
	 */
	private function getTermIDByStrTerms($strTerms){
		
		if(empty($strTerms))
			return(null);
			
		$arrTerm = explode(":", $strTerms);
		
		if(count($arrTerm) != 2)
			return(null);

		$taxonomy = $arrTerm[0];
		$slug = $arrTerm[1];
		
		$taxonomy = UniteFunctionsUC::sanitize($taxonomy, UniteFunctionsUC::SANITIZE_TEXT_FIELD);
		$slug = UniteFunctionsUC::sanitize($slug, UniteFunctionsUC::SANITIZE_TEXT_FIELD);
		
		if(empty($slug))
			return(null);
			
		$term = UniteFunctionsWPUC::getTermBySlug($taxonomy, $slug);
		
		if(empty($term))
			return(null);
			
		$termID = $term->term_id;
		
		return($termID);
	}
	

	/**
	 * parse filters string
	 */
	private function parseStrTerms($strFilters){

		$arrUrlKeys = $this->getUrlPartsKeys();
		
		$taxSapSign = UniteFunctionsUC::getVal($arrUrlKeys, "tax_sap");
		
		//fallback, if ~ sign exists - change to it
		
		if($taxSapSign != "~" && strpos($strFilters,"~") !== false)
			$taxSapSign = "~";

		$strFilters = trim($strFilters);
				
		$arrFilters = explode(";", $strFilters);

		//fill the terms
		$arrTerms = array();

		foreach($arrFilters as $strFilter){

			$arrFilter = explode($taxSapSign, $strFilter);

			if(count($arrFilter) != 2)
				continue;

			$key = $arrFilter[0];
			$strValues = $arrFilter[1];

			$arrValues = $this->parseStrTerms_values($strValues);

			$arrTerms[$key] = $arrValues;
		}

		//show debug terms

		if(self::DEBUG_PARSED_TERMS == true){

			dmp("parsed terms");
			dmp($arrTerms);
			exit();
		}


		$arrOutput = array();

		if(!empty($arrTerms))
			$arrOutput[self::TYPE_TABS] = $arrTerms;
		

		return($arrOutput);
	}

	/**
	 * get orderby input filter
	 */
	private function getArrInputFilters_getOrderby($arrOutput, $request){
		
		$orderby = UniteFunctionsUC::getVal($request, "ucorderby");
		$orderby = UniteProviderFunctionsUC::sanitizeVar($orderby, UniteFunctionsUC::SANITIZE_KEY);

		if(empty($orderby))
			return($arrOutput);
		
		//check if valid
		$arrOrderby = UniteFunctionsWPUC::getArrSortBy(true);

		if($orderby == "id")
			$orderby = "ID";

		if(is_string($orderby) && isset($arrOrderby[$orderby]))
			$arrOutput["orderby"] = $orderby;

		//meta old name
		if($orderby == "meta"){

			$orderbyMeta = UniteFunctionsUC::getVal($request, "ucorderby_meta");
			$orderbyMeta = UniteProviderFunctionsUC::sanitizeVar($orderbyMeta, UniteFunctionsUC::SANITIZE_KEY);

			$orderbyMetaType = UniteFunctionsUC::getVal($request, "ucorderby_metatype");
			$orderbyMetaType = UniteProviderFunctionsUC::sanitizeVar($orderbyMetaType, UniteFunctionsUC::SANITIZE_KEY);

			if(!empty($orderbyMeta)){
				$arrOutput["orderby"] = $orderby;
				$arrOutput["orderby_metaname"] = $orderbyMeta;
			}

			if(!empty($orderbyMetaType))
				$arrOutput["orderby_metatype"] = $orderbyMetaType;

		}

		//meta new way

		if(strpos($orderby, "meta__") === false)
			return($arrOutput);

		$arrMeta = explode("__", $orderby);

		if(count($arrMeta) != 3)
			return($arrOutput);

		$orderby = $arrMeta[0];
		$metaName = $arrMeta[1];
		$metaType = $arrMeta[2];

		if($orderby != "meta")
			return($arrOutput);

		$arrOutput["orderby"] = $orderby;
		$arrOutput["orderby_metaname"] = $metaName;
		$arrOutput["orderby_metatype"] = $metaType;


		return($arrOutput);
	}


	/**
	 * get filters array from input
	 */
	private function getArrInputFilters(){

		if(!empty(self::$arrInputFiltersCache))
			return(self::$arrInputFiltersCache);

		$request = $this->getArrRequest();
		
		$strTerms = UniteFunctionsUC::getVal($request, "ucterms");
		
		if($strTerms === "undefined:undefined")
			$strTerms = null;
		
		$arrOutput = array();

		//parse filters

		if(!empty($strTerms)){
			if(self::$showDebug == true)
				dmp("input filters found: $strTerms");

			$arrOutput = $this->parseStrTerms($strTerms);
		}
		
		
		//page

		$page = UniteFunctionsUC::getVal($request, "ucpage");
		$page = (int)$page;

		if(!empty($page))
			$arrOutput["page"] = $page;

		//offset
		$offset = UniteFunctionsUC::getVal($request, "ucoffset");
		$offset = (int)$offset;

		if(!empty($offset))
			$arrOutput["offset"] = $offset;

		//num items

		$numItems = UniteFunctionsUC::getVal($request, "uccount");
		$numItems = (int)$numItems;
		
		if(!empty($numItems))
			$arrOutput["num_items"] = $numItems;

		//search
		$search = UniteFunctionsUC::getVal($request, "ucs");

		if(!empty($search))
			$arrOutput["search"] = $search;

		//price

		$priceFrom = UniteFunctionsUC::getVal($request, "ucpricefrom");
		$priceTo = UniteFunctionsUC::getVal($request, "ucpriceto");

		if(!empty($priceFrom))
			$arrOutput["price_from"] = $priceFrom;

		if(!empty($priceTo))
			$arrOutput["price_to"] = $priceTo;


		//exclude
		$exclude = UniteFunctionsUC::getVal($request, "ucexclude");
		$exclude = UniteProviderFunctionsUC::sanitizeVar($exclude, UniteFunctionsUC::SANITIZE_TEXT_FIELD);

		if(!empty($exclude)){

			$isValid = UniteFunctionsUC::isValidIDsList($exclude);

			if($isValid == true)
				$arrOutput["exclude"] = $exclude;
		}

		//orderby

		$arrOutput = $this->getArrInputFilters_getOrderby($arrOutput, $request);
		
		//orderdir

		$orderDir = UniteFunctionsUC::getVal($request, "ucorderdir");

		if($orderDir == "asc" || $orderDir == "desc")
			$arrOutput["orderdir"] = $orderDir;
		
		//title start
			
		$titleStart = UniteFunctionsUC::getVal($request, "titlestart");
		
		//validation
		if(!empty($titleStart)){
			if(!UniteFunctionsUC::isAlphaNumeric($titleStart))
				UniteFunctionsUC::throwError("Wrong title start. please check the alphabit filter, should be some letter only");
			
			$arrOutput["titlestart"] = strtolower($titleStart);
		}
				
		//main term
		
		$strMainTerm = UniteFunctionsUC::getVal($request, "ucmainterm");
		
		if(!empty($strMainTerm)){
			$mainTermID = $this->getTermIDByStrTerms($strMainTerm);
			
			if(!empty($mainTermID))
				$arrOutput["maintermid"] = $mainTermID;
		}
		
		//authors
		$strAuthors = UniteFunctionsUC::getVal($request, "ucauthors");
		$strAuthors = UniteProviderFunctionsUC::sanitizeVar($strAuthors, UniteFunctionsUC::SANITIZE_TEXT_FIELD);
		
		if(!empty($strAuthors)){
			//validate IDs list
			UniteFunctionsUC::validateIDsList($strAuthors, "authors list");
			
			//parse comma-separated IDs
			$arrAuthorIDs = UniteFunctionsUC::csvToArray($strAuthors);
			
			if(!empty($arrAuthorIDs)){
				$arrAuthorIDs = array_unique($arrAuthorIDs);
				$arrOutput["authors"] = $arrAuthorIDs;
			}
		}

		//meta - same string format as terms: key:value1.value2.*;key2:value3
		$strMeta = UniteFunctionsUC::getVal($request, "ucmeta");

		if($strMeta === "undefined:undefined")
			$strMeta = null;

		if(!empty($strMeta)){

			if(self::$showDebug == true)
				dmp("input meta filters found: $strMeta");

			$arrParsedMeta = $this->parseStrTerms($strMeta);
			$arrMeta = UniteFunctionsUC::getVal($arrParsedMeta, self::TYPE_TABS);

			if(!empty($arrMeta))
				$arrOutput["meta"] = $arrMeta;
		}
		
		self::$arrInputFiltersCache = $arrOutput;
		
		
		return($arrOutput);
	}



	/**
	 * get filters arguments
	 */
	public function getRequestFilters(){
		
		if(self::$filters !== null)
			return(self::$filters);

		self::$filters = array();

		$arrInputFilters = $this->getArrInputFilters();

		if(empty($arrInputFilters))
			return(self::$filters);

		$arrTerms = UniteFunctionsUC::getVal($arrInputFilters, self::TYPE_TABS);

		if(!empty($arrTerms))
			self::$filters["terms"] = $arrTerms;

		//get the page

		$page = UniteFunctionsUC::getVal($arrInputFilters, "page");

		if(!empty($page) && is_numeric($page))
			self::$filters["page"] = $page;

		//get the offset

		$offset = UniteFunctionsUC::getVal($arrInputFilters, "offset");

		if(!empty($offset) && is_numeric($offset))
			self::$filters["offset"] = $offset;
		
		//get num items
		$numItems = UniteFunctionsUC::getVal($arrInputFilters, "num_items");

		if(!empty($numItems) && is_numeric($numItems))
			self::$filters["num_items"] = $numItems;

		//get search
		$search = UniteFunctionsUC::getVal($arrInputFilters, "search");

		if(!empty($search))
			self::$filters["search"] = $search;

		//get exclude
		$exclude = UniteFunctionsUC::getVal($arrInputFilters, "exclude");

		if(!empty($exclude))
			self::$filters["exclude"] = $exclude;

		//get orderby
		$orderby = UniteFunctionsUC::getVal($arrInputFilters, "orderby");

		if(!empty($orderby)){

			self::$filters["orderby"] = $orderby;

			if($orderby == "meta"){
				self::$filters["orderby_metaname"] = UniteFunctionsUC::getVal($arrInputFilters, "orderby_metaname");
				self::$filters["orderby_metatype"] = UniteFunctionsUC::getVal($arrInputFilters, "orderby_metatype");
			}
		}

		//get orderdir
		$orderdir = UniteFunctionsUC::getVal($arrInputFilters, "orderdir");

		if(!empty($orderdir))
			self::$filters["orderdir"] = $orderdir;

		//price

		$priceFrom = UniteFunctionsUC::getVal($arrInputFilters, "price_from");
		$priceTo = UniteFunctionsUC::getVal($arrInputFilters, "price_to");

		if(!empty($priceFrom) && is_numeric($priceFrom))
			self::$filters["price_from"] = $priceFrom;

		if(!empty($priceTo) && is_numeric($priceTo))
			self::$filters["price_to"] = $priceTo;
		
		//title start
		
		$titleStart = UniteFunctionsUC::getVal($arrInputFilters, "titlestart");
		
		if(!empty($titleStart))
			self::$filters["titlestart"] = $titleStart;
		
		//main term
		$mainTermID = UniteFunctionsUC::getVal($arrInputFilters, "maintermid");

		if(!empty($mainTermID))
			self::$filters["maintermid"] = $mainTermID;
		
		//authors
		$arrAuthors = UniteFunctionsUC::getVal($arrInputFilters, "authors");

		if(!empty($arrAuthors) && is_array($arrAuthors))
			self::$filters["authors"] = $arrAuthors;

		//meta
		$arrMeta = UniteFunctionsUC::getVal($arrInputFilters, "meta");

		if(!empty($arrMeta) && is_array($arrMeta))
			self::$filters["meta"] = $arrMeta;
		
				
		return(self::$filters);
	}


	private function _______FILTER_ARGS__________(){}


	/**
	 * get offset
	 */
	private function processRequestFilters_setPaging($args, $page, $numItems){

		if(empty($page))
			return(null);

		$perPage = UniteFunctionsUC::getVal($args, "posts_per_page");

		if(empty($perPage))
			return($args);

		$offset = null;
		$postsPerPage = null;

		//set posts per page and offset
		if(!empty($numItems) && $page > 1){

			if($page == 2)
				$offset = $perPage;
			else if($page > 2)
				$offset = $perPage+($page-2)*$numItems;

			$postsPerPage = $numItems;

		}else{	//no num items
			$offset = ($page-1)*$perPage;
		}

		if(!empty($offset))
			$args["offset"] = $offset;

		if(!empty($postsPerPage))
			$args["posts_per_page"] = $postsPerPage;

		return($args);
	}

	/**
	 * get tax query from terms array
	 */
	private function getTaxQuery($arrTax){
		
		if(empty($arrTax))
			return(array());
		
		$arrQuery = array();

		foreach($arrTax as $taxonomy=>$arrTerms){

			$relation = UniteFunctionsUC::getVal($arrTerms, "relation");
			
			if($relation == "AND"){		//multiple

				unset($arrTerms["relation"]);

				foreach($arrTerms as $term){
					$arrTermItems = $this->getTaxQuery_getTermItems($taxonomy, $term);
					$arrQuery = array_merge($arrQuery, $arrTermItems);
				}

			}else{		//single  (or)

				$item = array();
				$item["taxonomy"] = $taxonomy;
				$item["field"] = "slug";
				$item["terms"] = $arrTerms;
					
				$arrQuery[] = $item;
			}

		}

		$arrQuery["relation"] = "AND";

		return($arrQuery);
	}


	/**
	 * get tax query items from parsed term (slug string or nested group array)
	 */
	private function getTaxQuery_getTermItems($taxonomy, $term){

		if(is_array($term) == false){
			return(array(
				array(
					"taxonomy" => $taxonomy,
					"field" => "slug",
					"terms" => $term,
				)
			));
		}

		$innerRelation = UniteFunctionsUC::getVal($term, "relation");

		if($innerRelation == "AND"){
			unset($term["relation"]);

			$arrItems = array();

			foreach($term as $subTerm){
				$arrSubItems = $this->getTaxQuery_getTermItems($taxonomy, $subTerm);
				$arrItems = array_merge($arrItems, $arrSubItems);
			}

			return($arrItems);
		}

		if(isset($term["relation"]))
			unset($term["relation"]);

		return(array(
			array(
				"taxonomy" => $taxonomy,
				"field" => "slug",
				"terms" => array_values($term),
			)
		));
	}

	/**
	 * get meta query from parsed meta filters (same structure as terms)
	 * arrMeta = [meta_key => [value1, value2, "relation"=>"AND"]]
	 */
	private function getMetaQuery($arrMeta){

		if(empty($arrMeta) || is_array($arrMeta) == false)
			return(array());

		$arrQuery = array();

		foreach($arrMeta as $metaKey => $arrValues){

			$metaKey = sanitize_key($metaKey);

			if(empty($metaKey))
				continue;

			$relation = null;

			if(is_array($arrValues))
				$relation = UniteFunctionsUC::getVal($arrValues, "relation");

			if(is_array($arrValues) && isset($arrValues["relation"]))
				unset($arrValues["relation"]);

			if(is_array($arrValues) == false)
				$arrValues = array($arrValues);

			$arrCleanValues = array();

			foreach($arrValues as $metaValue){

				if(is_array($metaValue))
					continue;

				$metaValue = sanitize_text_field($metaValue);

				if($metaValue === "" || $metaValue === null)
					continue;

				$arrCleanValues[] = $metaValue;
			}

			if(empty($arrCleanValues))
				continue;

			if($relation == "AND"){

				foreach($arrCleanValues as $metaValue){

					$arrQuery[] = array(
						"key" => $metaKey,
						"value" => $metaValue,
						"compare" => "=",
					);
				}

			}else{

				if(count($arrCleanValues) == 1){

					$arrQuery[] = array(
						"key" => $metaKey,
						"value" => $arrCleanValues[0],
						"compare" => "=",
					);

				}else{

					$arrQuery[] = array(
						"key" => $metaKey,
						"value" => $arrCleanValues,
						"compare" => "IN",
					);
				}
			}
		}

		if(count($arrQuery) > 1)
			$arrQuery["relation"] = "AND";

		return($arrQuery);
	}


	/**
	 * remove "not in" tax query
	 */
	private function keepNotInTaxQuery($arrTaxQuery){

		if(empty($arrTaxQuery))
			return(null);

		$arrNew = array();

		foreach($arrTaxQuery as $tax){

			if(isset($tax["operator"])){
				$arrNew[] = $tax;
				continue;
			}

			$operator = UniteFunctionsUC::getVal($tax, "operator");
			if($operator == "NOT IN")
				$arrNew[] = $tax;
		}

		return($arrNew);
	}


	/**
	 * set arguments tax query, merge with existing if avaliable
	 */
	private function setArgsTaxQuery($args, $arrTaxQuery){
		
		if(empty($arrTaxQuery) && self::$isModeReplace == false)
			return($args);
				
		$existingTaxQuery = UniteFunctionsUC::getVal($args, "tax_query");
				
		//if replace terms mode - just delete the existing tax query
		if(self::$isModeReplace == true){
			
			if(empty($arrTaxQuery)){
				
				$args["tax_query"] = array();
				return($args);
				
			}
			
			$existingTaxQuery = $this->keepNotInTaxQuery($existingTaxQuery);
		}
		
		if(empty($existingTaxQuery)){

			$args["tax_query"] = $arrTaxQuery;

			return($args);
		}

		$newTaxQuery = array(
			$existingTaxQuery,
			$arrTaxQuery
		);

		$newTaxQuery["relation"] = "AND";


		$args["tax_query"] = $newTaxQuery;

		return($args);
	}


	/**
	 * process request filters
	 */
	public function processRequestFilters($args, $isFilterable, $arrFiltersCommands = array()){
		
		$this->setShowDebug();
				
		//allow all ajax, forbid under request and not filterable.
		
		if($isFilterable == false)
			return($args);
					
		$arrFilters = $this->getRequestFilters();
		
		$arrMetaQuery = array();
	
		//---- set offset and count ----

		$page = UniteFunctionsUC::getVal($arrFilters, "page");
		$numItems = UniteFunctionsUC::getVal($arrFilters, "num_items");
		$offset = UniteFunctionsUC::getVal($arrFilters, "offset");
		$search = UniteFunctionsUC::getVal($arrFilters, "search");
		$exclude = UniteFunctionsUC::getVal($arrFilters, "exclude");
		$orderby = UniteFunctionsUC::getVal($arrFilters, "orderby");
		$orderdir = UniteFunctionsUC::getVal($arrFilters, "orderdir");
		$priceFrom = UniteFunctionsUC::getVal($arrFilters, "price_from");
		$priceTo = UniteFunctionsUC::getVal($arrFilters, "price_to");
		$titleStart = UniteFunctionsUC::getVal($arrFilters, "titlestart");
		$mainTermID = UniteFunctionsUC::getVal($arrFilters, "maintermid");
		$arrAuthors = UniteFunctionsUC::getVal($arrFilters, "authors");
		$arrMeta = UniteFunctionsUC::getVal($arrFilters, "meta");
		
		
		if(!empty($page))
			$args = $this->processRequestFilters_setPaging($args, $page, $numItems);

		//set paging by offset
		if(!empty($offset))
			$args["offset"] = $offset;

		if(!empty($numItems))
			$args["posts_per_page"] = $numItems;

		//search
		if(!empty($search) && $search != "_all_"){
			
			$args["s"] = $search;
						
			if(!empty(self::$searchElementID)){
				
				$searchWidgetData = $this->getSettingsValuesFromElement(self::$arrContent, self::$searchElementID);
				
				GlobalsProviderUC::$isUnderAjaxSearch = true;
				
				$objAjaxSearch = new UniteCreatorAjaxSeach();
				$objAjaxSearch->initCustomAjaxSeach($searchWidgetData);
								
			}
			
		}
		
		//orderby
		if(!empty($orderby) && $orderby != "default"){

			$args["orderby"] = $orderby;

			if($orderby == "meta"){

				$metaName = UniteFunctionsUC::getVal($arrFilters, "orderby_metaname");
				$metaType = UniteFunctionsUC::getVal($arrFilters, "orderby_metatype");

				if(!empty($metaName)){

					if($metaType == "number")
						$args["orderby"] = "meta_value_num";
					else
						$args["orderby"] = "meta_value";

					$args["meta_key"] = $metaName;

				}

			}

			if($orderby == UniteFunctionsWPUC::SORTBY_PRICE){
				$args["orderby"] = "meta_value_num";
				$args["meta_key"] = "_price";
			}

			if($orderby == UniteFunctionsWPUC::SORTBY_SALE_PRICE){
				$args["orderby"] = "meta_value_num";
				$args["meta_key"] = "_sale_price";
			}

		}

		//orderdir
		if(!empty($orderdir) && $orderdir != "default"){
			$args["order"] = strtoupper($orderdir);
		}
		
		$arrTerms = UniteFunctionsUC::getVal($arrFilters, "terms");
		
		//if mode init - the filters should be set by "all". unless there are selected terms
		//the posts set, not by the selected ones.

		$strSelectedTermIDs = UniteFunctionsUC::getPostGetVariable("ucinitselectedterms","",UniteFunctionsUC::SANITIZE_TEXT_FIELD);
		
		if(self::$isModeInit == false || 
		   self::$isModeInit == true && !empty($strSelectedTermIDs) ){	
			
			//combine the tax queries
			$arrTaxQuery = $this->getTaxQuery($arrTerms);
			
			$args = $this->setArgsTaxQuery($args, $arrTaxQuery);
		}
		
		
		//exclude
		if(!empty($exclude)){

			$arrExclude = explode(",", $exclude);

			$arrExclude = array_unique($arrExclude);

			$arrNotIn = UniteFunctionsUC::getVal($args, "post__not_in");

			if(empty($arrNotIn))
				$arrNotIn = array();

			$arrNotIn = array_merge($arrNotIn, $arrExclude);

			$args["post__not_in"] = $arrExclude;

		}

		//supress all filters
		if(self::$isUnderAjaxSearch == true){
						
			//delete all filters in case of ajax search
			
			UniteCreatorAjaxSeach::supressThirdPartyFilters();
		}
		
		//Woo Prices
		
		if(!empty($priceFrom)){

			$arrMetaQuery[] = array(
                'key' => '_price',
                'value' => $priceFrom,
                'compare' => '>=',
                'type' => 'NUMERIC',
            );
		}

		if(!empty($priceTo)){

			$arrMetaQuery[] = array(
                'key' => '_price',
                'value' => $priceTo,
                'compare' => '<=',
                'type' => 'NUMERIC'
        	);
		}

		//meta filter - same structure as terms (key => values)
		if(!empty($arrMeta) && is_array($arrMeta)){

			$arrMetaFromFilter = $this->getMetaQuery($arrMeta);

			if(!empty($arrMetaFromFilter)){

				$relation = UniteFunctionsUC::getVal($arrMetaFromFilter, "relation");

				if(isset($arrMetaFromFilter["relation"]))
					unset($arrMetaFromFilter["relation"]);

				if(count($arrMetaFromFilter) == 1){

					$arrMetaQuery[] = $arrMetaFromFilter[0];

				}elseif(count($arrMetaFromFilter) > 1){

					if(!empty($relation))
						$arrMetaFromFilter["relation"] = $relation;

					$arrMetaQuery[] = $arrMetaFromFilter;
				}
			}
		}
		

		//set the meta query

		if(!empty($arrMetaQuery)){

			$arrExistingMeta = UniteFunctionsUC::getVal($args, "meta_query",array());

			$args["meta_query"] = array_merge($arrExistingMeta, $arrMetaQuery);
		}
		
		//set the title start hook
		
		if(!empty($titleStart)){
			
			$this->titleStart = $titleStart;
			
			add_filter( 'posts_where', array($this,'setWhereTitleStart'), 10, 2 );
		}
		
		//authors filter
		if(!empty($arrAuthors) && is_array($arrAuthors)){
			
			$arrAuthorIDs = array();
			
			foreach($arrAuthors as $authorID){
				$authorID = (int)$authorID;
				if($authorID > 0)
					$arrAuthorIDs[] = $authorID;
			}
			
			if(!empty($arrAuthorIDs)){
				
				//if author__in already exists, merge with existing IDs
				if(isset($args["author__in"]) && is_array($args["author__in"])){
					$arrAuthorIDs = array_merge($args["author__in"], $arrAuthorIDs);
					$arrAuthorIDs = array_unique($arrAuthorIDs);
				}
				
				$args["author__in"] = $arrAuthorIDs;
			}
		}
		
		//set the title start
		
		if(self::$showDebug == true){

			dmp("args:");
			dmp($args);

			dmp("filters:");
			dmp($arrFilters);
		}
		
		
		return($args);
	}
	
	/**
	 * set the where title start
	 */
	public function setWhereTitleStart($where, $wp_query){
		
		if(empty($this->titleStart))
			return($where);
		
		global $wpdb;		
		
		$where .= $wpdb->prepare(" AND $wpdb->posts.post_title LIKE %s", $this->titleStart.'%');		
		
		return($where);
	}

	/**
	 * on after query run
	 */
	public function afterQueryRun(){
		
		if(!empty($this->titleStart)){	//remove the filter
			
			$this->titleStart = null;
			
			remove_filter( 'posts_where', array($this,'setWhereTitleStart'), 10, 2);
		}
		
		
	}
	
	private function _______SYNC__________(){}
	
	/**
	 * get last grid request
	 */
	private function getLastGridRequest(){
		
		if(!empty($lastFiltersInitRequest))
			return($lastFiltersInitRequest);
			
		if(!empty(GlobalsProviderUC::$lastQueryRequest))
			return(GlobalsProviderUC::$lastQueryRequest);
		
		$args = GlobalsProviderUC::$lastQueryArgs;
		
		if(self::$showDebug == true){
			dmp("--- Last Query Args:");
			dmp($args);
		}
			
		$query = new WP_Query($args);
		
		if (is_wp_error($query)) {
		    $error_message = $query->get_error_message();
		    UniteFunctionsUC::throwError("get last grid failed: ".$error_message);
		}
			
		$request = $query->request;
					
		//some times other hooks distrubting the request
		//clear filters and run again if empty requests
		
		if(empty($request)){
			
			UniteFunctionsWPUC::clearAllWPFilters();
			
			$query = new WP_Query($args);
			$request = $query->request;
		}
		
		if(self::$showDebug == true){
			
			if(empty($request))
				dmp("EMPTY TAX REQUEST!!! - WILL CAUSE ERRORS IN TEST TERMS!");
		}
		
		return($request);
	}
	
	/**
	 * modify the request - change the buggy items
	 */
	private function modifySyncPostsRequest($request){
		
		$posLimit = strpos($request, "LIMIT");

		if($posLimit){
			$request = substr($request, 0, $posLimit-1);
			$request = trim($request);
		}
		
		$request = str_replace("SQL_CALC_FOUND_ROWS", "", $request);

		$prefix = UniteProviderFunctionsUC::$tablePrefix;

		$request = str_replace($prefix."posts.*", $prefix."posts.id", $request);
		
		return($request);
	}
	
	/**
	 * return only existing by thr grid letters
	 */
	public function syncAlphabetWithGrid($arrAlphabet){
		
		if(self::$isUnderAjax == false)
			return(array());
		
		$request = $this->getLastGridRequest();
		$request = $this->modifySyncPostsRequest($request);
		
		$prefix = UniteProviderFunctionsUC::$tablePrefix;
		
		$sql = "
			SELECT DISTINCT UPPER(LEFT(post_title, 1)) AS first_letter
			FROM {$prefix}posts AS p
			JOIN (
			    $request
			) AS req ON p.id = req.id
			ORDER BY first_letter ASC;
		";	

		$db = HelperUC::getDB();
		try{
	
			$response = @$db->fetchSql($sql);
			
		}catch(Exception $e){
			//leave it empty
		}

	if(empty($response))
		return(array());
	
	$arrAlphabet = UniteFunctionsUC::arrayToAssoc($arrAlphabet);
			
	$arrPostLetters = array();
	
	foreach($response as $arr){
		
		$letter = UniteFunctionsUC::getVal($arr, "first_letter");
		
		if(isset($arrAlphabet[$letter]) == false)
			continue;
		
		$arrPostLetters[] = $letter;		
	}
	
	
	return($arrPostLetters);
}

	/**
	 * get alphabet posts count
	 */
	public function getAlphabetPostsCount(){
		
	  if (self::$isUnderAjax == false) {
	        return array();
	    }
	
	    // get the last grid request SQL 
	    $request = $this->getLastGridRequest();
	    $request = $this->modifySyncPostsRequest($request);
	
	    $prefix = UniteProviderFunctionsUC::$tablePrefix;
	
	    $sql = "
	        SELECT 
	            UPPER(LEFT(p.post_title, 1)) AS first_letter,
	            COUNT(*) AS post_count
	        FROM {$prefix}posts AS p
	        JOIN (
	            {$request}
	        ) AS req ON p.id = req.id
	        WHERE p.post_title != ''
	        GROUP BY first_letter
	        ORDER BY first_letter ASC;
	    ";
	
	    $db = HelperUC::getDB();
	    $response = array();
			    
		 try {
	        $rows = @$db->fetchSql($sql);
	        if (!empty($rows)) {
	            foreach ($rows as $row) {
	                $letter = UniteFunctionsUC::getVal($row, "first_letter");
	                $count = UniteFunctionsUC::getVal($row, "post_count");
	                $response[$letter] = $count;
	            }
	        }
	    } catch (Exception $e) {
	        // fail silently, return empty
	    }
    	
	    if (empty($response))
	        return array();
	
	    return $response;	
	}

	/**
	 * return priceRangeMaxValue from Grid
	 */
	public function syncPriceRangeMaxValueWithGrid(){
		
		if(self::$isUnderAjax == false)
			return(array());
		
		$isDebug = GlobalsProviderUC::$showPostsQueryDebug;
			
		$request = $this->getLastGridRequest();
		
		$prefix = UniteProviderFunctionsUC::$tablePrefix;
		
		$request = $this->modifySyncPostsRequest($request);

		$sql = "SELECT MIN(CAST({$prefix}postmeta.meta_value AS SIGNED)) AS min_price, 
		           MAX(CAST({$prefix}postmeta.meta_value AS SIGNED)) AS max_price
		          FROM {$prefix}posts AS p
		          JOIN ({$request}) AS req ON p.ID = req.ID
		          JOIN {$prefix}postmeta ON (p.ID = {$prefix}postmeta.post_id)
		         WHERE {$prefix}postmeta.meta_key = '_price'";

		$db = HelperUC::getDB();
		try{
	
			$response = @$db->fetchSql($sql);
			
		if($isDebug == true){
			dmp("Price range repsonse");
			dmp($response);
		}
			
		}catch(Exception $e){
			
			if($isDebug == true){
				dmp("Price range error");
				dmp($e->getMessage());
			}
			
			//leave it empty
		}

	if(empty($response))
		return(array());

		$firstItem = $response[0];
		
		return($firstItem);
	}


	private function _______AJAX__________(){}
		
	/**
	 * get addon post list name
	 */
	private function getAddonPostListName($addon){

		$paramPostList = $addon->getParamByType(UniteCreatorDialogParam::PARAM_POSTS_LIST);

		$postListName = UniteFunctionsUC::getVal($paramPostList, "name");

		return($postListName);
	}


	/**
	 * validate if the addon ajax ready
	 * if it's have post list and has option that enable ajax
	 */
	private function validateAddonAjaxReady($addon, $arrSettingsValues){

		$paramPostList = $addon->getParamByType(UniteCreatorDialogParam::PARAM_POSTS_LIST);

		$paramListing = $addon->getListingParamForOutput();

		if(empty($paramPostList) && !empty($paramListing))
			$paramPostList = $paramListing;

		if(empty($paramPostList))
			UniteFunctionsUC::throwError("Widget not ready for ajax");

		$postListName = UniteFunctionsUC::getVal($paramPostList, "name");

		//check for ajax search
		$isAjaxSearch = $addon->isAjaxSearch();
		
		if($isAjaxSearch == true)
			return($postListName);
		
		
		$isAjaxReady = UniteFunctionsUC::getVal($arrSettingsValues, $postListName."_isajax");
		$isAjaxReady = UniteFunctionsUC::strToBool($isAjaxReady);

		if($isAjaxReady == false)
			UniteFunctionsUC::throwError("The ajax is not ready for this widget");

		return($postListName);
	}


	/**
	 * process the html output - convert all the links, remove the query part
	 */
	private function processAjaxHtmlOutput($html){

		$currentUrl = GlobalsUC::$current_page_url;

		$arrUrl = parse_url($currentUrl);

		$query = "?".UniteFunctionsUC::getVal($arrUrl, "query");

		$html = str_replace($query, "", $html);

		$query = str_replace("&", "&#038;", $query);

		$html = str_replace($query, "", $html);

		return($html);
	}

	/**
	 * modify settings values before set to addon
	 * set pagination type to post list values
	 */
	private function modifySettingsValues($arrSettingsValues, $postListName){

		$paginationType = UniteFunctionsUC::getVal($arrSettingsValues, "pagination_type");

		if(!empty($paginationType))
			$arrSettingsValues[$postListName."_pagination_type"] = $paginationType;

		return($arrSettingsValues);
	}
	
	/**
	 * get settings values from some element
	 */
	private function getSettingsValuesFromElement($arrContent, $elementID){
		
		if(self::$isGutenberg == false)
			$arrElement = HelperProviderCoreUC_EL::getArrElementFromContent($arrContent, $elementID);
		else
			$arrElement = self::$objGutenberg->getBlockByRootId($arrContent, $elementID);
		
		if(empty($arrElement)){

			UniteFunctionsUC::throwError(self::$platform." Widget with id: $elementID not found");
		}


		//Elementor Validations

		if(self::$isGutenberg == false){

			$type = UniteFunctionsUC::getVal($arrElement, "elType");

			if($type != "widget")
				UniteFunctionsUC::throwError("The element is not a widget");

			$widgetType = UniteFunctionsUC::getVal($arrElement, "widgetType");

			if(strpos($widgetType, "ucaddon_") === false){
		
				if($widgetType == "global")
					UniteFunctionsUC::throwError("Ajax filtering doesn't work with global widgets. Please change the grid to regular widget.");

				UniteFunctionsUC::throwError("Cannot output widget content for widget: $widgetType");
			}
						
		}
		
		//get settings values

		if(self::$isGutenberg == false)
			$arrSettingsValues = UniteFunctionsUC::getVal($arrElement, "settings");
		else
			$arrSettingsValues = self::$objGutenberg->getSettingsFromBlock($arrElement);
		
		
		//init addon

		$addon = new UniteCreatorAddon();

		if(self::$isGutenberg == false){		//init in elementor

			$widgetName = str_replace("ucaddon_", "", $widgetType);
			$addon->initByAlias($widgetName, GlobalsUC::ADDON_TYPE_ELEMENTOR);

		}else{		//init in gutenberg

			$blockName = UniteFunctionsUC::getVal($arrElement, "blockName");
			$addon->initByBlockName($blockName, GlobalsUC::ADDON_TYPE_ELEMENTOR);
		}
		
		//make a check that ajax option is on in this widget
		
		$addon->setParamsValues($arrSettingsValues);
					
		$arrParamsValues = $addon->getParamsValues();

		
		return($arrParamsValues);
	}

	/**
	 * get content element html
	 */
	private function getContentWidgetHtml($arrContent, $elementID, $isGrid = true){
		
		if(self::$isGutenberg == false)
			$arrElement = HelperProviderCoreUC_EL::getArrElementFromContent($arrContent, $elementID);
		else
			$arrElement = self::$objGutenberg->getBlockByRootId($arrContent, $elementID);
		
		if(empty($arrElement)){

			UniteFunctionsUC::throwError(self::$platform." Widget with id: $elementID not found");
		}


		//Elementor Validations

		if(self::$isGutenberg == false){

			$type = UniteFunctionsUC::getVal($arrElement, "elType");

			if($type != "widget")
				UniteFunctionsUC::throwError("The element is not a widget");

			$widgetType = UniteFunctionsUC::getVal($arrElement, "widgetType");

			if(strpos($widgetType, "ucaddon_") === false){
				
				if($widgetType == "global")
					UniteFunctionsUC::throwError("Ajax filtering doesn't work with global widgets. Please change the grid to regular widget.");
				
				UniteFunctionsUC::throwError("Cannot output widget content for widget: $widgetType");
			}
						
		}
		
		//get settings values

		if(self::$isGutenberg == false)
			$arrSettingsValues = UniteFunctionsUC::getVal($arrElement, "settings");
		else
			$arrSettingsValues = self::$objGutenberg->getSettingsFromBlock($arrElement);

		
		//init addon

		$addon = new UniteCreatorAddon();

		if(self::$isGutenberg == false){		//init in elementor

			$widgetName = str_replace("ucaddon_", "", $widgetType);
			$addon->initByAlias($widgetName, GlobalsUC::ADDON_TYPE_ELEMENTOR);

		}else{		//init in gutenberg

			$blockName = UniteFunctionsUC::getVal($arrElement, "blockName");
			$addon->initByBlockName($blockName, GlobalsUC::ADDON_TYPE_ELEMENTOR);
		}
		
		if(self::$showEchoDebug == true){
			$addonTitle = $addon->getTitle();
			dmp("<b>Put Widget: $addonTitle </b>");
		}

		//make a check that ajax option is on in this widget

		if($isGrid == true){

			$postListName = $this->validateAddonAjaxReady($addon, $arrSettingsValues);

			$arrSettingsValues = $this->modifySettingsValues($arrSettingsValues, $postListName);
		}

		$addon->setParamsValues($arrSettingsValues);

		if(GlobalsProviderUC::$isUnderAjax == true && self::$isGutenberg == false
			&& class_exists("UniteCreatorElementorIntegrate") && $addon->hasElementorDynamicSettings() == true){

			$layoutID = UniteFunctionsUC::getPostGetVariable("layoutid", "", UniteFunctionsUC::SANITIZE_KEY);

			$objElIntegrate = new UniteCreatorElementorIntegrate();
			$objElIntegrate->mergeElementorDynamicSettingsIntoAddon($addon, $layoutID);
		}
		
		//init the ajax search object to modify the post search list, if available
		if(GlobalsProviderUC::$isUnderAjaxSearch){
			
			$arrParamValues = $addon->getParamsValues();
			
			$objAjaxSearch = new UniteCreatorAjaxSeach();
			$objAjaxSearch->initCustomAjaxSeach($arrParamValues);
			
		}

		GlobalsUnlimitedElements::$currentRenderingAddon = $addon;
		
		//------ get the html output
		
		//collect the debug html
		if(self::$showDebug == false)
			UniteFunctionsUC::obStart();
		
		$objOutput = new UniteCreatorOutput();
		
	    $isDebugFromGet = HelperUC::hasPermissionsFromQuery("ucfieldsdebug");
		
	    if($isDebugFromGet == true)
	        $objOutput->showDebugData(true);
		
		$objOutput->initByAddon($addon);
		
		
		if(self::$showDebug == false){
			$htmlDebug = ob_get_contents();
			
			ob_end_clean();
	  	}


		$output = array();

		//get only items
		if($isGrid == true){
			
			$arrHtml = $objOutput->getHtmlItems();
			
			$output["html"] = UniteFunctionsUC::getVal($arrHtml, "html_items1");
			$output["html2"] = UniteFunctionsUC::getVal($arrHtml, "html_items2");

			$output["uc_id"] = $objOutput->getWidgetID();


		}else{		//not a grid - output of html template

			$htmlBody = $objOutput->getHtmlOnly();

			$htmlBody = $this->processAjaxHtmlOutput($htmlBody);
			
			$output["html"] = $htmlBody;
		}
		

		if(!empty($htmlDebug))
			$output["html_debug"] = $htmlDebug;
		
		if($isDebugFromGet == true){
	    	
			HelperProviderUC::showLastQueryPosts();			
			
			$htmlDebug = $objOutput->getHtmlDebug();
			
			uelm_echo($htmlDebug);
			dmp("End Here");
			exit();
		}
		
		GlobalsUnlimitedElements::$currentRenderingAddon = null;
		
		return($output);
	}


	/**
	 * get content widgets html
	 */
	private function getContentWidgetsHTML($arrContent, $strIDs, $isGrid = false){

		if(empty($strIDs))
			return(null);

		$arrIDs = explode(",", $strIDs);

		$arrHTML = array();

		$this->contentWidgetsDebug = array();

		foreach($arrIDs as $elementID){
			
			$output = $this->getContentWidgetHtml($arrContent, $elementID, $isGrid);

			$htmlDebug = UniteFunctionsUC::getVal($output, "html_debug");

			$html = UniteFunctionsUC::getVal($output, "html");
			$html2 = UniteFunctionsUC::getVal($output, "html2");

			//collect the debug
			if(!empty($htmlDebug))
				$this->contentWidgetsDebug[$elementID] = $htmlDebug;

			if($isGrid == false){
				$arrHTML[$elementID] = $html;
				continue;
			}

			//if case of grid


			$arrOutput = array();
			$arrOutput["html_items"] = $html;

			if(!empty($html2))
				$arrOutput["html_items2"] = $html2;

			$arrHTML[$elementID] = $arrOutput;

		}


		return($arrHTML);
	}


	/**
	 * get init filtres taxonomy request
	 */
	private function getInitFiltersTaxRequest($request, $strTestIDs){

		if(strpos($request, "WHERE 1=2") !== false)
			return("");
		
		//trim the limit
		
		$posLimit = strpos($request, "LIMIT");

		if($posLimit){
			$request = substr($request, 0, $posLimit-1);
			$request = trim($request);
		}

		//remove the calc found rows

		$request = str_replace("SQL_CALC_FOUND_ROWS", "", $request);

		$prefix = UniteProviderFunctionsUC::$tablePrefix;

		$request = str_replace($prefix."posts.*", $prefix."posts.id", $request);

		//wrap it in get term id's request
		
		$arrTermIDs = UniteFunctionsUC::csvToArray($strTestIDs);
		
		if(empty($arrTermIDs))
			return("");

		$selectTerms = "";
		$selectTop = "";

		$query = "SELECT \n";

		foreach($arrTermIDs as $termID){
			
			if(empty($termID))
				continue;	
			
			if(!empty($selectTerms)){
				$selectTerms .= ",\n";
				$selectTop .= ",\n";
			}

			$name = "term_$termID";
			
			$selectTerms .= "SUM(if(tt.`parent` = $termID OR tt.`term_id` = $termID, 1, 0)) AS $name";

			$selectTop .= "SUM(if($name > 0, 1, 0)) as $name";

		}
		
		$query .= $selectTerms;

		$sql = "
			FROM `{$prefix}posts` p
			LEFT JOIN `{$prefix}term_relationships` rl ON rl.`object_id` = p.`id`
			LEFT JOIN `{$prefix}term_taxonomy` tt ON tt.`term_taxonomy_id` = rl.`term_taxonomy_id`
			WHERE rl.`term_taxonomy_id` IS NOT NULL AND p.`id` IN \n
				({$request}) \n
			GROUP BY p.`id`";
		
		$query .= $sql;
		
		$fullQuery = "SELECT $selectTop from($query) as summary";
		
		return($fullQuery);
	}



	/**
	 * modify test term id's
	 */
	private function modifyFoundTermsIDs($arrFoundTermIDs){

		if(isset($arrFoundTermIDs[0]))
			$arrFoundTermIDs = $arrFoundTermIDs[0];

		$arrTermsAssoc = array();

		foreach($arrFoundTermIDs as $strID=>$count){

			$termID = str_replace("term_", "", $strID);

			$arrTermsAssoc[$termID] = $count;
		}

		return($arrTermsAssoc);
	}


	/**
	 * get init filtres author request
	 */
	private function getInitFiltersAuthorRequest($request, $strTestIDs){

		if(strpos($request, "WHERE 1=2") !== false)
			return("");
		
		//trim the limit
		
		$posLimit = strpos($request, "LIMIT");

		if($posLimit){
			$request = substr($request, 0, $posLimit-1);
			$request = trim($request);
		}

		//remove the calc found rows

		$request = str_replace("SQL_CALC_FOUND_ROWS", "", $request);

		$prefix = UniteProviderFunctionsUC::$tablePrefix;

		$request = str_replace($prefix."posts.*", $prefix."posts.id", $request);

		//wrap it in get author id's request
		
		$arrAuthorIDs = UniteFunctionsUC::csvToArray($strTestIDs);
		
		if(empty($arrAuthorIDs))
			return("");

		$selectAuthors = "";
		$selectTop = "";

		$query = "SELECT \n";

		foreach($arrAuthorIDs as $authorID){
			
			if(empty($authorID))
				continue;	
			
			if(!empty($selectAuthors)){
				$selectAuthors .= ",\n";
				$selectTop .= ",\n";
			}

			$name = "author_$authorID";
			
			$selectAuthors .= "SUM(if(p.`post_author` = $authorID, 1, 0)) AS $name";

			$selectTop .= "SUM(if($name > 0, 1, 0)) as $name";

		}
		
		$query .= $selectAuthors;

		$sql = "
			FROM `{$prefix}posts` p
			WHERE p.`id` IN \n
				({$request}) \n
			GROUP BY p.`id`";
		
		$query .= $sql;
		
		$fullQuery = "SELECT $selectTop from($query) as summary";
		
		return($fullQuery);
	}



	/**
	 * modify test author id's
	 */
	private function modifyFoundAuthorsIDs($arrFoundAuthorIDs){

		if(isset($arrFoundAuthorIDs[0]))
			$arrFoundAuthorIDs = $arrFoundAuthorIDs[0];

		$arrAuthorsAssoc = array();

		foreach($arrFoundAuthorIDs as $strID=>$count){

			$authorID = str_replace("author_", "", $strID);

			$arrAuthorsAssoc[$authorID] = $count;
		}

		return($arrAuthorsAssoc);
	}

	
	
	/**
	 * get widget ajax data
	 */
	private function putWidgetGridFrontAjaxData(){
		
		//validate by response code
		
		$responseCode = http_response_code();

		if($responseCode != 200){
			http_response_code(200);
			//$responseCode = (int)$responseCode;
			//UniteFunctionsUC::throwError("Request not allowed, please make sure the ajax is allowed for the ajax grid. the response code is: $responseCode");
		}

		//init widget by post id and element id

		self::$platform = UniteFunctionsUC::getPostGetVariable("platform","",UniteFunctionsUC::SANITIZE_TEXT_FIELD);
		
		self::$isGutenberg = (self::$platform == "gutenberg");
		
		$layoutID = UniteFunctionsUC::getPostGetVariable("layoutid","",UniteFunctionsUC::SANITIZE_KEY);
		$elementID = UniteFunctionsUC::getPostGetVariable("elid","",UniteFunctionsUC::SANITIZE_KEY);
		
		$addElIDs = UniteFunctionsUC::getPostGetVariable("addelids","",UniteFunctionsUC::SANITIZE_TEXT_FIELD);
		$syncIDs = UniteFunctionsUC::getPostGetVariable("syncelids","",UniteFunctionsUC::SANITIZE_TEXT_FIELD);	 //additional grids
		
		//set search element id
		$searchElementID = UniteFunctionsUC::getPostGetVariable("ucsid","",UniteFunctionsUC::SANITIZE_TEXT_FIELD);
		
		if(!empty($searchElementID))
			self::$searchElementID = $searchElementID;
		
		$isModeFiltersInit = UniteFunctionsUC::getPostGetVariable("modeinit","",UniteFunctionsUC::SANITIZE_TEXT_FIELD);
		$isModeFiltersInit = UniteFunctionsUC::strToBool($isModeFiltersInit);
		
		self::$isModeInit = $isModeFiltersInit;
				
		$testTermIDs = UniteFunctionsUC::getPostGetVariable("testtermids","",UniteFunctionsUC::SANITIZE_TEXT_FIELD);
		UniteFunctionsUC::validateIDsList($testTermIDs);
		
		$testAuthorIDs = UniteFunctionsUC::getPostGetVariable("testauthorids","",UniteFunctionsUC::SANITIZE_TEXT_FIELD);
		UniteFunctionsUC::validateIDsList($testAuthorIDs);
				
		//replace terms mode
		$isModeReplace = UniteFunctionsUC::getPostGetVariable("ucreplace","",UniteFunctionsUC::SANITIZE_TEXT_FIELD);
		$isModeReplace = UniteFunctionsUC::strToBool($isModeReplace);
		
		GlobalsProviderUC::$isUnderAjax = true;
		
		self::$isModeReplace = $isModeReplace;
		
		if(self::$isGutenberg == false)
			
			$arrContent = HelperProviderCoreUC_EL::getElementorContentByPostID($layoutID);
			
		else{	//gutenberg
			
			if(class_exists("UniteCreatorGutenbergIntegrate") == false)
				UniteFunctionsUC::throwError("no gutenberg platform enabled");
			
			self::$objGutenberg = new UniteCreatorGutenbergIntegrate();

			$arrContent = self::$objGutenberg->getPostBlocks($layoutID);
		}

		if(empty($arrContent))
			UniteFunctionsUC::throwError(self::$platform." content not found");
		
		self::$arrContent = $arrContent;	//save content

		
		//run the post query
		
		$arrHtmlWidget = $this->getContentWidgetHtml($arrContent, $elementID);
		
		if(empty(GlobalsProviderUC::$lastPostQuery))
			self::$numTotalPosts = 0;
		else
			self::$numTotalPosts = GlobalsProviderUC::$lastPostQuery->found_posts;
		
		//find the term id's for test (find or not in the current posts query)
		if(!empty($testTermIDs)){
			
			if(self::$showDebug == true)
				dmp("---- Test Not Empty Terms----");
						
			$args = GlobalsProviderUC::$lastQueryArgs;
			
			if(self::$showDebug == true){
				dmp("--- Last Query Args:");
				dmp($args);
			}
						
			if(!empty(GlobalsProviderUC::$lastQueryRequest)){
				$request = GlobalsProviderUC::$lastQueryRequest;
			}
			else{
				
				$query = new WP_Query($args);
				
				if (is_wp_error($query)) {
				    $error_message = $query->get_error_message();
				    UniteFunctionsUC::throwError("test terms query failed: ".$error_message);
				}
							
				$request = $query->request;
			}
			
			//some times other hooks distrubting the request
			//clear filters and run again if empty requests
						
			if(empty($request)){
				
				UniteFunctionsWPUC::clearAllWPFilters();
				
				$query = new WP_Query($args);
				$request = $query->request;
			}
						
			if(self::$showDebug == true){
				
				if(empty($request))
					dmp("EMPTY TAX REQUEST!!! - WILL CAUSE ERRORS IN TEST TERMS!");
			}
						
			self::$lastFiltersInitRequest = $request;
						
			$taxRequest = $this->getInitFiltersTaxRequest($request, $testTermIDs);

			if(self::$showDebug == true){
				
				$countLen = strlen($taxRequest);
				
				dmp("---- Test Terms request count: $countLen");
				
				if($countLen < 500){
					dmp("<div style='color:red;'>Note - request count is too low!</div>");
					dmp($taxRequest);
				}
				
			}
			
			$arrFoundTermIDs = array();
	
			if(!empty($taxRequest)){

				$db = HelperUC::getDB();
				try{

					$arrFoundTermIDs = $db->fetchSql($taxRequest);
					$arrFoundTermIDs = $this->modifyFoundTermsIDs($arrFoundTermIDs);

				}catch(Exception $e){
					//just leave it empty
				}
			}
			
			if(self::$showDebug == true){

				dmp("--- result - terms with num posts");
				dmp($arrFoundTermIDs);
			}

			//set the test term id's for the output
			GlobalsProviderUC::$arrTestTermIDs = $arrFoundTermIDs;
		}
		
		//find the author id's for test (find or not in the current posts query)
		if(!empty($testAuthorIDs)){
			
			if(self::$showDebug == true)
				dmp("---- Test Not Empty Authors----");
						
			$args = GlobalsProviderUC::$lastQueryArgs;
			
			if(self::$showDebug == true){
				dmp("--- Last Query Args (Authors):");
				dmp($args);
			}
						
			if(!empty(GlobalsProviderUC::$lastQueryRequest)){
				$request = GlobalsProviderUC::$lastQueryRequest;
			}
			else{
				
				$query = new WP_Query($args);
				
				if (is_wp_error($query)) {
				    $error_message = $query->get_error_message();
				    UniteFunctionsUC::throwError("test authors query failed: ".$error_message);
				}
							
				$request = $query->request;
			}
			
			//some times other hooks distrubting the request
			//clear filters and run again if empty requests
						
			if(empty($request)){
				
				UniteFunctionsWPUC::clearAllWPFilters();
				
				$query = new WP_Query($args);
				$request = $query->request;
			}
						
			if(self::$showDebug == true){
				
				if(empty($request))
					dmp("EMPTY AUTHOR REQUEST!!! - WILL CAUSE ERRORS IN TEST AUTHORS!");
			}
						
			$authorRequest = $this->getInitFiltersAuthorRequest($request, $testAuthorIDs);

			if(self::$showDebug == true){
				
				$countLen = strlen($authorRequest);
				
				dmp("---- Test Authors request count: $countLen");
				
				if($countLen < 100){
					dmp("<div style='color:red;'>Note - request count is too low!</div>");
					dmp($authorRequest);
				}
				
			}
			
			$arrFoundAuthorIDs = array();
	
			if(!empty($authorRequest)){

				$db = HelperUC::getDB();
				try{

					$arrFoundAuthorIDs = $db->fetchSql($authorRequest);
					$arrFoundAuthorIDs = $this->modifyFoundAuthorsIDs($arrFoundAuthorIDs);

				}catch(Exception $e){
					//just leave it empty
				}
			}
			
			if(self::$showDebug == true){

				dmp("--- result - authors with num posts");
				dmp($arrFoundAuthorIDs);
			}

			//set the test author id's for the output
			GlobalsProviderUC::$arrTestAuthorIDs = $arrFoundAuthorIDs;
		}

		$htmlGridItems = UniteFunctionsUC::getVal($arrHtmlWidget, "html");
		$htmlGridItems2 = UniteFunctionsUC::getVal($arrHtmlWidget, "html2");

		//replace widget id
		$widgetHTMLID = UniteFunctionsUC::getVal($arrHtmlWidget, "uc_id");

		if(!empty($widgetHTMLID)){

			$htmlGridItems = str_replace($widgetHTMLID, "%uc_widget_id%", $htmlGridItems);
			$htmlGridItems2 = str_replace($widgetHTMLID, "%uc_widget_id%", $htmlGridItems2);
		}

		$htmlDebug = UniteFunctionsUC::getVal($arrHtmlWidget, "html_debug");
		
		$addWidgetsHTML = $this->getContentWidgetsHTML($arrContent, $addElIDs);

		$syncWidgetsHTML = $this->getContentWidgetsHTML($arrContent, $syncIDs, true);


		//output the html
		$outputData = array();

		if(!empty($htmlDebug))
			$outputData["html_debug"] = $htmlDebug;

		if($isModeFiltersInit == false){
			$outputData["html_items"] = $htmlGridItems;

			$htmlGridItems2 = trim($htmlGridItems2);

			if(!empty($htmlGridItems2))
				$outputData["html_items2"] = $htmlGridItems2;

		}

		if(!empty($addWidgetsHTML))
			$outputData["html_widgets"] = $addWidgetsHTML;

		if(!empty($syncWidgetsHTML))
			$outputData["html_sync_widgets"] = $syncWidgetsHTML;

		if(!empty($this->contentWidgetsDebug))
			$outputData["html_widgets_debug"] = $this->contentWidgetsDebug;

		//add query data
		
		$arrQueryData = HelperUC::$operations->getLastQueryData();
		
		$strQueryPostIDs = HelperUC::$operations->getLastQueryPostIDs();

		$outputData["query_data"] = $arrQueryData;
		$outputData["query_ids"] = $strQueryPostIDs;

		
		if(self::$showEchoDebug == true){

			dmp("The posts: ");
			
			HelperUC::$operations->putPostsCustomFieldsDebug(GlobalsProviderUC::$lastPostQuery->posts);

			dmp("showing the debug");

			exit();
		}

		HelperUC::ajaxResponseData($outputData);

	}

	private function _______AJAX_SEARCH__________(){}
	
	
	/**
	 * before custom posts query
	 * if under ajax search then et main query
	 */
	public function onBeforeCustomPostsQuery($query){

		if(GlobalsProviderUC::$isUnderAjaxSearch == false)
			return(false);

		global $wp_the_query;
		$wp_the_query = $query;
	}


	/**
	 * ajax search
	 */
	private function putAjaxSearchData(){
		
		self::$isUnderAjaxSearch = true;

		$responseCode = http_response_code();

		if($responseCode != 200)
			http_response_code(200);

		define("UE_AJAX_SEARCH_ACTIVE", true);
		
		GlobalsProviderUC::$isUnderAjax = true;
		
		$layoutID = UniteFunctionsUC::getPostGetVariable("layoutid","",UniteFunctionsUC::SANITIZE_KEY);
		$elementID = UniteFunctionsUC::getPostGetVariable("elid","",UniteFunctionsUC::SANITIZE_KEY);

		$arrContent = HelperProviderCoreUC_EL::getElementorContentByPostID($layoutID);
		
		if(empty($arrContent))
			UniteFunctionsUC::throwError("Elementor content not found");

		//run the post query
		GlobalsProviderUC::$isUnderAjaxSearch = true;

		//for outside filters - check that under ajax

		$arrHtmlWidget = $this->getContentWidgetHtml($arrContent, $elementID);

		GlobalsProviderUC::$isUnderAjaxSearch = false;
		
		$htmlGridItems = UniteFunctionsUC::getVal($arrHtmlWidget, "html");
		$htmlGridItems2 = UniteFunctionsUC::getVal($arrHtmlWidget, "html2");

		$htmlDebug = UniteFunctionsUC::getVal($arrHtmlWidget, "html_debug");

		//output the html
		$outputData = array();

		if(!empty($htmlDebug))
			$outputData["html_debug"] = $htmlDebug;

		$outputData["html_items"] = $htmlGridItems;

		$htmlGridItems2 = trim($htmlGridItems2);

		if(!empty($htmlGridItems2))
			$outputData["html_items2"] = $htmlGridItems2;

		//add search suggestion data if no results
		//autocorrection in options
		
		$lastQuery = GlobalsProviderUC::$lastPostQuery;
		
		$isAutoCorrect = UniteFunctionsUC::getVal(GlobalsProviderUC::$lastWidgetParams, "search_autocorrect");
		$isAutoCorrect = UniteFunctionsUC::strToBool($isAutoCorrect);
				
		if($isAutoCorrect == true && !empty($lastQuery) && (int)$lastQuery->found_posts === 0){
						
			$lastQueryArgs = GlobalsProviderUC::$lastQueryArgs;
			$searchTerm = UniteFunctionsUC::getVal($lastQueryArgs, "s");
			$searchTerm = trim($searchTerm);
			
			if(!empty($searchTerm)){
				$objAjaxSearch = new UniteCreatorAjaxSeach();
				$suggestionData = $objAjaxSearch->getSearchSuggestionData($searchTerm, $lastQueryArgs);
				
				if(!empty($suggestionData))
					$outputData["search_suggestion"] = $suggestionData;
			}
		}


		HelperUC::ajaxResponseData($outputData);
	}

	private function _______WIDGET__________(){}


	/**
	 * include the filters js files
	 */
	private function includeJSFiles(){

		if(self::$isFilesAdded == true)
			return(false);

	        if (HelperUC::isEditMode())
	            return false;

		UniteProviderFunctionsUC::addAdminJQueryInclude();

		$urlFiltersJS = GlobalsUC::$url_assets_libraries."filters/ue_filters.js";
		HelperUC::addScriptAbsoluteUrl_widget($urlFiltersJS, "ue_filters");

		self::$isFilesAdded = true;
	}

	/**
	 * put custom scripts
	 */
	private function putCustomJsScripts(){

		if(self::$isScriptAdded == true)
			return(false);

		self::$isScriptAdded = true;
		
		$arrData = $this->getFiltersJSData();

		$strData = UniteFunctionsUC::jsonEncodeForClientSide($arrData);

		$script = "/* Unlimited Elements Filters */ \n";
		$script .= "window.g_strFiltersData = {$strData};";

		UniteProviderFunctionsUC::printCustomScript($script);
	}

	/**
	 * put custom style
	 */
	private function putCustomStyle(){

		if(self::$isStyleAdded == true)
			return(false);

		self::$isStyleAdded = true;

		$style = "
			.uc-ajax-loading{
				opacity:0.6;
			}
		";

		UniteProviderFunctionsUC::printCustomStyle($style);
	}


	/**
	 * include the client side scripts
	 */
	private function includeClientSideScripts(){

		$isInsideEditor = GlobalsProviderUC::$isInsideEditor;

		if($isInsideEditor == true)
			return(false);

		$this->includeJSFiles();

		$this->putCustomJsScripts();

		$this->putCustomStyle();

	}



	/**
	 * get active archive terms
	 */
	private function getActiveArchiveTerms($taxonomy){

		if(is_archive() == false)
			return(null);

		$currentTerm = $this->getCurrentTerm();

		if(empty($currentTerm))
			return(null);

		if($currentTerm instanceof WP_Term == false)
			return(null);

		$termID = $currentTerm->term_id;

		$args = array();
		$args["taxonomy"] = $taxonomy;
		$args["parent"] = $termID;

		$arrTerms = get_terms($args);

		return($arrTerms);
	}



	/**
	 * add values to settings from data
	 * postIDs - exists only if avoid duplicates option set
	 */
	public function addWidgetFilterableVarsFromData($data, $dataPosts, $postListName, $arrPostIDs, $numPosts){
		
		if(empty($arrPostIDs))
			$arrPostIDs = null;
		
		//check if ajax related
		$isAjax = UniteFunctionsUC::getVal($dataPosts, $postListName."_isajax");
		$isAjax = UniteFunctionsUC::strToBool($isAjax);

		$addClass = "";
		$strAttributes = "";

		//avoid duplicates handle

		if(!empty($arrPostIDs)){

			$addClass = " uc-avoid-duplicates";

			$strPostIDs = implode(",", $arrPostIDs);
			$strAttributes = " data-postids='$strPostIDs'";

		}

		if($isAjax == false){

			$data["uc_filtering_attributes"] = $strAttributes;
			$data["uc_filtering_addclass"] = $addClass;

			return($data);
		}
		
		
		//all ajax related

		$addClass .= " uc-filterable-grid";

		$filterBehavoiur = UniteFunctionsUC::getVal($dataPosts, $postListName."_ajax_seturl");

		$strAttributes .= " data-ajax='true' ";

		if(!empty($filterBehavoiur))
			$strAttributes .= " data-filterbehave='$filterBehavoiur' ";

		//add ajax group

		$filterGroup = UniteFunctionsUC::getVal($dataPosts, $postListName."_filtering_group");

		if(!empty($filterGroup)){
			$filterGroup = esc_attr($filterGroup);
			$strAttributes .= " data-filtergroup='$filterGroup' ";
		}

		//add last query
		
		$arrQueryData = HelperUC::$operations->getLastQueryData();
		
		
		$jsonQueryData = UniteFunctionsUC::jsonEncodeForHtmlData($arrQueryData);
		
		$strAttributes .= " querydata='$jsonQueryData'";

		$this->includeClientSideScripts();

		$data["uc_filtering_attributes"] = $strAttributes;
		$data["uc_filtering_addclass"] = $addClass;
		
		return($data);
	}

	/**
	 * add widget variables
	 * uc_listing_addclass, uc_listing_attributes
	 */
	public function addWidgetFilterableVariables($data, $addon, $arrPostIDs, $numPosts){
		
		if(empty($arrPostIDs))
			$arrPostIDs = array();
		
		$param = $addon->getParamByType(UniteCreatorDialogParam::PARAM_POSTS_LIST);

		if(empty($param))
			return($data);

		$postListName = UniteFunctionsUC::getVal($param, "name");

		$dataPosts = UniteFunctionsUC::getVal($data, $postListName);

		$data = $this->addWidgetFilterableVarsFromData($data, $dataPosts, $postListName, $arrPostIDs, $numPosts);
		
		return($data);
	}

	/**
	 * default sign is "~"
	 *
	 */
	private function getUrlPartsKeys(){

		$taxSapSetting = HelperProviderCoreUC_EL::getGeneralSetting("tax_sap_sign");

		$taxSapSetting = apply_filters("ue_filters_url_key__taxonomy_sap", $taxSapSetting);
		
		if(empty($taxSapSetting))
			$taxSapSetting = "~";
		
		$arrParts = array();
		$arrParts["tax_sap"] = apply_filters("ue_filters_url_key__taxonomy_sap", $taxSapSetting);

		return($arrParts);
	}

	/**
	 * get filters attributes
	 * get the base url
	 */
	private function getFiltersJSData(){
		
		$urlBase = UniteFunctionsUC::getBaseUrl(GlobalsUC::$current_page_url, true);		//strip pagination

		//include some common url filters
		$orderby = UniteFunctionsUC::getGetVar("orderby","",UniteFunctionsUC::SANITIZE_TEXT_FIELD);

		if(!empty($orderby)){
			$orderby = urlencode($orderby);
			$urlBase = UniteFunctionsUC::addUrlParams($urlBase, "orderby=$orderby");
		}

		//include the search if exists

		$search = UniteFunctionsUC::getGetVar("s","",UniteFunctionsUC::SANITIZE_TEXT_FIELD);

		if(empty($search)){
			$search = null;

			if(isset($_GET["s"]) && $_GET["s"] == "")
				$search = "";
		}

		if($search !== null){
			$search = urlencode($search);
			$urlBase = UniteFunctionsUC::addUrlParams($urlBase, "s=$search");
		}

		//include lang if exists
		
		$lang = UniteFunctionsUC::getGetVar("lang","",UniteFunctionsUC::SANITIZE_TEXT_FIELD);
		
		if(!empty($lang)){
			$lang = urlencode($lang);
			$urlBase = UniteFunctionsUC::addUrlParams($urlBase, "lang=$lang");
		}

		// Preserve URL query-debug flags on filter AJAX (urlbase drives GET requests in ue_filters.js).
		$ucQueryDebug = UniteFunctionsUC::getGetVar("ucquerydebug","",UniteFunctionsUC::SANITIZE_TEXT_FIELD);
		if(UniteFunctionsUC::strToBool($ucQueryDebug) == true)
			$urlBase = UniteFunctionsUC::addUrlParams($urlBase, "ucquerydebug=1");

		$ucQueryDebugTerms = UniteFunctionsUC::getGetVar("ucquerydebug_terms","",UniteFunctionsUC::SANITIZE_TEXT_FIELD);
		if(UniteFunctionsUC::strToBool($ucQueryDebugTerms) == true)
			$urlBase = UniteFunctionsUC::addUrlParams($urlBase, "ucquerydebug_terms=1");

		$ucTestChangearg = UniteFunctionsUC::getGetVar("uctestquery_changearg", "", UniteFunctionsUC::SANITIZE_NOTHING);
		if(UniteFunctionsWPUC::isCurrentUserHasPermissions() == true && trim($ucTestChangearg) !== "")
			$urlBase = UniteFunctionsUC::addUrlParams($urlBase, "uctestquery_changearg=".rawurlencode($ucTestChangearg));

		
		//debug client url

		$isDebug = UniteFunctionsUC::getGetVar("ucfiltersdebug","",UniteFunctionsUC::SANITIZE_TEXT_FIELD);
		$isDebug = UniteFunctionsUC::strToBool($isDebug);
		
		//ucpage for pagination init
		$ucpage = UniteFunctionsUC::getGetVar("ucpage","",UniteFunctionsUC::SANITIZE_TEXT_FIELD);
		if(is_numeric($ucpage) == false || $ucpage < 0)
			$ucpage = null;
		
		
		//get url parts
		$arrUrlKeys = $this->getUrlPartsKeys();

		//get current filters

		$arrData = array();
		$arrData["platform"] = GlobalsProviderUC::$renderPlatform;
		$arrData["urlbase"] = $urlBase;
		$arrData["urlajax"] = GlobalsUC::$url_ajax_full;
		$arrData["urlkeys"] = $arrUrlKeys;
		$arrData["postid"] = get_the_id();
		
		if(!empty($ucpage))
			$arrData["ucpage"] = $ucpage;
		
		if($isDebug == true)
			$arrData["debug"] = true;

		return($arrData);
	}

	
	private function _____MODIFY_PARAMS_PROCESS_AUTHORS_______(){}
	
	
	/**
	 * modify items (terms/authors) set num posts - unified function
	 */
	private function modifyOutputItems_setNumPosts($arrItems, $arrTestIDs, $isTerms = false){
		
		if(empty($arrItems))
			return($arrItems);
		
		if($arrTestIDs === null)
			return($arrItems);
				
		foreach($arrItems as $key => $item){
			
			$itemID = UniteFunctionsUC::getVal($item, "id");

			$itemFound = array_key_exists($itemID, $arrTestIDs);

			$numPosts = 0;

			if($itemFound){
				$numPosts = $arrTestIDs[$itemID];
			}

			//set the number of posts
			$item["num_posts"] = $numPosts;
			
			//for terms, also set num_products if exists
			if($isTerms == true && !empty($item["num_products"])){
				$item["num_products"] = $numPosts;
			}

			$isHidden = !$itemFound;

			if($numPosts == 0)
				$isHidden = true;

			$htmlAttributes = "";
			$htmlAttributesNew = "";

			if($isHidden == true){
				$htmlAttributes = "hidden='hidden' style='display:none'";
				$htmlAttributesNew = "hidden='hidden' ";	//no style

				$addClass = UniteFunctionsUC::getVal($item, "addclass");
				$addClass .= " uc-item-hidden";

				$item["addclass"] = $addClass;
			}

			$item["hidden"] = $isHidden;
			$item["html_attributes"] = $htmlAttributes;
			$item["html_attributes2"] = $htmlAttributesNew;

			$arrItems[$key] = $item;
		}

		return($arrItems);
	}
	
	
	/**
	 * modify the authors for init after
	 */
	private function modifyOutputAuthors_setNumPosts($arrAuthors){
	
		return $this->modifyOutputTerms_setNumPosts($arrAuthors, true);
	}


	/**
	 * add first item to authors array
	 */
	private function modifyOutputAuthors_addFirstItem($arrAuthors, $data, $filterType){

		//don't add first item if no authors, if no authors, no "all" as well

		if(empty($arrAuthors))
			return(array());

		$addFirst = UniteFunctionsUC::getVal($data, "add_first");
		$addFirst = UniteFunctionsUC::strToBool($addFirst);

		if($addFirst == false)
			return($arrAuthors);
	
		$text = UniteFunctionsUC::getVal($data, "first_item_text_authors", __("All","unlimited-elements-for-elementor"));
		
		$firstAuthor = array();
		$firstAuthor["index"] = 0;
		$firstAuthor["name"] = $text;
		$firstAuthor["id"] = "";
		$firstAuthor["slug"] = "";
		$firstAuthor["link"] = "";
		$firstAuthor["username"] = "";
		$firstAuthor["user_login"] = "";

		$firstAuthor["addclass"] = " uc-item-all";
		
		if(!empty(self::$numTotalPosts))
			$firstAuthor["num_posts"] = self::$numTotalPosts;
		
		array_unshift($arrAuthors, $firstAuthor);

		return($arrAuthors);
	}


	/**
	 * set selected class by options for authors
	 */
	private function modifyOutputAuthors_setSelectedClass($arrAuthors, $filterType){
		
		if(empty($arrAuthors))
			return($arrAuthors);

		foreach($arrAuthors as $index => $author){

			$isSelected = UniteFunctionsUC::getVal($author, "isselected");
			$isSelected = UniteFunctionsUC::strToBool($isSelected);

			if($isSelected == false)
				continue;

			//hidden can't be selected

			$isHidden = UniteFunctionsUC::getVal($author, "hidden");
			$isHidden = UniteFunctionsUC::strToBool($isHidden);

			if($isHidden == true)
				continue;

			$class = UniteFunctionsUC::getVal($author, "addclass","");
			$class .= " uc-selected";

			$author["addclass"] = $class;

			//set select attribute
			switch($filterType){
				case self::TYPE_SELECT:
				
					$htmlAttributes = UniteFunctionsUC::getVal($author, "html_attributes");
				
					if(empty($htmlAttributes))
						$htmlAttributes = "";

					$htmlAttributes .= " selected";

					$author["html_attributes"] = $htmlAttributes;

				break;
				case self::TYPE_CHECKBOX:

					$author["html_attributes_input"] = " checked";

				break;
			}

			$arrAuthors[$index] = $author;

		}


		return($arrAuthors);
	}

	/**
	 * get data attributes for authors
	 */
	private function modifyOutputAuthors_getDataAttributes($arrAuthors, $filterType){
		
		if(empty($arrAuthors))
			return($arrAuthors);
		
		foreach($arrAuthors as $index => $author){

			$authorID = UniteFunctionsUC::getVal($author, "id");

			if(empty($authorID))
				continue;
			 
			$title = UniteFunctionsUC::getVal($author, "name");
			$username = UniteFunctionsUC::getVal($author, "username");
			$userLogin = UniteFunctionsUC::getVal($author, "user_login");
			
			// Use username or user_login as slug equivalent
			$slug = !empty($username) ? $username : $userLogin;

			$type = "author";

			$title = esc_attr($title);
			$slug = esc_attr($slug);

			$key = "{$type}|{$slug}";

			$htmlData = " data-id=\"$authorID\" data-type=\"$type\" data-username=\"$slug\" data-title=\"{$title}\" data-key=\"{$key}\" ";
			
			$author["html_data"] = $htmlData;

			$arrAuthors[$index] = $author;
		}

		return($arrAuthors);
	}
	
	private function _____MODIFY_PARAMS_PROCESS_TERMS_______(){}


	/**
	 * flatten slugs array that can contain nested groups / relations
	 */
	private function flattenTermSlugs($arrSlugs){

		if(empty($arrSlugs))
			return(array());

		$result = array();

		foreach($arrSlugs as $key => $value){

			//skip relation markers
			if($key === "relation")
				continue;

			//simple slug value
			if(!is_array($value)){
				if($value !== "" && $value !== null)
					$result[] = $value;

				continue;
			}

			//nested group flatten recursively
			$nested = $this->flattenTermSlugs($value);

			if(!empty($nested))
				$result = array_merge($result, $nested);
		}

		return($result);
	}


	/**
	 * check if term selected by request
	 */
	private function isTermSelectedByRequest($term, $selectedTerms){

		$taxonomy = UniteFunctionsUC::getVal($term, "taxonomy");

		if(empty($taxonomy))
			return(false);

		$arrSlugs = UniteFunctionsUC::getVal($selectedTerms, $taxonomy);

		if(empty($arrSlugs))
			return(false);

		$slug = UniteFunctionsUC::getVal($term, "slug");

		// $arrSlugs can contain nested arrays for grouped OR conditions (|a.b|),
		// so flatten it before searching.
		if(is_array($arrSlugs))
			$arrSlugsFlat = $this->flattenTermSlugs($arrSlugs);
		else
			$arrSlugsFlat = array($arrSlugs);
		
		$found = in_array($slug, $arrSlugsFlat, true);

		return($found);
	}


	/**
	 * modify selected by request
	 */
	private function modifyOutputTerms_modifySelectedByRequest($arrTerms){
		
		if(empty($arrTerms))
			return($arrTerms);
		
		$this->hasSelectedByRequest = false;

		$selectedTerms = null;
		$selectedTermIDs = null;
		
		//if mode init - get selected id's from request
		if(self::$isModeInit == true){
			
			$strSelectedTermIDs = UniteFunctionsUC::getPostGetVariable("ucinitselectedterms","",UniteFunctionsUC::SANITIZE_TEXT_FIELD);
			
			if(empty($strSelectedTermIDs))
				return($arrTerms);

			UniteFunctionsUC::validateIDsList($strSelectedTermIDs,"selected terms");
			
			$selectedTermIDs = explode(",", $strSelectedTermIDs);

			if(empty($selectedTermIDs))
				return($arrTerms);

		}else{

			$arrRequest = $this->getRequestFilters();

			if(empty($arrRequest))
				return($arrTerms);

			$selectedTerms = UniteFunctionsUC::getVal($arrRequest, "terms");

			if(empty($selectedTerms))
				return($arrTerms);

		}

		$arrSelected = array();

		foreach($arrTerms as $index => $term){
		
			if(!empty($selectedTerms))
				$isSelected = $this->isTermSelectedByRequest($term, $selectedTerms);
			else{

				$termID = UniteFunctionsUC::getVal($term, "id");
				if(empty($termID))
					continue;

				$isSelected = in_array($termID, $selectedTermIDs);
			}

			if($isSelected == false)
				continue;


			$arrSelected["term_".$index] = true;
		}

		if(empty($arrSelected))
			return($arrTerms);

		$this->hasSelectedByRequest = true;
		
		//modify the selected

		foreach($arrTerms as $index => $term){

			$isSelected = UniteFunctionsUC::getVal($arrSelected, "term_".$index);

			if($isSelected == true){
				$term["iscurrent"] = true;
				$term["isselected"] = true;

			}else{

				$term["iscurrent"] = false;
				$term["isselected"] = false;
				$term["class_selected"] = "";
			}

			$arrTerms[$index] = $term;
		}

		return($arrTerms);
	}


	/**
	 * modify filters - add first item
	 */
	private function modifyOutputTerms_addFirstItem($arrTerms, $data, $filterType){

		//don't add first item if no terms, if no terms, no "all" as well

		if(empty($arrTerms))
			return(array());

		$addFirst = UniteFunctionsUC::getVal($data, "add_first");
		$addFirst = UniteFunctionsUC::strToBool($addFirst);

		if($addFirst == false)
			return($arrTerms);

		$text = UniteFunctionsUC::getVal($data, "first_item_text", __("All","unlimited-elements-for-elementor"));

		$firstTerm = array();
		$firstTerm["index"] = 0;
		$firstTerm["name"] = $text;
		$firstTerm["slug"] = "";
		$firstTerm["link"] = "";
		$firstTerm["parent_id"] = "";
		$firstTerm["taxonomy"] = "";

		$firstTerm["addclass"] = " uc-item-all";

		if(!empty(self::$numTotalPosts))
			$firstTerm["num_posts"] = self::$numTotalPosts;

		array_unshift($arrTerms, $firstTerm);

		return($arrTerms);
	}


	/**
	 * modify the selected - first selected from options
	 */
	private function modifyOutputTerms_modifySelected($arrTerms, $data, $filterType){

		if(empty($arrTerms))
			return($arrTerms);

		$isSelectFirst = UniteFunctionsUC::getVal($data, "select_first");
		$isSelectFirst = UniteFunctionsUC::strToBool($isSelectFirst);

		if($filterType == self::TYPE_SELECT)
			$isSelectFirst = true;

		if($filterType == self::TYPE_TABS){

			$role = UniteFunctionsUC::getVal($data, "filter_role");

			if(strpos($role, self::ROLE_CHILD) !== false)
				$isSelectFirst = false;
		}

		if($isSelectFirst == false)
			return($arrTerms);

		$numSelectedTab = UniteFunctionsUC::getVal($data, "selected_tab_number");
		if(empty($numSelectedTab))
			$numSelectedTab = 1;
		
		//correct selected tab

		$numTerms = count($arrTerms);

		if($isSelectFirst == true && $numSelectedTab > $numTerms)
			$numSelectedTab = 1;

		$firstNotHiddenIndex = null;

		$hasSelectedTerm = false;

		foreach($arrTerms as $index => $term){

			//set the index
			$numTab = ($index + 1);

			$term["index"] = $index;

			//check if hidden

			$isHidden = UniteFunctionsUC::getVal($term, "hidden");
			$isHidden = UniteFunctionsUC::strToBool($isHidden);

			if($isHidden == true)
				continue;

			if($firstNotHiddenIndex === null)
				$firstNotHiddenIndex = $index;

			if($numTab == $numSelectedTab){
				$term["isselected"] = true;
				$hasSelected = true;
			}

			$arrTerms[$index] = $term;
		}

		if($hasSelected == true)
			return($arrTerms);

		if($firstNotHiddenIndex === null)
			return($arrTerms);

		if($filterType != self::TYPE_SELECT)
			return($arrTerms);

		//make sure the first item selected in select filter
		if($isSelectFirst == true)
			$arrTerms[$firstNotHiddenIndex]["isselected"] = true;


		return($arrTerms);
	}


	/**
	 * modify the terms for init after
	 */
	private function modifyOutputTerms_setNumPosts($arrTerms, $isAuthors = false){
		
		if(empty($arrTerms))
			return($arrTerms);
		
		$arrTestIDs = $isAuthors ? GlobalsProviderUC::$arrTestAuthorIDs : GlobalsProviderUC::$arrTestTermIDs;
		$isTerms = ($isAuthors == false);
		
		if($arrTestIDs === null)
			return($arrTerms);
		
		return $this->modifyOutputItems_setNumPosts($arrTerms, $arrTestIDs, $isTerms);
	}


	/**
	 * modify limit loaded items
	 */
	private function modifyOutputTerms_tabs_modifyLimitGrayed($arrTerms, $limitGrayedItems){

		if(empty($limitGrayedItems))
			return($arrTerms);

		$numTerms = count($arrTerms);

		if($numTerms < $limitGrayedItems)
			return($arrTerms);

		foreach($arrTerms as $index => $term){

			if($index < $limitGrayedItems)
				continue;

			$addClass = UniteFunctionsUC::getVal($term, "addclass");
			$addClass .= " uc-hide-loading-item";

			$term["addclass"] = $addClass;

			$arrTerms[$index] = $term;
		}


		return($arrTerms);
	}

	/**
	 * set selected class by options
	 */
	private function modifyOutputTerms_setSelectedClass($arrTerms, $filterType){
		
		if(empty($arrTerms))
			return($arrTerms);

		foreach($arrTerms as $index => $term){

			$isSelected = UniteFunctionsUC::getVal($term, "isselected");
			$isSelected = UniteFunctionsUC::strToBool($isSelected);

			if($isSelected == false)
				continue;

			//hidden can't be selected

			$isHidden = UniteFunctionsUC::getVal($term, "hidden");
			$isHidden = UniteFunctionsUC::strToBool($isHidden);

			if($isHidden == true)
				continue;

			$class = UniteFunctionsUC::getVal($term, "addclass","");
			$class .= " uc-selected";

			$term["addclass"] = $class;

			//set select attribute
			switch($filterType){
				case self::TYPE_SELECT:

					$htmlAttributes = UniteFunctionsUC::getVal($term, "html_attributes");

					if(empty($htmlAttributes))
						$htmlAttributes = "";

					$htmlAttributes .= " selected";

					$term["html_attributes"] = $htmlAttributes;

				break;
				case self::TYPE_CHECKBOX:

					$term["html_attributes_input"] = " checked";

				break;
			}

			//set hasSelected - true, only if there are some selected slug

			$selectedSlug = UniteFunctionsUC::getVal($term, "slug");

			if(!empty($selectedSlug))
				$this->hasSelectedTerm = true;

			$arrTerms[$index] = $term;

		}


		return($arrTerms);
	}


	/**
	 * check if filter should be hidden, if selected items avaliable
	 * only for select filters / child role and under ajax
	 */
	private function modifyOutputTerms_isFilterHidden($data, $arrTerms, $isUnderAjax){

		if($isUnderAjax == false)
			return(false);

		$role = UniteFunctionsUC::getVal($data, "filter_role");
				
		if($role != self::ROLE_CHILD)
			return(false);

		if(empty($arrTerms))
			return(true);

		//get number of not hidden items

		$numItems = 0;

		foreach($arrTerms as $term){

			$isHidden = UniteFunctionsUC::getVal($term, "hidden");
			$isHidden = UniteFunctionsUC::strToBool($isHidden);

			if($isHidden == true)
				continue;

			$numItems++;
		}

		if($numItems > 1)
			return(false);

		$firstItem = $arrTerms[0];

		$slug = UniteFunctionsUC::getVal($firstItem, "slug");

		$isAllItem = empty($slug);

		//if there is only "all" item, it should be hidden as well

		if($isAllItem == true)
			return(true);

		return(false);
	}

	/**
	 * get data attributes
	 */
	private function modifyOutputTerms_getDataAttributes($arrTerms, $filterType){
		
		if(empty($arrTerms))
			return($arrTerms);
		
		foreach($arrTerms as $index => $term){

			$termID = UniteFunctionsUC::getVal($term, "id");

			if(empty($termID))
				continue;

			$title = UniteFunctionsUC::getVal($term, "name");
			$slug = UniteFunctionsUC::getVal($term, "slug");
			$taxonomy = UniteFunctionsUC::getVal($term, "taxonomy");

			$type = "term";

			$title = esc_attr($title);
			$slug = esc_attr($slug);
			$taxonomy = esc_attr($taxonomy);

			$key = "{$type}|{$taxonomy}|{$slug}";

			$htmlData = " data-id=\"$termID\" data-type=\"$type\" data-slug=\"$slug\" data-taxonomy=\"$taxonomy\" data-title=\"{$title}\" data-key=\"{$key}\" ";

			$term["html_data"] = $htmlData;

			$arrTerms[$index] = $term;
		}

		return($arrTerms);
	}


	/**
	 * convert metaselect repeater rows to filter items
	 */
	private function modifyOutputMetaSelect_getItems($data){

		$metaSelect = UniteFunctionsUC::getVal($data, "metaselect");

		$arrRows = array();

		if(is_array($metaSelect)){

			$arrRows = UniteFunctionsUC::getVal($metaSelect, "items");

			if(empty($arrRows))
				$arrRows = UniteFunctionsUC::getVal($metaSelect, "metaselect_items");

			//already converted flat list of items
			if(empty($arrRows)){
				$first = reset($metaSelect);

				if(is_array($first) && (isset($first["meta_key"]) || isset($first["title"]) || isset($first["name"])))
					$arrRows = $metaSelect;
			}
		}

		if(empty($arrRows))
			$arrRows = UniteFunctionsUC::getVal($data, "metaselect_items");

		if(empty($arrRows) || is_array($arrRows) == false)
			return(array());

		$arrItems = array();

		foreach($arrRows as $index => $row){

			if(is_array($row) == false)
				continue;

			$title = UniteFunctionsUC::getVal($row, "title");
			if(empty($title))
				$title = UniteFunctionsUC::getVal($row, "name");

			$metaKey = UniteFunctionsUC::getVal($row, "meta_key");
			if(empty($metaKey))
				$metaKey = UniteFunctionsUC::getVal($row, "slug");

			$metaValue = UniteFunctionsUC::getVal($row, "meta_value");

			if(empty($title) && empty($metaKey))
				continue;

			$id = UniteFunctionsUC::getVal($row, "_id");
			if(empty($id))
				$id = $metaKey;

			$item = array();
			$item["index"] = $index;
			$item["id"] = $id;
			$item["name"] = $title;
			$item["slug"] = $metaKey;
			$item["meta_key"] = $metaKey;
			$item["meta_value"] = $metaValue;
			$item["link"] = "";
			$item["taxonomy"] = "";
			$item["addclass"] = "";

			$arrItems[] = $item;
		}

		return($arrItems);
	}


	/**
	 * get data attributes for metaselect items
	 */
	private function modifyOutputMetaSelect_getDataAttributes($arrItems, $filterType){

		if(empty($arrItems))
			return($arrItems);

		foreach($arrItems as $index => $item){

			$id = UniteFunctionsUC::getVal($item, "id");
			$metaKey = UniteFunctionsUC::getVal($item, "meta_key");
			$metaValue = UniteFunctionsUC::getVal($item, "meta_value");
			$slug = UniteFunctionsUC::getVal($item, "slug", $metaKey);
			$title = UniteFunctionsUC::getVal($item, "name");

			if(empty($id) && empty($metaKey))
				continue;

			$type = "meta";

			$title = esc_attr($title);
			$slug = esc_attr($slug);
			$metaKey = esc_attr($metaKey);
			$metaValue = esc_attr($metaValue);
			$id = esc_attr($id);

			$key = "{$type}|{$metaKey}";
			if($metaValue !== "")
				$key .= ":{$metaValue}";

			$htmlData = " data-id=\"$id\" data-type=\"$type\" data-slug=\"$slug\" data-metakey=\"$metaKey\" data-metavalue=\"$metaValue\" data-title=\"{$title}\" data-key=\"{$key}\" ";

			$item["html_data"] = $htmlData;

			$arrItems[$index] = $item;
		}

		return($arrItems);
	}


	/**
	 * get filter attributes for search filter
	 */
	private function addEditorFilterArguments_search($data){
		
		//pass advanced search or nothing
		
		$hasSpecialArgs = UniteCreatorAjaxSeach::isSearchFilterHasSpecialArgs($data);
				
		if($hasSpecialArgs == true)
			$data["filter_attributes"] = "data-advancedsearch='true' ";
		else
			$data["filter_attributes"] = "";
		
		return($data);		
	}


	/**
	 * get editor filter arguments
	 */
	public function addEditorFilterArguments($data, $typeArg){
		
		$filterType = self::TYPE_TABS;
		
		switch($typeArg){
			case "type_select":
				$filterType = self::TYPE_SELECT;
			break;
			case "type_checkbox":
				$filterType = self::TYPE_CHECKBOX;
			break;
			case "type_search":
				
				$data = $this->addEditorFilterArguments_search($data);
				
				return($data);								
			break;
		}


		//add the filter related js and css includes
		$this->includeClientSideScripts();
		
		$isInitAfter = UniteFunctionsUC::getVal($data, "init_after");
		$isInitAfter = UniteFunctionsUC::strToBool($isInitAfter);

		$isReplaceTerms = UniteFunctionsUC::getVal($data, "replace_terms");
		$isReplaceTerms = UniteFunctionsUC::strToBool($isReplaceTerms);

		$limitGrayedItems = UniteFunctionsUC::getVal($data, "load_limit_grayed");
		$limitGrayedItems = (int)$limitGrayedItems;

		$showNumberOfPosts = UniteFunctionsUC::getVal($data, "show_number_of_posts");
		$showNumberOfPosts = UniteFunctionsUC::strToBool($showNumberOfPosts);
		
		$filterSource = UniteFunctionsUC::getVal($data, "filter_source");
		
		$isAuthors = ($filterSource == "authors");
		$isMeta = ($filterSource == "meta");
		
		if($isAuthors == true && $showNumberOfPosts == true)
			$isInitAfter = true;
		
		$filterRole = UniteFunctionsUC::getVal($data, "filter_role");
		if($filterRole == "single")
			$filterRole = "";

		$attributes = "";
		$style = "";
		$addClass = " uc-grid-filter";
		$addClassItem = "";
		$isFirstLoad = true;		//not in ajax, or with init after (also first load)

		$connectGroup = UniteFunctionsUC::getVal($data, "connect_group");

		if($connectGroup == "auto")
			$connectGroup = null;

		$isInsideEditor = GlobalsProviderUC::$isInsideEditor;

		$isUnderAjax = self::$isUnderAjax;

		if($isUnderAjax == true)
			$isFirstLoad = false;
		
		if($isInitAfter == true){
			
			$attributes = " data-initafter=\"true\"";

			if($isUnderAjax == false && $isInsideEditor == false){
				$addClassItem = " uc-filter-item-hidden";
				$addClass .= " uc-filter-initing";
			}

			$isFirstLoad = true;
		}
		
		if($filterRole == self::ROLE_TERM_CHILD){

			$termID = UniteFunctionsUC::getVal($data, "child_termid");

			if(!empty($termID))
				$attributes .= " data-childterm=\"$termID\"";
		}

		if(!empty($connectGroup))
			$attributes .= " data-connectgroup=\"$connectGroup\"";
		
		if($isInsideEditor == true)
			$isFirstLoad = true;

		//main filter

		if(!empty($filterRole))
			$attributes .= " data-role=\"{$filterRole}\"";

		if($isReplaceTerms == true)
			$attributes .= " data-replace-mode=\"true\"";

		
		if($isAuthors == true)
			$arrItems = UniteFunctionsUC::getVal($data, "authors");
		elseif($isMeta == true)
			$arrItems = $this->modifyOutputMetaSelect_getItems($data);
		else
			$arrItems = UniteFunctionsUC::getVal($data, "taxonomy");
		
		//modify the hidden as well
		if($isMeta == false)
			$arrItems = $this->modifyOutputTerms_setNumPosts($arrItems, $isAuthors);
		
		//modify the selected class - add first
		if($isAuthors == true)
			$arrItems = $this->modifyOutputAuthors_addFirstItem($arrItems, $data, $filterType);
		else
			$arrItems = $this->modifyOutputTerms_addFirstItem($arrItems, $data, $filterType);
		
		//modify the selected class
		$arrItems = $this->modifyOutputTerms_modifySelected($arrItems, $data,$filterType);
		
		//for terms only
		if($isAuthors == false && $isMeta == false)
			$arrItems = $this->modifyOutputTerms_modifySelectedByRequest($arrItems);
		
		$isFilterHidden = false;

		switch($filterType){
			case self::TYPE_TABS:
			case self::TYPE_CHECKBOX:

				if($isInitAfter == true && !empty($limitGrayedItems) && $isUnderAjax == false)
					$arrItems = $this->modifyOutputTerms_tabs_modifyLimitGrayed($arrItems, $limitGrayedItems);

				$isFilterHidden = $this->modifyOutputTerms_isFilterHidden($data, $arrItems, $isUnderAjax);
		
			break;
			case self::TYPE_SELECT:

				//modify if hidden

				$isFilterHidden = $this->modifyOutputTerms_isFilterHidden($data, $arrItems, $isUnderAjax);
			break;
		}

		if($isAuthors == true)
			$arrItems = $this->modifyOutputAuthors_setSelectedClass($arrItems, $filterType);
		else
			$arrItems = $this->modifyOutputTerms_setSelectedClass($arrItems, $filterType);
		
		if($isAuthors == true)
			$arrItems = $this->modifyOutputAuthors_getDataAttributes($arrItems, $filterType);
		elseif($isMeta == true)
			$arrItems = $this->modifyOutputMetaSelect_getDataAttributes($arrItems, $filterType);
		else
			$arrItems = $this->modifyOutputTerms_getDataAttributes($arrItems, $filterType);
		
		
		//hide child filter at start

		if(strpos($filterRole,"child") !== false &&
		   $isUnderAjax == false &&
		   $isInsideEditor == false){

			$addClass .= " uc-filter-initing uc-initing-filter-hidden";
		}

		if($this->hasSelectedTerm == true)
			$addClass .= " uc-has-selected";

		if($isFilterHidden)
			$addClass .= " uc-filter-hidden";
		
		$data["filter_isajax"] = $isUnderAjax?"yes":"no";
		$data["filter_attributes"] = $attributes;
		$data["filter_style"] = $style;
		$data["filter_addclass"] = $addClass;
		$data["filter_addclass_item"] = $addClassItem;
		$data["filter_first_load"] = $isFirstLoad?"yes":"no";
		
		if($isAuthors)
			$data["authors"] = $arrItems;
		elseif($isMeta)
			$data["metaselect"] = $arrItems;
		else
			$data["taxonomy"] = $arrItems;
		
			
		return($data);
	}


	private function _______MAIN__________(){}


	/**
	 * show the main query debug
	 */
	private function showMainQueryDebug(){

		global $wp_query;

		$args = $wp_query->query_vars;

		$argsForDebug = UniteFunctionsWPUC::cleanQueryArgsForDebug($args);

		dmp("MAIN QUERY DEBUG");

		dmp($argsForDebug);

	}

	/**
	 * is ajax request
	 */
	public function isFrontAjaxRequest(){

		if(self::$isAjaxCache !== null)
			return(self::$isAjaxCache);

		$frontAjaxAction = UniteFunctionsUC::getPostGetVariable("ucfrontajaxaction","",UniteFunctionsUC::SANITIZE_KEY);

		if($frontAjaxAction == "getfiltersdata"){
			self::$isAjaxCache = true;
			return(true);
		}

		self::$isAjaxCache = false;

		return(false);
	}

	/**
	 * just return true
	 */
	public function pluginProtection_ezCacheHideComment(){

		return(true);
	}


	/**
	 * run some cross plugin protections
	 */
	private function runSomeCrossPluginProtections(){

		add_filter("wp_bost_hide_cache_time_comment",array($this, "pluginProtection_ezCacheHideComment"));

	}

	/**
	 * set if show debug or not
	 */
	private function setShowDebug(){
		
		//already set
		
		if(self::$showDebug == true)
			return(false);
		
		if(self::DEBUG_FILTER == true){
			self::$showDebug = true;
			return(false);
		}
		
		//set debug only for logged in users
		
		$isDebug = HelperUC::hasPermissionsFromQuery("ucfiltersdebug");

		if($isDebug == true){
			
			self::$showEchoDebug = true;
			self::$showDebug = true;
			
			dmp("SHOW DEBUG, logged in user");

		}

	}

	/**
	 * check and set display errors by general option
s	 */
	private function checkSetErrorsReporting(){

		$setDisplayErrors = HelperProviderCoreUC_EL::getGeneralSetting("enable_display_errors_ajax");
		$setDisplayErrors = UniteFunctionsUC::strToBool($setDisplayErrors);

		if($setDisplayErrors == true){

			ini_set("display_errors", "on");
			error_reporting(E_ALL);
		}

	}


	/**
	 * test the request filter
	 */
	public function operateAjaxResponse(){

		if(self::DEBUG_MAIN_QUERY == true){
			$this->showMainQueryDebug();
			exit();
		}

		$frontAjaxAction = UniteFunctionsUC::getPostGetVariable("ucfrontajaxaction","",UniteFunctionsUC::SANITIZE_KEY);

		if(empty($frontAjaxAction))
			return(false);

		$this->runSomeCrossPluginProtections();

		$this->setShowDebug();
		
		$this->checkSetErrorsReporting();

		self::$isUnderAjax = true;
		
		
		try{	

			switch($frontAjaxAction){
				case "getfiltersdata":
					$this->putWidgetGridFrontAjaxData();
				break;
				case "ajaxsearch":
					$this->putAjaxSearchData();
				break;
				case "submitform":

					$form = new UniteCreatorForm();
					$form->submitFormFront();

				break;
				case "removefromcart":

					$objWoo = UniteCreatorWooIntegrate::getInstance();

					$objWoo->removeFromCartFromData();

				break;
				case "updatecartquantity":

					$objWoo = UniteCreatorWooIntegrate::getInstance();

					$objWoo->updateCartQuantityFromData();
				break;
				case "getcartdata":

					$objWoo = UniteCreatorWooIntegrate::getInstance();
					$objWoo->outputCartFragments();

				break;
				case "custom":
					
					do_action("uc_custom_front_ajax_action");

					//if not catch - will throw error

				default:
					UniteFunctionsUC::throwError("wrong front ajax action: $frontAjaxAction");
				break;
			}

		}catch(Exception $e){

			$message = $e->getMessage();

			HelperUC::ajaxResponseError($message);

		}

	}

	/**
	 * on plugins loaded - do some operations like disable plugins
	 */
	public function operateOnPluginsLoaded(){

		$frontAjaxAction = UniteFunctionsUC::getPostGetVariable("ucfrontajaxaction","",UniteFunctionsUC::SANITIZE_KEY);
		
		if(empty($frontAjaxAction))
			return(false);
		
		UniteCreatorPluginIntegrations::disableShortPixel();
	}
	

	/**
	 * init wordpress front filters
	 */
	public function initWPFrontFilters(){
		
		if(is_admin() == true)
			return(false);
		
		add_action("plugins_loaded", array($this, "operateOnPluginsLoaded"),4);
			
		add_action("wp", array($this, "operateAjaxResponse"));
		
		add_action("ue_before_custom_posts_query", array($this, "onBeforeCustomPostsQuery"));
		//add_action("ue_after_custom_posts_query", array($this, "onAfterCustomPostsQuery"));


	}


}
