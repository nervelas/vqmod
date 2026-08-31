<?php
/**
 * @package Unlimited Elements
 * @author UniteCMS Enhanced
 * @copyright Copyright (c) 2016-2024 UniteCMS
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */

if(!defined('ABSPATH')) exit;
class UniteCreatorSchema {
	
	private static $arrCollectedSchemaData = array();
	private static $arrSchemas = array();
	
	private $debugFields = array();
	
	private static $showDebug = false;
	
	private $arrSchemaDebugContent = array();
	
	private $lastCustomSchema;
	
	private $objAddon;
	
	private static $showJsonErrorOnce = false;
	
	const ROLE_TITLE = "title";
	const ROLE_DESCRIPTION = "description";
	const ROLE_HEADING = "heading";
		
	const ROLE_LINK = "link";
	const ROLE_CONTENT = "content";
	const ROLE_IMAGE = "image";
	
	const ROLE_EXTRA_FIELD1 = "field1";
	const ROLE_EXTRA_FIELD2 = "field2";
	const ROLE_EXTRA_FIELD3 = "field3";
	const ROLE_EXTRA_FIELD4 = "field4";
	
	const ROLE_AUTO = "_auto_";
	
	const CONTENT_FIELDS = "content_fields";
	
	const SCHEMA_ORG_SITE = "https://schema.org";
	
	const MULTIPLE_SCHEMA_NAME = "ue_schema";
	
	
	/**
	 * get roles keys
	 */
	private function getArrRolesKeys(){
		
		$arrRoles = array(
			self::ROLE_TITLE,
			self::ROLE_DESCRIPTION,
			self::ROLE_HEADING,
			self::ROLE_LINK,
			self::ROLE_CONTENT,
			self::ROLE_IMAGE,
			self::ROLE_EXTRA_FIELD1,
			self::ROLE_EXTRA_FIELD2,
			self::ROLE_EXTRA_FIELD3,
			self::ROLE_EXTRA_FIELD4
		);
		
		return($arrRoles);
	}
	
	
	/**
	 * get schemas array
	 */
	private function getArrSchemas(){
		
		if(!empty(self::$arrSchemas))
			return(self::$arrSchemas);
		
		$arrSchemas = array(
			array(
				"type"=>"FAQPage",
				"title"=>"FAQ",
				"multiple"=>true
			),
			array(
				"type"=>"Person",
				"title"=>"List Of Person"
			),
			array(
				"type"=>"HowTo"
			),
			array(
				"type"=>"Recipe"
			),			
			array(
				"type"=>"Course",
				"title"=>"Courses"
			),
			array(
				"type"=>"Book",
				"title"=>"Books"
			),
			array(
				"type"=>"ItemList",
				"title"=>"Items List",
				"multiple"=>true
			),
			array(
				"type"=>"Event"
			),
			array(
				"type"=>"Place",
				"title"=>"Places"
			),
			array(
				"type"=>"Product",
				"title"=>"Products"
			),			
			array(
				"type"=>"TouristDestination",
				"title"=>"Tourist Destinations"
			),
			array(
				"type"=>"EventSeries",
				"title"=>"Event Series",
				"multiple"=>true
			),
			array(
				"type"=>"MusicPlaylist",
				"title"=>"Music Playlist",
				"multiple"=>true
			),
			array(
				"type"=>"SearchResultsPage",
				"title"=>"Search Results Page",
				"multiple"=>true
			),
			array(
				"type"=>"NewsArticle",
				"title"=>"News Article"
			)
			
		);
		
		$arrOutput = array();
		
		foreach($arrSchemas as $schama){
			
			$type = UniteFunctionsUC::getVal($schama, "type");
			
			$name = strtolower($type);
			
			$title = UniteFunctionsUC::getVal($schama, "title"); 
						
			if(!empty($title))
				$title .= " ($type)";
			else
				$title = $type;
			
			$schama["name"] = $name;
			$schama["title"] = $title;
			
			$arrOutput[$name] = $schama;
		}
		
		self::$arrSchemas = $arrOutput;
		
		return(self::$arrSchemas);
	}
	
	/**
	 * get schema options by name
	 */
	private function getSchemaOptionsByName($name){
		
		$arrSchemas = $this->getArrSchemas();
		
		$arrSchema = UniteFunctionsUC::getVal($arrSchemas, $name);
		
		return($arrSchema);
	}
	
	/**
	 * set the addon
	 */
	public function setObjAddon($addon){
		
		//$this->objAddon = new UniteCreatorAddon();
		
		$this->objAddon = $addon;
	}
	
	
	/**
	 * convert the items from post list name
	 */
	private function convertWidgetItems($arrItems, $paramName){
		
		$arrItemsConverted = array();
				
		foreach($arrItems as $item){
			
			$arrItem = UniteFunctionsUC::getVal($item, "item");
			
			$arrItem = UniteFunctionsUC::getVal($arrItem, $paramName);

			$arrItemsConverted[] = $arrItem;
		}
		
		
		return($arrItemsConverted);
	}
	
	
	
	/**
	 * put schema items by post
	 */
	private function putSchemaByPost($schemaType, $arrItems, $arrSettings){
				
		$postListName = UniteFunctionsUC::getVal($arrSettings, "post_list_name");
		$arrItemsConverted = $this->convertWidgetItems($arrItems, $postListName);
		
		$arrParamsItems = array();
		
		$this->putSchemaItems($schemaType, $arrItemsConverted, $arrParamsItems , $arrSettings);
		
	}
	
	
	/**
	 * put schema items
	 */
	public function putSchemaItemsByType($type, $schemaType, $arrItems, $arrParamsItems, $arrSettings){
		
		$arrSettings["item_type"] = $type;
			
		switch($type){
			case UniteCreatorAddon::ITEMS_TYPE_POST:
				
				$this->putSchemaByPost($schemaType, $arrItems, $arrSettings);
				
			break;
			case UniteCreatorAddon::ITEMS_TYPE_MULTISOURCE:
				
				$this->putSchemaItems($schemaType, $arrItems, $arrParamsItems , $arrSettings);
				
			break;
			
		}
								
	}
		
	
	/**
	 * put html items schema
	 */
	public function putSchemaItems($schemaType, $arrItems, $paramsItems, $arrSettings){
		
		if(empty($schemaType))
			$schemaType = "faqpage";
		
		$title = UniteFunctionsUC::getVal($arrSettings, "title");
		
		$title = wp_strip_all_tags($title);
		
		if(!isset(self::$arrCollectedSchemaData[$schemaType]))
			self::$arrCollectedSchemaData[$schemaType] = array("addons"=>array());
		
		self::$arrCollectedSchemaData[$schemaType]["addons"][] = array(
			"items"=>$arrItems,
			"params"=>$paramsItems,
			"settings"=>$arrSettings
		);
		
		//---- add title
		
		$existingTitle = "";
		if(isset(self::$arrCollectedSchemaData[$schemaType]["title"]))
			$existingTitle = self::$arrCollectedSchemaData[$schemaType]["title"];
		
		if(!empty($title) && empty($existingTitle))
			self::$arrCollectedSchemaData[$schemaType]["title"] = $title;
		
		$showDebug = UniteFunctionsUC::getVal($arrSettings, "debug");
		$showDebug = UniteFunctionsUC::strToBool($showDebug);
		
		
		//set the debug
		
		if($showDebug === true){
			self::$showDebug = true;
			
			$this->showDebugMessage();
		}	
		
		
	}
	
	
	/**
	 * show schema code
	 */
	public function showAddonSchema(){
		
		if(self::$showDebug == false){
			self::$showDebug = HelperUC::hasPermissionsFromQuery("ucschemadebug");
		}
		
		if(empty(self::$arrCollectedSchemaData))
			return false;
		
		foreach (self::$arrCollectedSchemaData as $schemaType => $data)
				$this->putAddonSchema($schemaType, $data);
		
		self::$arrCollectedSchemaData = array();
		
	}
	

	/**
	 * generate schema code
	 */
	private function putAddonSchema($schemaType, $data) {
		
		$arrSchema = $this->generateSchemaByType($schemaType, $data);
		
		if(empty($arrSchema)) 
			return;
		
		//clean certain keys that not goo to leave empty
		$arrSchema = $this->cleanSchemaArray($arrSchema, array(
		    'description',
		    'image',
		    'sameAs',
		));
		
		
		$jsonItems = json_encode($arrSchema, JSON_UNESCAPED_UNICODE);
		
		if($jsonItems === false)
			return(false);
		
		$strSchema = '<script type="application/ld+json">' . $jsonItems . '</script>';
		
		//show debug by url
		if(self::$showDebug == true)
			$this->showDebugSchema($schemaType, $arrSchema);
		
		echo $strSchema;
	}

	
	private function a____________SCHEMA_ITEM_CONTENT________(){}
	

	/**
	 * sanitize item value
	 */
	private function sanitizeItemValue($value, $role){
		
		switch($role){
			case self::ROLE_TITLE:
			case self::ROLE_CONTENT:
			case self::ROLE_HEADING:
			case self::ROLE_DESCRIPTION:
			case self::ROLE_EXTRA_FIELD1:
			case self::ROLE_EXTRA_FIELD2:
			case self::ROLE_EXTRA_FIELD3:
			case self::ROLE_EXTRA_FIELD4:
				$value = wp_strip_all_tags($value);
				$value = trim($value);
			break;
			case self::ROLE_IMAGE:
				if($value == GlobalsUC::$url_no_image_placeholder)
					$value = "";
			case self::ROLE_LINK:
				$value = UniteFunctionsUC::sanitize($value, UniteFunctionsUC::SANITIZE_URL);
			break;
		}
			
		return($value);
	}
	
	/**
	 * get extra field placeholder type. meta:key or term:key
	 * Enter description here ...
	 */
	private function getExtraFieldPlaceholderType($fieldName){
		
		if(strpos($fieldName, "meta:") !== false)
			return("meta");
		
		if(strpos($fieldName, "term:") !== false)
			return("term");
		
		return("");
	}
	
	
	/**
	 * get item extra field
	 */
	private function getItemExtraFieldValue($fieldName, $postID, $fieldNameType){
		
		if(empty($postID))
			return("");
							
		//get the meta
		
		if($fieldNameType == "meta"){
						
			$metaKey = str_replace("meta:", "", $fieldName);
				
			$metaValue = UniteFunctionsWPUC::getPostCustomField($postID, $metaKey);
			
			if(empty($metaValue))
				return("");
			
			if(is_array($metaValue))
				 $metaValue = implode(',', $metaValue);
			
			if(is_string($metaValue) == false)
				$metaValue = "";
			
			return($metaValue);
		}
		
		//get the term
		
		if($fieldNameType == "term"){
			
			$taxonomu = str_replace("term:", "", $fieldName);
			
			$termName = UniteFunctionsWPUC::getPostTerm_firstTermName($postID, $taxonomu);
			
			return($termName);
		}

		
		return("");
	}
	
	
	/**
	 * get item schema content
	 */
	private function getItemSchemaContent($item, $arrFieldsByRoles, $itemType){
		
		if(isset($item["item"]))
			$item = $item["item"];
		
						
		$arrContent = array();
		
		$postID = null;
		
		switch($itemType){
			case UniteCreatorAddon::ITEMS_TYPE_POST:
				
				$postID = UniteFunctionsUC::getVal($item, "id");
			break;
			case UniteCreatorAddon::ITEMS_TYPE_MULTISOURCE:
				
				$itemSource = UniteFunctionsUC::getVal($item, "item_source");
				if($itemSource == "posts")
					$postID = UniteFunctionsUC::getVal($item, "object_id");
				
			break;
		}
		
		
		foreach($arrFieldsByRoles as $role => $fieldName){
			
			//get value
			
			//meta or term type
			$extraFieldType = $this->getExtraFieldPlaceholderType($fieldName);
			
			if(!empty($extraFieldType))		//take the value from the extra field
				$value = $this->getItemExtraFieldValue($fieldName, $postID, $extraFieldType);
			else	
					//take the value from the item
				$value = UniteFunctionsUC::getVal($item, $fieldName);
					
			$value = $this->sanitizeItemValue($value, $role);
			
			$arrContent[$role] = $value;
		}
		
		
		return($arrContent);
	}
	
	
	private function a____________MAP_FIELDS________(){}
	
	
	/**
	 * get fields by type
	 */
	private function getParamNamesByTypes($params, $type){
				
		$arrFieldNames = array();
		
		foreach($params as $param){
			
			$fieldType = UniteFunctionsUC::getVal($param, "type");
			
			//content fields check
			
			if($type == self::CONTENT_FIELDS){
				
				$typeFound = in_array($fieldType, array(UniteCreatorDialogParam::PARAM_TEXTAREA, 
					  							   UniteCreatorDialogParam::PARAM_EDITOR) 
				);
				
			}else 
				$typeFound = $fieldType == $type;
				
			if($typeFound == false)
				continue;
			
			$name = UniteFunctionsUC::getVal($param, "name");
			
			$arrFieldNames[$name] = $fieldType;
		}
		
		return($arrFieldNames);
	}
	
	
	/**
	 * get fields by the params roles 
	 */
	private function getFieldsByRoles($params){
		
		$arrRoles = array();
		
		$roleTitle = "";
		$roleHeading = "";
		$roleDescription = "";
		$roleLink = "";
		$roleImage = "";
		
		//get title
		
		$arrTextParams = $this->getParamNamesByTypes($params, UniteCreatorDialogParam::PARAM_TEXTFIELD);
		
		if(isset($arrTextParams["title"]))
			$roleTitle = "title";
		else
			$roleTitle = UniteFunctionsUC::getFirstNotEmptyKey($arrTextParams);
		
		if(!empty($roleTitle))
			unset($arrTextParams[$roleTitle]);
		
		//guess heading
		
		if(isset($arrTextParams["heading"])){
			$roleHeading = "heading";
			unset($arrTextParams[$roleHeading]);
		}
		
		//get description from text or content
		
		$arrContentParams = $this->getParamNamesByTypes($params, self::CONTENT_FIELDS);
		
		
		//get from text params with name: 'description'
		
		if(isset($arrTextParams["description"])){
			$roleDescription = $arrTextParams["description"];
			unset($arrTextParams["description"]);
		}
		
		//get from content params
		
		if(empty($roleDescription)){
			
			if(isset($arrContentParams["description"]))
				$roleDescription = "description";
			else
				$roleDescription = UniteFunctionsUC::getFirstNotEmptyKey($arrContentParams);	//get first key
			
			if(!empty($roleDescription))
				unset($arrContentParams[$roleDescription]);
		}
		
		
		//guess content - get first field from the content
		if(!empty($arrContentParams))
			$roleContent = UniteFunctionsUC::getFirstNotEmptyKey($arrContentParams);
		
		//copy from description if empty
		if(empty($roleContent))
			$roleContent = $roleDescription;
		
		
		//guess link
		
		$arrLinkParams = $this->getParamNamesByTypes($params, UniteCreatorDialogParam::PARAM_LINK);
		
		if(!empty($arrLinkParams))
			$roleLink = UniteFunctionsUC::getFirstNotEmptyKey($arrLinkParams);
		
		//guess image
		
		$arrImageParams = $this->getParamNamesByTypes($params, UniteCreatorDialogParam::PARAM_IMAGE);
		
		if(isset($arrImageParams["image"]))
			$roleImage = "image";
		
		if(!empty($arrImageParams))
			$roleImage = UniteFunctionsUC::getFirstNotEmptyKey($arrImageParams);
		
		//return the params
		
		$arrOutput = array(
			self::ROLE_TITLE => $roleTitle,
			self::ROLE_DESCRIPTION => $roleDescription,
			self::ROLE_HEADING => $roleHeading,
			self::ROLE_CONTENT => $roleContent,
			self::ROLE_LINK => $roleLink,
			self::ROLE_IMAGE => $roleImage
		);
		
		
		return($arrOutput);
	}
	
	/**
	 * check the extra fields fields mapping, modify to meta:key or term:key
	 */
	private function getFieldsByRoles_checkMetaTermKeys($arrFieldsByRoles, $fieldMap, $roleKey, $arrSettings){
		
		//--- check if related to meta and term
		
		if($fieldMap != "meta" && $fieldMap != "term")
			return($arrFieldsByRoles);

		//---- get the extra field key
		
        $fieldKey = UniteFunctionsUC::getVal($arrSettings, "extrafieldkey_" . $roleKey);
        
        if(empty($fieldKey))
			return($arrFieldsByRoles);
        
        //check the key and sanitize
        
        switch($fieldMap){
        	case "meta":	// check that meta field is valid
        		
        		$isValid = UniteFunctionsUC::isMetaKeyValid($fieldKey);
        		
        		if($isValid == false){
        			$fieldKey = UniteFunctionsUC::sanitize($fieldKey, UniteFunctionsUC::SANITIZE_HTML);
        			UniteFunctionsUC::throwError("The meta name: $fieldKey is not valid");
        		}
        			        		
        	break;
        	case "term":	//check that taxonomy is valid
        		$isValid = UniteFunctionsUC::isTaxonomyNameValid($fieldKey);
				
        		if($isValid == false){
        			$fieldKey = UniteFunctionsUC::sanitize($fieldKey, UniteFunctionsUC::SANITIZE_HTML);
        			UniteFunctionsUC::throwError("The taxonomy name: $fieldKey is not valid");
        		}
        	        		
        	break;
        }

        //put the new field with the key
        
        $arrFieldsByRoles[$roleKey] = $fieldMap.":".$fieldKey;
	       
		return($arrFieldsByRoles);
	}
	
	/**
	 * Get final fields mapping by roles, using manual settings if enabled.
	 *
	 * @param array $paramsItems The widget params items
	 * @param array $arrSettings The widget settings
	 * @return array The final mapping: role => paramName
	 */
	private function getFieldsByRolesFinal($paramsItems, $arrSettings) {
		
		$itemsType = UniteFunctionsUC::getVal($arrSettings, "item_type");
		
		switch($itemsType){
			case UniteCreatorAddon::ITEMS_TYPE_POST:
				
				$arrFieldsByRoles = array(
			        self::ROLE_TITLE => "title",
			        self::ROLE_DESCRIPTION => "intro_full",
			        self::ROLE_HEADING => "intro",
			        self::ROLE_CONTENT =>"content",
			        self::ROLE_IMAGE =>"image",
			        self::ROLE_LINK =>"link",
				);
				
			break;
			default:
	    		$arrFieldsByRoles = $this->getFieldsByRoles($paramsItems);
			break;
		}
				
			    
	    // Check if manual mapping is enabled
	    $isMappingEnabled = UniteFunctionsUC::getVal($arrSettings, "enable_mapping");
	    $isMappingEnabled = UniteFunctionsUC::strToBool($isMappingEnabled);
	    
	    if ($isMappingEnabled == false)
	        return $arrFieldsByRoles;
		
	    $arrManualRoles = array(
	        self::ROLE_TITLE,
	        self::ROLE_DESCRIPTION,
	        self::ROLE_HEADING,
	        self::ROLE_CONTENT
	    );
	
	    foreach ($arrManualRoles as $roleKey) {
	    	
	        $manualValue = UniteFunctionsUC::getVal($arrSettings, "fieldmap_" . $roleKey);
	        
	        if (!empty($manualValue) && $manualValue !== self::ROLE_AUTO) {
	            $arrFieldsByRoles[$roleKey] = $manualValue;
	            
	            //check for meta or term, add the extra key
	            
	        	$arrFieldsByRoles = $this->getFieldsByRoles_checkMetaTermKeys($arrFieldsByRoles, $manualValue, $roleKey, $arrSettings);
	        	
	        }
	        
	    }
	    
	    //-- add extra fields:
	    $arrExtraFields = array(
	    	self::ROLE_EXTRA_FIELD1,
	    	self::ROLE_EXTRA_FIELD2,
	    	self::ROLE_EXTRA_FIELD3,
	    	self::ROLE_EXTRA_FIELD4
	    );
		
	    foreach($arrExtraFields as $roleKey){
	    	
	        $fieldMap = UniteFunctionsUC::getVal($arrSettings, "fieldmap_" . $roleKey);
	        	
	        $arrFieldsByRoles[$roleKey] = "";
	        
	        if($fieldMap == "nothing" || empty($fieldMap))
	        	continue;
	        
	        $arrFieldsByRoles = $this->getFieldsByRoles_checkMetaTermKeys($arrFieldsByRoles, $fieldMap, $roleKey, $arrSettings);
	        
	    }
	    
	    
	    return $arrFieldsByRoles;
	}	
	
	
	private function a____________SETTINGS________(){}
	
/**
 * Add UI for fields mapping by roles.
 *
 * Adds enable mapping toggle + "Auto" option for each field.
 *
 * @param UniteCreatorDialogSettings $objSettings
 * @param string $name
 * @param array $paramsItems
 */
private function addFieldsMappingSettings($objSettings, $name, $paramsItems, $isPost) {
	
	
    // ---- Add master toggle: enable mapping yes/no ----
    $arrParam = array();
    $arrParam["origtype"] = UniteCreatorDialogParam::PARAM_RADIOBOOLEAN;
    $arrParam["elementor_condition"] = array($name . "_enable" => "true");
    $arrParam["description"] = __("Enable manual fields mapping by roles. The post related fields are relevant only if the content from posts", "unlimited-elements-for-elementor");
	
    $objSettings->addRadioBoolean(
        "{$name}_enable_mapping",
        __("Enable Fields Mapping", "unlimited-elements-for-elementor"),
        false,
        "Yes",
        "No",
        $arrParam
    );
	
	
    // ---- Build field options: only textfield, textarea, editor ----
    
    $arrExtraFieldOptions = array();
    $arrExtraFieldOptions["nothing"] = __("[Select Content]", "unlimited-elements-for-elementor");
    $arrExtraFieldOptions["meta"] = __("Post Meta Field", "unlimited-elements-for-elementor");
    $arrExtraFieldOptions["term"] = __("Post Term", "unlimited-elements-for-elementor");
    
    $arrFieldOptions = array();
    $arrFieldOptions[self::ROLE_AUTO] = __("[Auto Detect]", "unlimited-elements-for-elementor");
    
    if($isPost == true){
    	
 		$arrFieldOptions['title']   = __("Post Title", "unlimited-elements-for-elementor");
        $arrFieldOptions['intro'] = __("Post Intro", "unlimited-elements-for-elementor");
        $arrFieldOptions['intro_full'] = __("Post Intro Full", "unlimited-elements-for-elementor");
        $arrFieldOptions['content'] = __("Post Content", "unlimited-elements-for-elementor");
                
    }else{
    	
	    foreach ($paramsItems as $param) {
	        $paramName = UniteFunctionsUC::getVal($param, "name");
	        $paramTitle = UniteFunctionsUC::getVal($param, "title");
	        $paramType = UniteFunctionsUC::getVal($param, "type");
	
	        if (empty($paramName))
	            continue;
	
	        $isTextType = ($paramType === UniteCreatorDialogParam::PARAM_TEXTFIELD);
	        $isContentType = in_array($paramType, array(
	            UniteCreatorDialogParam::PARAM_TEXTAREA,
	            UniteCreatorDialogParam::PARAM_EDITOR
	        ));
	
	        if (!$isTextType && !$isContentType)
	            continue;
	
	        $arrFieldOptions[$paramName] = $paramTitle;
	    }
    }	
    
    //modify extra fields
    $arrExtraFieldOptions = array_merge($arrExtraFieldOptions, $arrFieldOptions);
    unset($arrExtraFieldOptions[self::ROLE_AUTO]);
    
    
    //add extra field option. meta or term
    
    $arrFieldOptions['meta'] = __("Post Meta Field", "unlimited-elements-for-elementor");
    $arrFieldOptions['term'] = __("Post Term", "unlimited-elements-for-elementor");
    
    
    // ---- Flip options: label => value ----
    
    $arrFieldOptions = array_flip($arrFieldOptions);
    
    $arrExtraFieldOptions = array_flip($arrExtraFieldOptions);
    
    
    // ---- Define roles (only text/content roles) ----
    
    $arrRoles = array(
        self::ROLE_TITLE => __("Title Field", "unlimited-elements-for-elementor"),
        self::ROLE_DESCRIPTION => __("Description Field", "unlimited-elements-for-elementor"),
        self::ROLE_HEADING => __("Heading Field", "unlimited-elements-for-elementor"),
        self::ROLE_CONTENT => __("Content Field", "unlimited-elements-for-elementor"),
        self::ROLE_EXTRA_FIELD1 => __("Extra Field 1", "unlimited-elements-for-elementor"),
        self::ROLE_EXTRA_FIELD2 => __("Extra Field 2", "unlimited-elements-for-elementor"),
        self::ROLE_EXTRA_FIELD3 => __("Extra Field 3", "unlimited-elements-for-elementor"),
        self::ROLE_EXTRA_FIELD4 => __("Extra Field 4", "unlimited-elements-for-elementor")
    );
    
    
    // ---- Add a select control for each text/content role ----
    foreach ($arrRoles as $roleKey => $roleLabel) {

        $arrParam = array();
        $arrParam["origtype"] = UniteCreatorDialogParam::PARAM_DROPDOWN;
        $arrParam["elementor_condition"] = array(
            $name . "_enable" => "true",
            "{$name}_enable_mapping" => "true"
        );
		
        $arrOptions = $arrFieldOptions;
        $defaultValue = self::ROLE_AUTO;
        
        $isExtraField = (strpos($roleKey, "field") !== false);
        
        if($isExtraField == true){
        	$arrOptions = $arrExtraFieldOptions;
        	$defaultValue = "nothing";
        }	
        
        $objSettings->addSelect(
            "{$name}_fieldmap_{$roleKey}",
            $arrOptions,	//options
            $roleLabel,		//label
            $defaultValue,		//default
            $arrParam
        );
        
        
        //add meta and term field name
                	
        $arrParam = array();
        $arrParam["origtype"] = UniteCreatorDialogParam::PARAM_TEXTFIELD;
        $arrParam["description"] = "Meta Field name or Term Taxonomy name";
         
        $arrParam["elementor_condition"] = array(
            $name . "_enable" => "true",
            "{$name}_enable_mapping" => "true",
            "{$name}_fieldmap_{$roleKey}"=>array("meta","term")
        );
        
        $objSettings->addTextBox("{$name}_extrafieldkey_{$roleKey}", "", esc_html__("Field Name", "unlimited-elements-for-elementor"),$arrParam);
                
        
    }
}
	
	
	/**
	 * put schema settings
	 */
	public function addSchemaSettings(&$objSettings, $name, $param){
		
		$arrParam = array();
		$arrParam["origtype"] = UniteCreatorDialogParam::PARAM_RADIOBOOLEAN;
		$arrParam["description"] = UniteFunctionsUC::getVal($param, "description");
		
		$objSettings->addRadioBoolean($name."_enable", $param["title"],false,"Yes","No",$arrParam);
		
		
		if(GlobalsUnlimitedElements::$enableCustomSchema == true){
			
			//------- from list / custom
			
			$arrParam = array();
			$arrParam["origtype"] = UniteCreatorDialogParam::PARAM_DROPDOWN;
			$arrParam["elementor_condition"] = array($name."_enable"=>"true");
			
			
			$arrOptions = array(
				__("From List","unlimited-elements-for-elementor") => "list",
				__("Custom","unlimited-elements-for-elementor") => "custom",
			);
			
			$objSettings->addSelect($name."_selection",$arrOptions, __("Schema Source","unlimited-elements-for-elementor") , "list", $arrParam);
		
			//------- custom textarea
			
			$arrParam = array();
			$arrParam["origtype"] = UniteCreatorDialogParam::PARAM_TEXTAREA;
			$arrParam["elementor_condition"] = array($name."_enable"=>"true",$name."_selection"=>"custom");
			
			$descripiton = __("Paste any JSON schema from ","unlimited-elements-for-elementor");
			$descripiton .= "<a href='https://schema.org/Person' target='_blank'>schema.org</a>";
			$descripiton .= __(" site","unlimited-elements-for-elementor");
			
			$descripiton .= __("<br>Posible Placeholders Are: %title%, %description%, %heading%, %link%, %content%, %image%, %field1%, %field2%, %field3%, %field4% ","unlimited-elements-for-elementor");
			
			$arrParam["description"] = $descripiton;
			
			$objSettings->addTextArea($name."_custom", "", __("Custom JSON Schema","unlimited-elements-for-elementor") , $arrParam);
			
		}
		
		
		//------- schema types
		
		$arrSchemas = $this->getArrSchemas();
			
		$arrOptions = array();
		foreach($arrSchemas as $schema){
			
			$schemaName = UniteFunctionsUC::getVal($schema, "name");
			$schemaTitle = UniteFunctionsUC::getVal($schema, "title");
			
			$arrOptions[$schemaName] = $schemaTitle;
		}

		$arrParam = array();
		$arrParam["origtype"] = UniteCreatorDialogParam::PARAM_DROPDOWN;
		$arrParam["elementor_condition"] = array($name."_enable"=>"true",$name."_selection"=>"list");
		
		if(GlobalsUnlimitedElements::$enableCustomSchema == false)
			$arrParam["elementor_condition"] = array($name."_enable"=>"true");
		
		
		$arrOptions = array_flip($arrOptions);
		
		$title = __('Schema Type',"unlimited-elements-for-elementor");
		
		$objSettings->addSelect("{$name}_type", $arrOptions, $title, "faqpage", $arrParam);
		
		//------- main name
		
		$arrParam = array();
		$arrParam["origtype"] = UniteCreatorDialogParam::PARAM_TEXTFIELD;
		$arrParam["add_dynamic"] = true;
		$arrParam["elementor_condition"] = array($name."_enable"=>"true",$name."_type"=>
			array("howto", 
				  "recipe", 
				  "faqpage", 
				  "itemlist", 
				  "eventseries", 
				  "musicplaylist", 
				  "searchresultspage"));
		
		$arrParam["description"] = __('Use to describe the action, like how to tie a shoes',"unlimited-elements-for-elementor");
		$arrParam["label_block"] = true;
		
		$title = __('Schema Main Title',"unlimited-elements-for-elementor");
		
		$objSettings->addTextBox($name."_title","", $title, $arrParam);
		
		
		//------- hr before mapping -------
		
		$arrParam = array();
		$arrParam["origtype"] = UniteCreatorDialogParam::PARAM_HR;
		$arrParam["elementor_condition"] = array($name."_enable"=>"true");
		
		$objSettings->addHr($name."_hr_before_mapping", $arrParam);
		
		
		//---- add schema mapping here:
		
		if(empty($this->objAddon))
			UniteFunctionsUC::throwError("No addon found, please set addon for the schema object");
		
		$itemsType = $this->objAddon->getItemsType();
		
		$isPost = ($itemsType == UniteCreatorAddon::ITEMS_TYPE_POST);
					
		$paramsItems = $this->objAddon->getParamsItems();
		
		if(!empty($paramsItems))
			$this->addFieldsMappingSettings($objSettings, $name, $paramsItems, $isPost);
		
		
		//------- debug ------
		
		$arrParam = array();
		$arrParam["origtype"] = UniteCreatorDialogParam::PARAM_RADIOBOOLEAN;
		$arrParam["elementor_condition"] = array($name."_enable"=>"true");
		$arrParam["description"] = __('Show schema debug in front end footer',"unlimited-elements-for-elementor");
		
		$title = __('Show Schema Debug',"unlimited-elements-for-elementor");
		
		$objSettings->addRadioBoolean($name."_debug", $title, false, "Yes","No", $arrParam);
		
		//------- debug ------
		
		$arrParam = array();
		$arrParam["origtype"] = UniteCreatorDialogParam::PARAM_STATIC_TEXT;
		$arrParam["elementor_condition"] = array($name."_enable"=>"true");
		
		$text = __('To show post meta fields and terms turn on debug option in Advanced Section',"unlimited-elements-for-elementor");
		
		$objSettings->addStaticText($text, $name."_debug_meta_text", $arrParam);
		
	}
	
	
	/**
	 * add schema settings for posts option
	 */
	public function addSchemaMultipleSettings(&$objSettings){
		
		$param = array();
		$param["title"] = __('Enable Schema',"unlimited-elements-for-elementor");
		$param["description"] = "";
		
		$this->addSchemaSettings($objSettings, self::MULTIPLE_SCHEMA_NAME, $param);
		
	}
	
	
	private function a____________DEBUG________(){}
	
	
	/**
	 * show the debug message under the widget
	 * 
	 */
	private function showDebugMessage(){
		
		$message = __('Schema Debug: This widget will generate schema debug at the footer',"unlimited-elements-for-elementor");

		$html = HelperHtmlUC::getDebugWarningMessageHtml($message);
		
		uelm_echo($html);
		
	}
	
	/**
	 * show schema debug
	 */
	private function showDebugSchema($schemaType, $arrSchema){
		
		dmp("Schema Output Debug: $schemaType");
		
		dmp("The Fields Mapping and Content");
		
		HelperHtmlUC::putHtmlDataDebugBox($this->debugFields);
		
		dmp("The Schema Output");
		
		HelperHtmlUC::putHtmlDataDebugBox($arrSchema);
	}
	
	
	private function a____________SCHEMAS________(){}


	/**
	 * Clean only selected keys from schema array.
	 *
	 * @param mixed $data
	 * @param array $keysToClean  Keys that should be removed when empty.
	 * @return mixed
	 */
	private function cleanSchemaArray($data, $keysToClean = array()) {
	
	    // If not array – return as is
	    if (!is_array($data)) {
	        return $data;
	    }
	
	    $clean = array();
	
	    foreach ($data as $key => $value) {
	
	        // --- Nested array ---
	        if (is_array($value)) {
	
	            // Clean recursively
	            $value = $this->cleanSchemaArray($value, $keysToClean);
	
	            // If the key is one of the "to clean" keys AND empty – skip it
	            if (in_array($key, $keysToClean, true) && empty($value)) {
	                continue;
	            }
	
	            $clean[$key] = $value;
	            continue;
	        }
	
	        // Normalize scalar values
	        if (is_string($value)) {
	            $value = trim($value);
	        }
	
	        // --- Clean only selected keys ---
	        if (in_array($key, $keysToClean, true)) {
	
	            // Remove ONLY if empty (empty string or null)
	            if ($value === '' || $value === null) {
	                continue;
	            }
	        }
	
	        // Keep ZERO, false, numeric values
	        $clean[$key] = $value;
	    }
	
	    return $clean;
	}
	

	/**
	 * normalize custom schema template, remove the tags
	 * in case that the user pasted the "script" tag around json
	 */
	private function normalizeCustomSchemaTemplate($strJsonSchema){
		
		$strJsonSchema = trim($strJsonSchema);
		
		$strJsonSchema = strip_tags($strJsonSchema,"script");
		
		return($strJsonSchema);
	}
	
	/**
	 * get all schemas items content
	 */
	private function getAllSchemaItemsContent($data, $schemaType){
		
		$arrAddonsData = UniteFunctionsUC::getVal($data, "addons");
		
		if(empty($arrAddonsData))
			return(null);
			
		$arrItemsContent = array();
		
		foreach($arrAddonsData as $addonData){
			
			$items = UniteFunctionsUC::getVal($addonData, "items");
			$params = UniteFunctionsUC::getVal($addonData, "params");
			$settings = UniteFunctionsUC::getVal($addonData, "settings");
			
			
			//field quessing
			$arrFieldsByRoles = $this->getFieldsByRolesFinal($params, $settings);
			
			$schemaContent = null;
			if($schemaType == "custom"){
				
				$schemaContent = UniteFunctionsUC::getVal($settings, "custom");
				
				$schemaContent = $this->normalizeCustomSchemaTemplate($schemaContent);
			}

			$itemType = UniteFunctionsUC::getVal($settings, "item_type");
			
			//add debug
			
			$arrDebug = array();
			
			if(self::$showDebug == true){
				
				$arrParamsAssoc = UniteFunctionsUC::arrayToAssoc($params, "name", "type");
				
				$arrDebug = array("params"=>$arrParamsAssoc,"fieldsbyroles"=>$arrFieldsByRoles);
								
			}
			
			$arrDebugContent = array();
			
			foreach($items as $item){
				
				$arrContent = $this->getItemSchemaContent($item, $arrFieldsByRoles, $itemType);
				
				if(self::$showDebug == true)
					$arrDebugContent[] = $arrContent;
				
				if(!empty($schemaContent))
					$arrContent["schema_custom_json"] = $schemaContent;
				
				$arrItemsContent[] = $arrContent;
			}
			
			//---- show debug
			
			if(self::$showDebug == true){
								
				$arrDebug["items_content"] = $arrDebugContent;
				
				$this->debugFields[$schemaType][] = $arrDebug;
			}
			
		}
		
		
		return($arrItemsContent);
	}
	
	
	/**
	 * Generate schema structure based on specified type
	 */
	private function generateSchemaByType($schemaType, $data) {
		
	    $items = $this->getAllSchemaItemsContent($data, $schemaType);
	   	
	    $title = UniteFunctionsUC::getVal($data, "title");
			    	    
		switch ($schemaType) {
		    case "person":
		        return $this->schemaPerson($items);
		    case "howto":
		        return $this->schemaHowTo($items, $title);
		    case "course":
		        return $this->schemaCourse($items);
		    case "recipe":
		        return $this->schemaRecipe($items);
		    case "book":
		        return $this->schemaBook($items);
		    case "itemlist":
		        return $this->schemaItemList($items, $title);
		    case "event":
		        return $this->schemaEvent($items);
		    case "place":
		        return $this->schemaPlace($items);
		    case "product":
		        return $this->schemaProduct($items);		        
		    case "touristdestination":
		        return $this->schemaTouristDestination($items);
		    case "eventseries":
		        return $this->schemaEventSeries($items);
		    case "musicplaylist":
		        return $this->schemaMusicPlaylist($items);
		    case "searchresultspage":
		        return $this->schemaSearchResultsPage($items, $title);
		    case "newsarticle":
		        return $this->schemaNewsArticle($items);
		    case "custom":
		    	
		    	if(GlobalsUnlimitedElements::$enableCustomSchema == true){
			    	$jsonSchema = $this->schemaCustom($items, $title);
			    	
			    	return($jsonSchema);
		    	}else 
		    		return(null);
		    	
		    break;
		    default:
		    case "faqpage":
		        return $this->schemaFaq($items, $title);
		}
		
}

	private function a____________CUSTOM_SCHEMA________(){}
	
	
	/**
	 * replace placeholders in the value string
	 */
	private function replacePlaceholders_values($value, $item){
		
		if(is_string($value) == false)
			return($value);
					
		//check for %something%
		if (preg_match('/%[a-zA-Z0-9_]+%/', $value) == false) 
			return($value);
		
		$arrRoles = $this->getArrRolesKeys();
				
		foreach($arrRoles as $role){
			
			$content = UniteFunctionsUC::getVal($item, $role);
			
			$value = str_replace("%{$role}%", $content, $value);
		}
		
		//check error, if found some unknown placeholder:
		if (preg_match('/%[a-zA-Z0-9_]+%/', $value) == true){
			
			if(HelperUC::isRunCodeOnce("uc_schema_replace_placeholders") == true){
				
				$message = "Custom Schema Error: Unknown Placeholder: ".$value." Please change for a known placeholder from the list.";
				
				$htmlError = HelperHtmlUC::getErrorMessageHtml($message,"",true);
				dmp($htmlError);
				
			}
			
		}
		
		
		return($value);		
	}
	
	
	/**
	 * replace placeholders
	 */
	private function replacePlaceholders($arrSchema, $item){
		
		if(empty($arrSchema))
			return($arrSchema);
		
		foreach ($arrSchema as $key => $value){
			
	        if (is_array($value))
	            $value = $this->replacePlaceholders($value, $item);
	         else {
	        	$value = $this->replacePlaceholders_values($value, $item);
	         }
	         
	         $arrSchema[$key] = $value;
	         
	    }
		
		
	    return($arrSchema);
	}
	
	
	/**
	 * put schema custom item
	 */
	private function schemaCustomItem($item){
		
		$schemaJson = UniteFunctionsUC::getVal($item, "schema_custom_json");
		
		$this->lastCustomSchema = $schemaJson;
		
		if(empty($schemaJson))
			return($schemaJson);
		
		try{
			
			$arrSchema = UniteFunctionsUC::jsonDecodeValidate($schemaJson);
			
			$arrSchema = $this->replacePlaceholders($arrSchema, $item);
			
		}catch(Exception $e){
			
			if(self::$showJsonErrorOnce == true)
				return(array());
			
			self::$showJsonErrorOnce = true;
			
			$message = $e->getMessage();
			
			$message = "Schema JSON Decode Error: <b> $message </b> in schema: ";
			
			$htmlError = HelperHtmlUC::getErrorMessageHtml($message,"",true);
			
			dmp($htmlError);
			dmpHtml($schemaJson);
			
			$message2 = "You can copy paste this json into chat gpt, and it will tell you where is the error";
			$htmlError = HelperHtmlUC::getErrorMessageHtml($message2,"",true);
			
			dmp($htmlError);
			
			return(array());
		}
		
		return($arrSchema);
	}
	
	
	
	/**
	 * custom schema
	 */
	private function schemaCustom($items, $title){
		
		$arrSchema = array();
		
		foreach($items as $item){
			
			$arrSchemaItem = $this->schemaCustomItem($item);
			
			$arrSchema[] = $arrSchemaItem;
		}
		
		return($arrSchema);
	}
	
	
	private function a____________SCHEMA_FUNCTIONS________(){}
	
	

/**
 * FAQ
 */
private function schemaFaq($items, $title = "") {
	
    $schema = array(
        '@context' => self::SCHEMA_ORG_SITE,
        '@type' => 'FAQPage',
    );
    if (!empty($title)) $schema['name'] = $title;

    $schema['mainEntity'] = array();
    
    foreach ($items as $item) {
    	
		 $question = array(
            '@type' => 'Question',
            'name'  => $item[self::ROLE_TITLE],
            'acceptedAnswer' => array(
                '@type' => 'Answer',
                'text'  => $item[self::ROLE_CONTENT],
            ),
        );
		
        // Optional image on Question
        if (!empty($item[self::ROLE_IMAGE])) {
            $question['image'] = $item[self::ROLE_IMAGE];
        }

        $schema['mainEntity'][] = $question;        
    }
    return $schema;
}

/**
 * HowTo
 */
private function schemaHowTo($items, $title = "") {
    $schema = array(
        '@context' => self::SCHEMA_ORG_SITE,
        '@type' => 'HowTo',
    );
    if (!empty($title)) $schema['name'] = $title;

    $schema['step'] = array();
    foreach ($items as $item) {
    	
		 $step = array(
            '@type' => 'HowToStep',
            'name'  => $item[self::ROLE_TITLE],
            'text'  => $item[self::ROLE_DESCRIPTION],
            'url'   => $item[self::ROLE_LINK],
        );
        if (!empty($item[self::ROLE_IMAGE])) {
            $step['image'] = $item[self::ROLE_IMAGE];
        }
        $schema['step'][] = $step;
        
    }
    
    return $schema;
}


/**
 * Recipe
 */
private function schemaRecipe($items, $title = "") {
    
	$schema = array(
        '@context' => self::SCHEMA_ORG_SITE,
        '@type' => 'Recipe',
    );
    
    if (!empty($title)) 
    	$schema['name'] = $title;
	
    $schema['recipeInstructions'] = array();
    
    foreach ($items as $item) {
    	
		 $step = array(
            '@type' => 'HowToStep',
            'name'  => $item[self::ROLE_TITLE],
            'text'  => $item[self::ROLE_DESCRIPTION],
            'url'   => $item[self::ROLE_LINK],
        );
        if (!empty($item[self::ROLE_IMAGE])) {
            $step['image'] = $item[self::ROLE_IMAGE];
        }
        
        $schema['recipeInstructions'][] = $step;
    }
    
    return $schema;
}



/**
 * ItemList
 */
private function schemaItemList($items, $title = "") {
    $schema = array(
        '@context' => self::SCHEMA_ORG_SITE,
        '@type' => 'ItemList',
    );
    if (!empty($title)) $schema['name'] = $title;
	
    $schema['itemListElement'] = array();
    $position = 1;
    foreach ($items as $item) {
    	
 		$listItem = array(
            '@type'    => 'ListItem',
            'position' => $position++,
 			'item' => array(
	            'name' => $item[self::ROLE_TITLE],
	            'url'  => $item[self::ROLE_LINK]
 			)
        );
        
        if (!empty($item[self::ROLE_IMAGE])) {
            $listItem['item']['image'] = $item[self::ROLE_IMAGE];
        }
        
        $schema['itemListElement'][] = $listItem;
                
    }
    return $schema;
}

/**
 * SearchResultsPage
 */
private function schemaSearchResultsPage($items, $title = "") {
    $schema = array(
        '@context' => self::SCHEMA_ORG_SITE,
        '@type' => 'SearchResultsPage',
    );
    if (!empty($title)) $schema['name'] = $title;

    $schema['mainEntity'] = array(
        '@type' => 'ItemList',
        'itemListElement' => array(),
    );

    $position = 1;
    foreach ($items as $item) {
    	
 		$listItem = array(
            '@type'    => 'ListItem',
            'position' => $position++,
            'name'     => $item[self::ROLE_TITLE],
            'url'      => $item[self::ROLE_LINK],
        );
        if (!empty($item[self::ROLE_IMAGE])) {
            $listItem['image'] = $item[self::ROLE_IMAGE];
        }
        $schema['mainEntity']['itemListElement'][] = $listItem;        
        
    }
    return $schema;
}


/**
 * Person Schema
 */
private function schemaPerson($items) {

    $schema = array();

    foreach ($items as $item) {

        $person = array(
            '@context'     => self::SCHEMA_ORG_SITE,
            '@type'        => 'Person',
            'name'         => $item[self::ROLE_TITLE],
        );

        // Optional fields added only if not empty
        if (!empty($item[self::ROLE_HEADING])) {
            $person['jobTitle'] = $item[self::ROLE_HEADING];
        }

        if (!empty($item[self::ROLE_DESCRIPTION])) {
            $person['description'] = $item[self::ROLE_DESCRIPTION];
        }

        if (!empty($item[self::ROLE_IMAGE])) {
            $person['image'] = $item[self::ROLE_IMAGE];
        }

        if (!empty($item[self::ROLE_LINK])) {
            $person['sameAs'] = array($item[self::ROLE_LINK]); // must be array
        }

        $schema[] = $person;
    }

    return $schema;
}


/**
 * Course
 */
private function schemaCourse($items) {

    $schema = array();

    foreach ($items as $item) {

        $course = array(
            '@context'    => self::SCHEMA_ORG_SITE,
            '@type'       => 'Course',
            'name'        => $item[self::ROLE_TITLE],
        );

        // optional: description
        if (!empty($item[self::ROLE_DESCRIPTION])) {
            $course['description'] = $item[self::ROLE_DESCRIPTION];
        }

        // Build provider only if needed
        $provider = array(
            '@type' => 'Organization'
        );

        $hasProvider = false;

        if (!empty($item[self::ROLE_HEADING])) {
            $provider['name'] = $item[self::ROLE_HEADING];
            $hasProvider = true;
        }

        if (!empty($item[self::ROLE_LINK])) {
            $provider['sameAs'] = array($item[self::ROLE_LINK]); // must be array
            $hasProvider = true;
        }

        if ($hasProvider) {
            $course['provider'] = $provider;
        }

        // Optional image
        if (!empty($item[self::ROLE_IMAGE])) {
            $course['image'] = $item[self::ROLE_IMAGE];
        }

        $schema[] = $course;
    }

    return $schema;
}



/**
 * NewsArticle (schema.org/NewsArticle - Article subtype for news)
 */
private function schemaNewsArticle($items) {

    $schema = array();

    foreach ($items as $item) {

        $article = array(
            '@context' => self::SCHEMA_ORG_SITE,
            '@type'    => 'NewsArticle',
            'headline' => $item[self::ROLE_TITLE],
        );

        if (!empty($item[self::ROLE_CONTENT])) {
            $article['articleBody'] = $item[self::ROLE_CONTENT];
        }

        if (!empty($item[self::ROLE_DESCRIPTION])) {
            $article['description'] = $item[self::ROLE_DESCRIPTION];
        }

        if (!empty($item[self::ROLE_IMAGE])) {
            $article['image'] = $item[self::ROLE_IMAGE];
        }

        if (!empty($item[self::ROLE_LINK])) {
            $article['url'] = $item[self::ROLE_LINK];
            $article['mainEntityOfPage'] = $item[self::ROLE_LINK];
        }

        if (!empty($item[self::ROLE_HEADING])) {
            $article['author'] = array(
                '@type' => 'Person',
                'name'  => $item[self::ROLE_HEADING]
            );
        }

        $schema[] = $article;
    }

    return $schema;
}

/**
 * Book
 */
private function schemaBook($items) {

    $schema = array();

    foreach ($items as $item) {

        $book = array(
            '@context' => self::SCHEMA_ORG_SITE,
            '@type'    => 'Book',
            'name'     => $item[self::ROLE_TITLE],
        );

        // Optional: Description
        if (!empty($item[self::ROLE_DESCRIPTION])) {
            $book['description'] = $item[self::ROLE_DESCRIPTION];
        }

        // Optional: Image
        if (!empty($item[self::ROLE_IMAGE])) {
            $book['image'] = $item[self::ROLE_IMAGE];
        }

        // Optional: URL
        if (!empty($item[self::ROLE_LINK])) {
            $book['url'] = $item[self::ROLE_LINK];
            $book['sameAs'] = array($item[self::ROLE_LINK]);
        }

        // Optional: Author (use heading field as author name)
        if (!empty($item[self::ROLE_HEADING])) {
            $book['author'] = array(
                '@type' => 'Person',
                'name'  => $item[self::ROLE_HEADING]
            );
        }

        $schema[] = $book;
    }

    return $schema;
}


	/**
	 * Event
	 */
	private function schemaEvent($items) {
	    $schema = array();
	    foreach ($items as $item) {
	        $schema[] = array(
	            '@context' => self::SCHEMA_ORG_SITE,
	            '@type' => 'Event',
	            'name' => $item[self::ROLE_TITLE],
	            'description' => $item[self::ROLE_DESCRIPTION],
	            'image' => $item[self::ROLE_IMAGE],
	            'url' => $item[self::ROLE_LINK],
	        );
	    }
	    return $schema;
	}

	
/**
 * EventSeries
 */
private function schemaEventSeries($items) {

    $schema = array();

    foreach ($items as $item) {

        $eventSeries = array(
            '@context' => self::SCHEMA_ORG_SITE,
            '@type'    => 'EventSeries',
            'name'     => $item[self::ROLE_TITLE],
        );

        // Optional: description
        if (!empty($item[self::ROLE_DESCRIPTION])) {
            $eventSeries['description'] = $item[self::ROLE_DESCRIPTION];
        }

        // Optional: image
        if (!empty($item[self::ROLE_IMAGE])) {
            $eventSeries['image'] = $item[self::ROLE_IMAGE];
        }

        // Optional: sameAs (must be an array)
        if (!empty($item[self::ROLE_LINK])) {
            $eventSeries['sameAs'] = array($item[self::ROLE_LINK]);
        }

        $schema[] = $eventSeries;
    }

    return $schema;
}

/**
 * MusicPlaylist
 */
private function schemaMusicPlaylist($items) {
    $schema = array();
    foreach ($items as $item) {
        $playlist = array(
            '@context'    => self::SCHEMA_ORG_SITE,
            '@type'       => 'MusicPlaylist',
            'name'        => $item[self::ROLE_TITLE],
            'description' => $item[self::ROLE_DESCRIPTION],
        );
        if (!empty($item[self::ROLE_IMAGE])) {
            $playlist['image'] = $item[self::ROLE_IMAGE];
        }
        $schema[] = $playlist;
    }
    return $schema;
}

/**
 * Place
 */
private function schemaPlace($items) {

    $schema = array();

    foreach ($items as $item) {

        $place = array(
            '@context' => self::SCHEMA_ORG_SITE,
            '@type'    => 'Place',
            'name'     => $item[self::ROLE_TITLE],
        );

        // optional: description
        if (!empty($item[self::ROLE_DESCRIPTION])) {
            $place['description'] = $item[self::ROLE_DESCRIPTION];
        }

        // optional: image
        if (!empty($item[self::ROLE_IMAGE])) {
            $place['image'] = $item[self::ROLE_IMAGE];
        }

        // optional: url / sameAs
        if (!empty($item[self::ROLE_LINK])) {
            $place['url'] = $item[self::ROLE_LINK];
            $place['sameAs'] = array($item[self::ROLE_LINK]);
        }

        $schema[] = $place;
    }

    return $schema;
}


/**
 * Product
 */
private function schemaProduct($items) {

    $schema = array();

    foreach ($items as $item) {

        $product = array(
            '@context' => self::SCHEMA_ORG_SITE,
            '@type'    => 'Product',
            'name'     => $item[self::ROLE_TITLE],
        );

        // Optional: description
        if (!empty($item[self::ROLE_DESCRIPTION])) {
            $product['description'] = $item[self::ROLE_DESCRIPTION];
        }

        // Optional: image
        if (!empty($item[self::ROLE_IMAGE])) {
            $product['image'] = $item[self::ROLE_IMAGE];
        }

        // Optional: main URL + sameAs
        if (!empty($item[self::ROLE_LINK])) {
            $product['url']    = $item[self::ROLE_LINK];
            $product['sameAs'] = array($item[self::ROLE_LINK]); // must be array for Google
        }

        $schema[] = $product;
    }

    return $schema;
}


/**
 * TouristDestination
 */
private function schemaTouristDestination($items) {

    $schema = array();

    foreach ($items as $item) {

        $dest = array(
            '@context' => self::SCHEMA_ORG_SITE,
            '@type'    => 'TouristDestination',
            'name'     => $item[self::ROLE_TITLE],
        );

        // optional description
        if (!empty($item[self::ROLE_DESCRIPTION])) {
            $dest['description'] = $item[self::ROLE_DESCRIPTION];
        }

        // optional image
        if (!empty($item[self::ROLE_IMAGE])) {
            $dest['image'] = $item[self::ROLE_IMAGE];
        }

        // optional sameAs / link
        if (!empty($item[self::ROLE_LINK])) {
            $dest['sameAs'] = array($item[self::ROLE_LINK]);
            $dest['url']    = $item[self::ROLE_LINK]; // recommended for Google
        }

        $schema[] = $dest;
    }

    return $schema;
}


}

