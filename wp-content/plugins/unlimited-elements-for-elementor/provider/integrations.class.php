<?php

/**
 * @package Unlimited Elements
 * @author UniteCMS http://unitecms.net
 * @copyright Copyright (c) 2016 UniteCMS
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
*/

//no direct accees
defined ('UNLIMITED_ELEMENTS_INC') or die ('restricted aceess');

class UniteCreatorPluginIntegrations{

	private $activeLang;

	private $defaultLang;

	/** WPP orderby filter: post IDs order (static for filter callback) */
	private static $wppOrderByIDs = array();
	/** WPP orderby filter: ASC/DESC */
	private static $wppOrderByDirection = "DESC";
	
	private function ___________JET_ENGINE_________(){}
	
	/**
	 * check if the jet engin exists
	 */
	public static function isJetEngineExists(){
							
		$isExists = class_exists( 'Jet_Engine' );
		
		return($isExists);
	}
	
	private function ___________WP_POPULAR_POSTS_________(){}
	
	/**
	 * return if exists wp popular posts
	 */
	public static function isWPPopularPostsExists(){
		
		$isExists = defined("WPP_VERSION"); 
		
		return($isExists);
	}
	
	/**
	 * get single post views using wpp
	 */
	public static function WPP_getPostViews($postID){
				
		if(self::isWPPopularPostsExists() == false)
			return(0);
		
		if(empty($postID))
			return(0);
			
		if(function_exists("wpp_get_views") == false)
			return(0);
			
		$numViews = wpp_get_views($postID);
		
		return($numViews);
	}
	
	/**
	 * get wpp range select
	 */
	public static function WPP_getRangeSelect(){
		
		$arrItems = array(
			"last30days" => "Last 30 Days",
			"last7days" => "Last 7 Days",
			"last24hours" => "Last 24 Hours",
			"daily" => "Daily",
			"weekly" => "Weekly",
			"monthly" => "Monthly",
			"all" => "All",
		);
		
		return($arrItems);
	}
	
	/**
	 * get popular posts
	 * args - post_type, cat, limit, range
	 */
	public function WPP_getPopularPosts($args, $addDebug = false){
		
		$isExists = self::isWPPopularPostsExists();
		
		if($isExists == false)
			return(false);
		
		$postType = UniteFunctionsUC::getVal($args, "post_type");
		
		if(is_array($postType))
			$postType = implode(",",$postType);
		
		if(empty($postType))
			$postType = "post";
		
		$limit = UniteFunctionsUC::getVal($args, "limit", 5);
		$range = UniteFunctionsUC::getVal($args, "range", "last7days");
		$cat = UniteFunctionsUC::getVal($args, "cat", "");
		
		if(is_array($cat))
			$cat = $cat[0];
		
		if($cat == "all")
			$cat = null;
		
		$params = array();
		$params["post_type"] = $postType;
		$params["limit"] = $limit;
		$params["range"] = $range;
		
		if(!empty($cat))
			$params["cat"] = $cat;
		
		$query = new \WordPressPopularPosts\Query($params);
		
		$arrPosts = $query->get_posts();
		
		if(empty($arrPosts))
			$arrPosts = array();
		
		$arrPosts = UniteFunctionsUC::convertStdClassToArray($arrPosts);
		
		$strDebug = "";
		$arrPostIDs = array();
		
		if($addDebug == true){
		
			$strDebug .= "Popular posts query arguments:";
			$strDebug .= "<pre>";
			$strDebug .= print_r($params, true);
			$strDebug .= "</pre>";
	
			$numPosts = count($arrPosts);
			if(!empty($numPosts))
				$strDebug .= "Found $numPosts posts: <br>";
		}
		
		foreach($arrPosts as $index => $post){
			
			$num = $index+1;
			
			$id = UniteFunctionsUC::getVal($post, "id");
			$title = UniteFunctionsUC::getVal($post, "title");
			$pageviews = UniteFunctionsUC::getVal($post, "pageviews");
			
			if($addDebug == true)
				$strDebug .= "{$num}. $title ($id): $pageviews views <br>";
			
			$arrPostIDs[] = $id;
		}
		
		if(empty($arrPosts) && $addDebug == true)
			$strDebug .= "No popular posts found <br>";
		
		//empty the selection if not found
		if(empty($arrPostIDs))
			$arrPostIDs = array("0");
		
		$output = array();
		$output["post_ids"] = $arrPostIDs;
		$output["debug"] = $strDebug;
		
		return($output);
	}

	/**
	 * get popular post IDs for orderby (e.g. from sort filter) from query args
	 * queryArgs - WP_Query-style args (post_type, posts_per_page)
	 * range - WPP range key, default last30days
	 * @return array post IDs or empty array
	 */
	public static function WPP_getPopularPostIdsForOrderBy($queryArgs, $range = "last30days"){
		if(self::isWPPopularPostsExists() == false)
			return array();
		$postType = UniteFunctionsUC::getVal($queryArgs, "post_type", "post");
		$limit = UniteFunctionsUC::getVal($queryArgs, "posts_per_page", 999);
		if(empty($limit) || $limit < 1)
			$limit = 999;
		$obj = new self();
		$wpp_args = array(
			"post_type" => $postType,
			"limit" => $limit,
			"range" => $range
		);
		$response = $obj->WPP_getPopularPosts($wpp_args, false);
		$ids = UniteFunctionsUC::getVal($response, "post_ids");
		return is_array($ids) ? $ids : array();
	}

	/**
	 * WordPress filter: order by WPP popularity (uses static IDs/direction)
	 */
	public static function filterPostsOrderbyWpp($orderby, $query){
		
		if(is_object($query) && method_exists($query, "get")){
			$isActive = $query->get("ue_wpp_orderby");
			if(empty($isActive))
				return $orderby;
		}
		
		if(empty(self::$wppOrderByIDs))
			return $orderby;
		
		$arrIDs = array_map("intval", self::$wppOrderByIDs);
		$arrIDs = array_filter($arrIDs);
		$arrIDs = array_unique($arrIDs);
		
		if(empty($arrIDs))
			return $orderby;
			
		global $wpdb;
		
		$orderDir = (self::$wppOrderByDirection == "ASC") ? "ASC" : "DESC";
		$orderDirField = ($orderDir == "ASC") ? "DESC" : "ASC";
		$field = "FIELD({$wpdb->posts}.ID," . implode(",", $arrIDs) . ")";
		$orderby = "({$field} = 0) ASC, {$field} {$orderDirField}, {$wpdb->posts}.post_date {$orderDir}";
				
		return $orderby;
	}

	/**
	 * apply WPP orderby filter for the next WP_Query run
	 */
	public static function WPP_applyOrderByFilter($arrIDs, $orderDir = "DESC"){
		
		self::$wppOrderByIDs = is_array($arrIDs) ? $arrIDs : array();
		self::$wppOrderByDirection = ($orderDir == "ASC") ? "ASC" : "DESC";
		
		add_filter("posts_orderby", array(__CLASS__, "filterPostsOrderbyWpp"), 10, 2);
	}

	/**
	 * remove WPP orderby filter after query
	 */
	public static function WPP_removeOrderByFilter(){
		remove_filter("posts_orderby", array(__CLASS__, "filterPostsOrderbyWpp"), 10);
		self::$wppOrderByIDs = array();
	}

	private function ___________STICKY_POSTS_STITCH_________(){}
	
	/**
	 * check if enabled sticky posts switch plugin
	 */
	public static function isStickySwitchPluginEnabled(){
		
		$isExists = class_exists('WP_Sticky_Posts_Switch');
		
		return($isExists);
	}
	
	
	/**
	 * add sticky posts to a post list
	 */
	public static function checkAddStickyPosts($arrPosts, $args){
		
		$isExists = self::isStickySwitchPluginEnabled();
		
		if($isExists == false)
			return($arrPosts);
		
        $arrStickyPostIDs = get_option('sticky_posts');
		
        if(empty($arrStickyPostIDs))
        	return($arrPosts);
                	
        $arrStickyAssoc = UniteFunctionsUC::arrayToAssoc($arrStickyPostIDs);
        	
        $arrPostsNew = array();
        
        $countSticky = 0;
        
        $numOriginal = count($arrPosts);
        
        //remove the sticky from the list to the sticky assoc array if exists
        
        foreach($arrPosts as $post){
        	
        	$postID = $post->ID;

        	$isSticky = isset($arrStickyAssoc[$postID]);
		
        	if($isSticky == false){
        		$arrPostsNew[] = $post;
        		continue;
        	}
        	
        	$arrStickyAssoc[$postID] = $post;        	
        	$countSticky++;
        }
        
        //if all sticky found - then use the array, if not - get new posts
		
		if($countSticky != count($arrStickyAssoc)){
			
			$postType = UniteFunctionsUC::getVal($args, "post_type");
			
			if(empty($postType) || $postType == "post")
				return($arrPosts);
			
			$argsSticky = array();
			$argsSticky["post_type"] = $postType;
			$argsSticky["post__in"] = $arrStickyPostIDs;
			$argsSticky["post_status"] = "publish";
			$argsSticky["nopaging"] = true;
			$argsSticky["orderby"] = "post__in";
			
			$arrStickyAssoc = get_posts($argsSticky);
		}
        
		if(empty($arrStickyAssoc))
			return($arrPosts);
		
		//connect the arrays - sticky at the top
		
		$arrPostsOutput = array_values($arrStickyAssoc);

		$numPostsNew = count($arrPostsOutput);
				
		foreach($arrPostsNew as $post){
			
			$arrPostsOutput[] = $post;
			
			//avoid more then original number of posts
			
			if($numPostsNew >= $numOriginal)
				break;
						
			$numPostsNew++;
		}
		
		
		return($arrPostsOutput);
	}

	private function ___________CONTACT_FORM_7_________(){}
	
	
	/**
	 * check if contact form 7 installed
	 */
	public static function isContactFrom7Installed(){
		
		if(defined("WPCF7_VERSION"))
			return(true);
			
		return(false);
	}
	
	
	/**
	 * get contact from 7 array
	 */
	public static function getArrContactForm7(){
		
		$arrPosts = UniteFunctionsWPUC::getPostsByType("wpcf7_contact_form");
		
		if(empty($arrPosts))
			return(array());
		
		$arrForms = array();
		
		$arrForms["[ Select From ]"] = __("Please Select Contact From 7","unlimited-elements-for-elementor");
		
		foreach($arrPosts as $post){
		
			$postID = $post["ID"];
			
			$title = $post["post_title"];
			
			$title = esc_attr($title);
			
			$shortcode = "[contact-form-7 id=\"{$postID}\" title=\"{$title}\"]";
			
			if(isset($arrForms[$title]))
				$title = "$title ($postID)";
			
			$arrForms[$title] = $shortcode;
		}
		
		
		return($arrForms);
	}
	
	private function ___________JET_WISHLIST_________(){}
	
	/**
	 * put woocommerce jet wishlist button if exist
	 */
	public static function putJetWooWishlistButton(){
		
		
		if(GlobalsProviderUC::$isInsideEditor == true)
			return(false);
		
		if(class_exists("Jet_CW") == false)
			return(false);
		
		$objJetCW = Jet_CW();
		
		if(empty($objJetCW))
			return(false);
		
		$isEnabled = $objJetCW->wishlist_enabled;
		
		if($isEnabled == false)
			return(false);
		
		$objSettings = $objJetCW->settings;
		
		if(empty($objSettings))
			return(false);
		
		$isAddDefault = $objSettings->get("add_default_wishlist_button");
		
		$isAddDefault = UniteFunctionsUC::strToBool($isAddDefault);
		
		if($isAddDefault == false)
			return(false);
					
		if(empty($objJetCW->wishlist_integration))
			return(false);
		
		if(method_exists($objJetCW->wishlist_integration,"add_wishlist_button_default") == false)
			return(false);
		
		$objJetCW->wishlist_integration->add_wishlist_button_default();
		
	}
	
	
	
	
	
	private function ___________SIMPLE_AUTHOR_BOX_________(){}
	
	
	/**
	 * modify get user data
	 */
	public function saboxGetUserData($arrData){
		
		$userID = UniteFunctionsUC::getVal($arrData, "id");
		
		if(empty($userID))
			return($arrData);
			
		$arrMeta = UniteFunctionsWPUC::getAllUserMeta($userID);
		
		if(empty($arrMeta))
			return($arrData);
			
		$urlProfileImage = UniteFunctionsUC::getVal($arrMeta, "sabox-profile-image");
		
		if(!empty($urlProfileImage))
			$arrData["avatar_url"] = $urlProfileImage;
		
		
		return($arrData);
	}
	
	
	/**
	 * simple author box
	 */
	private function initSABoxIntegration(){
		
		add_filter("unlimited_elements_get_user_data",array($this,"saboxGetUserData"));
				
	}
	
	private function ___________FVPLAYER_________(){}
	
	/**
	 * fvplayer - modify includeby
	 */
	public function fvplayerModifyPostsIncludeby($includeBy){
		
		$includeBy["fvplayers_user_watched"] = __("FVPlayer - User Watched Posts", "unlimited-elements-for-elementor");
		
		return($includeBy);
	}
	
	/**
	 * get custom post id's
	 */
	public function fvplayerGetCustomPostIDs($arrIDs, $includeBY, $limit){
		
		$arrIDs = array();
		
		switch($includeBY){
			case "fvplayers_user_watched":
				
				$arrIDs = fv_player_get_user_watched_post_ids(array("count"=>$limit));
				
				//show debug
				
				if(GlobalsProviderUC::$showPostsQueryDebug == true){
					dmp("FVPlayer - get user recently watched posts by function: fv_player_get_user_watched_post_ids");
					dmp($arrIDs);
				}
				
			break;
		}
				
		if(empty($arrIDs))
			$arrIDs = array();
					
		return($arrIDs);
	}
	
	/**
	 * check if fv player active
	 */
	private function initFvPlayerIntegrations(){
		
		//check if exists
		global $fv_wp_flowplayer_ver;
		
		if(empty($fv_wp_flowplayer_ver))
			return(false);
			
		//double check
		
		if(function_exists("fv_player_get_user_watched_post_ids") == false)
			return(false);
		
		add_filter("ue_modify_post_select_includeby",array($this,"fvplayerModifyPostsIncludeby"));
		
		add_filter("ue_get_custom_includeby_postids",array($this,"fvplayerGetCustomPostIDs"),10,3);
		
	}
	
	private function ___________TRANSLATE_PRESS_________(){}
	
	/**
	 * modify post data
	 */
	public function translatePressModifyPostData($data){
		
		if(empty($data))
			return($data);
		
		if(is_array($data) == false)
			return($data);
			
		$trp = TRP_Translate_Press::get_trp_instance();
		
		if(empty($trp))
			return($data);
		
		$translation_render = $trp->get_component('translation_render');
		
		foreach($data as $key=>$value){
			
			if(is_string($value) == false)
				continue;
			
			$data[$key] = $translation_render->translate_page($value);
		}
		
		
		return($data);
	}
	
	/**
	 * translate press
	 */
	private function initTranslatePressIntegration(){
		
		if(class_exists('TRP_Translate_Press') == false)
			return(false);
		
		add_filter("ue_modify_post_data",array($this,"translatePressModifyPostData"));
	}
	
	private function ___________FAVORITES_PLUGIN_________(){}
	
	/**
	 * favorites plugin posts includeby
	 */
	public function favoritesModifyPostsIncludeby($includeBy){
		
		$includeBy["favorites_get_user_posts"] = __("Favorites Plugin - Get User Posts", "unlimited-elements-for-elementor");

		return($includeBy);
	}
	
	private function ___________RELEVANSSI_________(){}


	
	/**
	 * add relevanssi integration settings to post list select
	 */
	public function addRelevanssiIntegrationSetting($arrAjaxSettings, $paramName){
		
		$arrAjaxSettings[] = array(
			"name"         => $paramName . '_relevanssi_integration',
			"type"         => UniteCreatorDialogParam::PARAM_RADIOBOOLEAN,
			"label"        => __( 'Enable Relevanssi Plugin Integration', "unlimited-elements-for-elementor" ),
			"default"      => "",
			'label_on'     => __( 'Yes', 'unlimited-elements-for-elementor' ),
			'label_off'    => __( 'No', 'unlimited-elements-for-elementor' ),
			'return_value' => 'true',
			'separator'    => 'before',
			'condition' => array($paramName.'_isajax'=>"true"),
			'description'  => __('When searching using search filter, if enable the search using relevancy plugin', 'unlimited-elements-for-elementor'),
		);
		
		return($arrAjaxSettings);
	}
	
	
	/**
	 * modify query arguments for relevanssi
	 * disable relevanssi, or enable if the checkbox turned on
	 */
	public function relevanssiModifyQueryArgs($args, $value, $name){
		
		/*
		if(GlobalsProviderUC::$isUnderAjax == false)
			return($args);
		
		$search = UniteFunctionsUC::getVal($args, "s");
		
		if(empty($search))
			return($args);
		*/
		
		$relevanssiIntegration = UniteFunctionsUC::getVal($value, "{$name}_relevanssi_integration");
		$relevanssiIntegration = UniteFunctionsUC::strToBool($relevanssiIntegration);
		
		if($relevanssiIntegration == true){		//enable

			$args["relevanssi"] = true;
			
		}else{	// disable
			
			unset($args["relevanssi"]);		
			
			remove_filter('posts_request', 'relevanssi_prevent_default_request');
		}
		
		
		return($args);
	}
	
	/**
	 * init relevancy plugin integrations
	 */
	private function initRelevanssiIntegrations(){
		
		//add setting in post list
		add_filter("ue_modify_post_grid_ajax_settings",array($this,"addRelevanssiIntegrationSetting"),10,2);

		//modiify the query arguments
		add_filter("ue_modify_posts_query_args",array($this,"relevanssiModifyQueryArgs"),10,3);
		
	}
	
	private function ___________SHORT_PIXEL_________(){}
	
	
	/**
	 * disable short pixel optimization for this page
	 */
	public static function disableShortPixel(){
		
		if(!defined("SHORTPIXEL_API"))
			return(false);
						
		if(!defined("DONOTCDN"))
			define("DONOTCDN",true);
		
		add_filter('shortpixel/image/filecheck', '__return_false');
		
		add_filter( 'shortpixel/plugin/init', function( $init ) {
			
		    return false;
		}, 10, 1 );
		
	}
	
	
	private function ___________LANGUAGES_________(){}

	
	/**
	 * add "lang" to post query
	 */
	public function languagesPostQueryAddLang($args){
		
		$args["lang"] = UniteFunctionsWPUC::getLanguage();
		
		return($args);
	}
	
	/**
	 * init languages integration
	 */
	private function initLanguagesIntegration(){
		
		if(function_exists('pll_current_language') == false && UniteCreatorWpmlIntegrate::isWpmlExists() == false)
			return(false);

		//modify post query arguments, add current site language
		
		add_filter("ue_modify_posts_query_args",array($this,"languagesPostQueryAddLang"));
				
	}
	
	private function ___________GENERAL_INIT_INTEGRATIONS_________(){}

	
	/**
	 * modify post query integrations
	 */
	public static function modifyPostQueryIntegrations($args){
				
		$args = self::checkPostQueryLanguage($args);
						
		return($args);
	}
	
	
	/**
	 * get user post ids
	 */
	public function favoritesGetUserPostIDs($arrIDs, $includeBY, $limit){
		
		$arrIDs = array();
		
		switch($includeBY){
			case "favorites_get_user_posts":
				
				$exists = class_exists("Favorites\Entities\User\UserRepository");
				
				$response = null;
				
				if($exists == true){
					$userRepository = new Favorites\Entities\User\UserRepository();
					$response = $userRepository->getAllFavorites();
				}
				
				if(!empty($response)){
					$arrRespones = $response[0];
					$arrIDs = UniteFunctionsUC::getVal($arrRespones, "posts");
				}
				
				//show debug
				
				if(GlobalsProviderUC::$showPostsQueryDebug == true){
					dmp("Favorites plugin - get usre favorites");
					dmp($arrIDs);
				}
				
			break;
		}
				
		if(empty($arrIDs))
			$arrIDs = array();
					
		return($arrIDs);
		
		
	}
	
	/**
	 * init favoritest plugin integration
	 */
	private function initFavoritesIntegration(){
		
		if(function_exists("favorites_check_versions") == false)
			return(false);
			
		add_filter("ue_modify_post_select_includeby",array($this,"favoritesModifyPostsIncludeby"));
		
		add_filter("ue_get_custom_includeby_postids",array($this,"favoritesGetUserPostIDs"),10,3);
		
	}

	/**
	 * init process sticky posts
	 */
	private function initProcessStickyPosts() {
		add_action('ue_before_get_only_sticky_posts', array($this,'getStickyPostsBasedOnDefaultLanguage'), 10, 2);
	}

	/**
	 * get sticky posts based on default language
	 */
	public function getStickyPostsBasedOnDefaultLanguage($value, $name) {
		$stickyPostDefaultLangOption = UniteFunctionsUC::getVal( $value, "{$name}_sticky_post_default_lang" );
		$stickyPostDefaultLangOption = UniteFunctionsUC::strToBool( $stickyPostDefaultLangOption );

		$isWpmlExists = UniteCreatorWpmlIntegrate::isWpmlExists();
		if($isWpmlExists == false)
			return;

		$objWPML     = new UniteCreatorWpmlIntegrate();
		$this->defaultLang = $objWPML->getDefaultSiteLanguage();
		$this->activeLang  = $objWPML->getActiveLanguage();

		if ($stickyPostDefaultLangOption == true && $this->activeLang != $this->defaultLang){
			do_action('wpml_switch_language', $this->defaultLang);
			add_action("ue_after_custom_posts_query", array($this, "resetStickyPostsToActiveLanguage"), 10);
		}
	}

	/**
	 * reset sticky posts to active language
	 */
	public function resetStickyPostsToActiveLanguage() {
		do_action('wpml_switch_language', $this->activeLang);
	}
	
	/**
	 * init plugin integrations - on plugins loaded
	 */
	public function initPluginIntegrations(){
		
		//simple author box
		
		if(class_exists("Simple_Author_Box"))
			$this->initSABoxIntegration();
		
		$this->initFvPlayerIntegrations();
		
		if(defined("FAVORITES_PLUGIN_FILE"))
			$this->initFavoritesIntegration();
		
		if(function_exists("relevanssi_init"))
			$this->initRelevanssiIntegrations();
		
		$this->initLanguagesIntegration();

		$this->initProcessStickyPosts();
		
		//if(function_exists("trp_enable_translatepress"))
			//$this->initTranslatePressIntegration();
			
	}
	
	
	
}
