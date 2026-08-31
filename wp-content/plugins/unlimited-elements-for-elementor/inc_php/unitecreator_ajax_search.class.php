<?php 
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved.
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
if ( ! defined( 'ABSPATH' ) ) exit;

class UniteCreatorAjaxSeach{
	
	public static $arrCurrentParams;
	public static $customSearchEnabled = false;
	public static $enableThirdPartyHooks = false;

	private $searchInMeta = false;
	private $searchInTerms = false;
	private $strTerms = false;
	private $searchPostFields = array();
	private $searchMetaKey = "";

	/**
	 * set post parts where clause
	 */
	public function setWherePostParts($where, $wp_query){
		
		if (in_array('all', $this->searchPostFields))
			return ($where);

		if(empty($this->searchPostFields))
			return($where);
		
		//set fields to delete
		$arrDelete = array("post_title"=>true,"post_excerpt"=>true,"post_content"=>true);

		foreach($this->searchPostFields as $field){
			
			unset($arrDelete[$field]);
		}
		
		//AI Help :)
		
		// Remove fields specified in $arrDelete
		
	    foreach ($arrDelete as $field => $remove) {
	        if ($remove) {
	            // Pattern to match the specific condition
	            $pattern = "/\(wp_posts\.$field LIKE '[^']*'\)\s*(OR\s*)?/";
	            $where = preg_replace($pattern, '', $where);
	        }
	    }
	
	    // Clean up unnecessary OR and extra spaces left after removal
	    $where = preg_replace('/\s+OR\s+\)/', ')', $where);
	    $where = preg_replace('/\(\s+OR\s+/', '(', $where);
	
	    $where = trim($where);		
		
		if(GlobalsProviderUC::$showPostsQueryDebug == true){
			
			dmp("Mat the search for those fields: ");
			dmp($this->searchPostFields);
			
			dmp($where);
		}
	    
		remove_filter( 'posts_where', array($this,'setWherePostParts'), 10, 2 );
		
		return($where);
	}
	
	/**
	 * on posts response
	 */
	public function onPostsResponse($arrPosts, $value, $filters){
		
		if(GlobalsProviderUC::$isUnderAjaxSearch == false)
			return($arrPosts);
					
		$name = UniteFunctionsUC::getVal($value, "uc_posts_name");
		
		$args = GlobalsProviderUC::$lastQueryArgs;
		
		$maxItems = UniteFunctionsUC::getVal($args, "posts_per_page", 9);
		
		$numPosts = count($arrPosts);
		
		//if maximum reached - return the original
		
		$addCount = $maxItems - count($arrPosts);
		
		if($addCount <= $maxItems){
						
			if(GlobalsProviderUC::$showPostsQueryDebug == true && $addCount <= 0){
				dmp("Max posts reach");
			}
			
		}
		
		//search in meta
		if($this->searchInMeta == true && $addCount > 0){
			
			$arrPosts = $this->getPostsByMeta($arrPosts, $args, $addCount);
			$addCount = $maxItems - count($arrPosts);
			
			if(GlobalsProviderUC::$showPostsQueryDebug == true && $addCount <= 0){
				dmp("Max posts reach");
			}
			
		}
		
		//search in taxonomy
		if($this->searchInTerms == true && $addCount > 0){
			
			$arrPosts = $this->getPostsByTerms($arrPosts, $args, $addCount);
			
			$addCount = $maxItems - count($arrPosts);
			
			if(GlobalsProviderUC::$showPostsQueryDebug == true && $addCount <= 0){
				dmp("Max posts reach");
			}
			
		}
		
		if (GlobalsProviderUC::$showPostsQueryDebug == true ) {
			
			//print total posts
			
			$totalPosts = count($arrPosts);
			
			dmp("<strong>Total Posts: {$totalPosts} </strong>");
		}


		return($arrPosts);
	}


	/**
	 * get posts from meta query
	 */
	private function getPostsByMeta($arrPosts, $args, $maxPosts){

		$search = $args["s"];
		unset($args["s"]);
				
		
		if(empty($this->searchMetaKey))
			return($arrPosts);

		$searchMetaKeys = explode(",", $this->searchMetaKey);
		$arrMetaQuery = array("relation" => "OR");

		foreach ($searchMetaKeys as $metaKey) {
			$metaKey = trim($metaKey);
			$arrMetaQuery[] = array(
				'key'     => $metaKey,
				'value'   => $search,
				'compare' => "LIKE"
			);
		}

		$arrExistingMeta = UniteFunctionsUC::getVal($args, "meta_query", array());
		$args["meta_query"] = array_merge($arrExistingMeta, $arrMetaQuery);

		$query = new WP_Query();
		$query->query($args);
		$arrPostsByMeta = $query->posts;

		//debug output
		if(GlobalsProviderUC::$showPostsQueryDebug == true){

			dmp("<strong>Search By Meta Fields</strong>");
			dmp("Query:");
			dmp($args);
			dmp("Found Posts: ".count($arrPostsByMeta));
			dmp("Total Query Posts: ".(int)$query->found_posts);

		}
		
		$arrPosts = array_merge($arrPosts, $arrPostsByMeta);
		
		if (!empty($arrPosts))
			$arrPosts = UniteFunctionsWPUC::deleteDuplicatePostsFromArray($arrPosts);
		
		return($arrPosts);
	}


	/**
	 * search posts by terms
	 */
	private function getPostsByTerms($arrPosts, $args, $maxPosts){

		if($this->searchInTerms == false)
			return($arrPosts);

		$search = $args["s"];

		unset($args["s"]);

		$postType = UniteFunctionsUC::getVal($args, "post_type");

		if(empty($postType))
			return($arrPosts);

		$arrTax = UniteFunctionsWPUC::getPostTypeTaxomonies($postType);



		if(empty($arrTax))
			return($arrPosts);

		$arrAllTaxNames = array_keys($arrTax);

		$arrTaxNames = UniteFunctionsUC::csvToArray($this->strTerms);

		if(!empty($arrTaxNames))
			$arrTaxNames = array_intersect($arrAllTaxNames, $arrTaxNames);
		else
			$arrTaxNames = $arrAllTaxNames;

		if(empty($arrTaxNames)){

			if(GlobalsProviderUC::$showPostsQueryDebug == true) {
				
				dmp("<strong>Search By Terms</strong> ");
				dmp("Taxonomies not found: {$this->strTerms}. please use some of those: ");
				dmp($arrAllTaxNames);
				
			}

			return($arrPosts);
		}
		
		
		$arrTermsSearch = array();
		$arrTermsSearch["taxonomy"] = $arrTaxNames;
		$arrTermsSearch["search"] = $search;
		$arrTermsSearch["hide_empty"] = true;
		$arrTermsSearch["number"] = 50;
		//$arrTermsSearch["fields"] = "id=>name";

		$termsQuery = new WP_Term_Query();
		$arrTermsFound = $termsQuery->query($arrTermsSearch);


		if(empty($arrTermsFound)){
			if(GlobalsProviderUC::$showPostsQueryDebug == true){
				dmp("no terms found by: <b>$search</b>. Terms Query:");
				dmp($arrTermsSearch);
			}

			return($arrPosts);
		}
		
		$arrTaxQuery = UniteFunctionsWPUC::getTaxQueryFromTerms($arrTermsFound);
		$args = UniteFunctionsWPUC::mergeArgsTaxQuery($args,$arrTaxQuery);

		$query = new WP_Query();
		$query->query($args);
		$arrPostsByTerms = $query->posts;

		//debug output
		if(GlobalsProviderUC::$showPostsQueryDebug == true){
			dmp("<strong>Search By Terms</strong>");
			dmp("Query:");
			$strTerms = UniteFunctionsWPUC::getTermsTitlesString($arrTermsFound, true);
			dmp($strTerms);
			dmp($args);
			dmp("Found Terms: ".count($arrTermsFound));
			dmp("Found Posts: ".count($arrPostsByTerms));
			dmp("Total Query Posts: ".(int)$query->found_posts);
			
		}


		if(empty($arrPostsByTerms))
			return($arrPosts);

		$arrPosts = array_merge($arrPosts, $arrPostsByTerms);

		//remove duplicates if there are posts with the same ID in the array after merging two arrays "$arrPostsByTerms" and "$arrPosts"
		if (!empty($arrPosts))
			$arrPosts = UniteFunctionsWPUC::deleteDuplicatePostsFromArray($arrPosts);
		

		return($arrPosts);
	}
	
	
	/**
	 * is there is special search in search filter
	 */
	public static function isSearchFilterHasSpecialArgs($data){
		
		$searchBy = UniteFunctionsUC::getVal($data, "search_by");
		
		if(empty($searchBy))
			return($output);
			
		if(is_array($searchBy) == false)
			return($output);
		
		$firstItem = $searchBy[0];
		
		if($firstItem != "all")
			return(true);
			
		//check search in meta
		
		$searchInMeta = UniteFunctionsUC::getVal($data, "search_in_meta");
		$searchInMeta = UniteFunctionsUC::strToBool($searchInMeta);
		
		if($searchInMeta == true){
			$searchInMetaName = UniteFunctionsUC::getVal($data, "searchin_meta_name");
			$searchInMetaName = trim($searchInMetaName);
			
			if(!empty($searchInMetaName))
				return(true);
							
		}
		
		//check search in terms
		
		$searchInTerms = UniteFunctionsUC::getVal($data, "search_in_terms");
		$searchInTerms = trim($searchInTerms);
		
		if($searchInTerms == true){

			$searchInTaxonomy = UniteFunctionsUC::getVal($data, "search_in_taxonomy");
			$searchInTaxonomy = trim($searchInTaxonomy);
			
			if(!empty($searchInTaxonomy))
				return(true);
		}
		
		
		return(false);
	}

	
	/**
	 * supress third party filters except of this class ones
	 */
	public static function supressThirdPartyFilters(){
		
		//on the enable hooks setting - don't supress hooks
		
		if(self::$enableThirdPartyHooks === true)
			return(false);

		global $wp_filter;
		
		if(self::$customSearchEnabled == false){
			
			$wp_filter = array();
			return(false);
		}

		//keys to leave
		$arrKeys = array("uc_filter_posts_list","posts_where");
		
		
		$newFilters = array();

		foreach($arrKeys as $key){

			$filter = UniteFunctionsUC::getVal($wp_filter, $key);

			if(!empty($filter))
				$newFilters[$key] = $filter;
		}

		$wp_filter = $newFilters;

	}
	
	
	/**
	 * init the ajax search - before the get posts accure, from ajax request
	 */
	public function initCustomAjaxSeach($arrParams){
		
		self::$arrCurrentParams = $arrParams;
				
		//enable hooks
		
		$enableHooks = UniteFunctionsUC::getVal($arrParams, "enable_third_party_hooks");
		$enableHooks = UniteFunctionsUC::strToBool($enableHooks);
		
		if($enableHooks == true)
			self::$enableThirdPartyHooks = true;

		$applyModifyFilter = false;

		//search by meta fields
		$searchInMeta = UniteFunctionsUC::getVal($arrParams, "search_in_meta");
		$searchInMeta = UniteFunctionsUC::strToBool($searchInMeta);
		
		$searchMetaKey = UniteFunctionsUC::getVal($arrParams, "searchin_meta_name");

		$searchMetaSku = UniteFunctionsUC::getVal($arrParams, "search_by_sku");
		$searchMetaSku = UniteFunctionsUC::strToBool($searchMetaSku);
		
		if($searchMetaSku == true){
			$searchInMeta = true;
			$searchMetaKey = "_sku";
		}
		
		if($searchInMeta == true){
			$applyModifyFilter = true;
			self::$customSearchEnabled = true;
			$this->searchInMeta = true;
			$this->searchMetaKey = $searchMetaKey;
		}

		
		//search by terms
		$searchInTerms = UniteFunctionsUC::getVal($arrParams, "search_in_terms");
		$searchInTerms = UniteFunctionsUC::strToBool($searchInTerms);
		if($searchInTerms == true){
			$applyModifyFilter = true;
			self::$customSearchEnabled = true;
			$this->searchInTerms = true;
			$this->strTerms = UniteFunctionsUC::getVal($arrParams, "search_in_taxonomy");
		}
		
		//search by post fields
		$arrSearchPostFields = UniteFunctionsUC::getVal($arrParams, "search_by");
		
		if(!empty($arrSearchPostFields) && in_array("all", $arrSearchPostFields) == false){
			
			add_filter( 'posts_where', array($this,'setWherePostParts'), 10, 2 );
			
			self::$customSearchEnabled = true;
			
			$this->searchPostFields = $arrSearchPostFields;
		}
		
		
		//skip main query if just meta or terms selected for example
		
		if(empty($arrSearchPostFields) && $applyModifyFilter == true){
			GlobalsProviderUC::$skipRunPostQueryOnce = true;			
		}
		
		
		if($applyModifyFilter == true){
			UniteProviderFunctionsUC::addFilter("uc_filter_posts_list", array($this,"onPostsResponse"),10,3);
		}
		
	}
	
	/**
	 * get suggestion data for ajax search
	 */
	public function getSearchSuggestionData($search, $args){
		
		$searchToken = $this->getSuggestionToken($search);
		
		if(empty($searchToken))
			return(null);
		
		$suggestion = $this->getSuggestionFromArgs($searchToken, $args);
		
		if(empty($suggestion) || $suggestion === $searchToken)
			return(null);
		
		$output = array();
		$output["original"] = $search;
		$output["suggestion"] = $suggestion;
		
		return($output);
	}
	
	/**
	 * get suggestion from query args
	 */
	private function getSuggestionFromArgs($searchToken, $args){
		
		if(empty($args) || is_array($args) == false)
			return(null);
		
		if(isset($args["s"]))
			unset($args["s"]);
		
		if(isset($args["search"]))
			unset($args["search"]);
		
		if(isset($args["paged"]))
			unset($args["paged"]);
		
		if(isset($args["offset"]))
			unset($args["offset"]);
		
		$args["posts_per_page"] = 50;
		$args["no_found_rows"] = true;
		$args["ignore_sticky_posts"] = true;
		
		$query = new WP_Query($args);
		
		if(empty($query) || empty($query->posts))
			return(null);
		
		$maxDistance = $this->getSuggestionMaxDistance($searchToken);
		$bestWord = null;
		$bestDistance = null;
		
		foreach($query->posts as $post){
			
			$title = "";
			
			if(is_object($post) && isset($post->post_title)){
				$title = $post->post_title;
			}else{
				$title = UniteFunctionsUC::getVal($post, "post_title");
			}
			
			if(empty($title))
				continue;
			
			$arrWords = $this->getSuggestionWordsFromTitle($title);
			
			if(empty($arrWords))
				continue;
			
			foreach($arrWords as $word){
				
				if($word === $searchToken)
					return(null);
				
				$lenDiff = abs(strlen($word) - strlen($searchToken));
				
				if($lenDiff > $maxDistance)
					continue;
				
				$distance = levenshtein($searchToken, $word);
				
				if($distance === false)
					continue;
				
				if($distance <= $maxDistance && ($bestDistance === null || $distance < $bestDistance)){
					$bestDistance = $distance;
					$bestWord = $word;
					
					if($bestDistance === 0)
						break 2;
				}
				
			}
			
		}
		
		return($bestWord);
	}
	
	/**
	 * get suggestion token (single word)
	 */
	private function getSuggestionToken($search){
		
		$search = trim($search);
		
		if(empty($search))
			return(null);
		
		$search = strtolower($search);
		$search = preg_replace("/[^a-z0-9\\s]/", " ", $search);
		$tokens = preg_split("/\\s+/", $search, -1, PREG_SPLIT_NO_EMPTY);
		
		if(empty($tokens))
			return(null);
		
		$token = $tokens[0];
		
		if(strlen($token) < 3)
			return(null);
		
		return($token);
	}
	
	/**
	 * get words from title
	 */
	private function getSuggestionWordsFromTitle($title){
		
		$title = strtolower($title);
		$title = preg_replace("/[^a-z0-9\\s]/", " ", $title);
		$words = preg_split("/\\s+/", $title, -1, PREG_SPLIT_NO_EMPTY);
		
		if(empty($words))
			return(array());
		
		$output = array();
		
		foreach($words as $word){
			
			if(strlen($word) < 3)
				continue;
			
			$output[$word] = true;
		}
		
		$output = array_keys($output);
		
		return($output);
	}
	
	/**
	 * get suggestion max distance
	 */
	private function getSuggestionMaxDistance($word){
		
		$length = strlen($word);
		
		if($length <= 4)
			return(1);
		
		if($length <= 7)
			return(2);
		
		return(3);
	}

}
