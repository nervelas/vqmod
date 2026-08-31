<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved. 
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
if ( ! defined( 'ABSPATH' ) ) exit;

class UELanguageAlphabets {
	
    private $alphabets = array();
	
    public function __construct(){
    	
    	$this->alphabets = UELanguageAlphabetsArray::$alphabets;
    }
    
    /**
     * 
     * get the alphabet
     * @param $language
     */
    public function getAlphabet($language) {
        
    	$language = strtolower($language);
        
    	if(isset($this->alphabets[$language]) == false)
    		return(array());
    		
    	return($this->alphabets[$language]);
    }
    
    /**
     * check or output error if language not match
     */
    private function checkOutputError($arrAlphabet){
		
    	if(empty($arrAlphabet)){
			dmp("$arg1 language not exists. Please choose one of those: ");
			$arrLanguages = $this->getLanguages();
			
			dmp($arrLanguages);
		}
    	
    }
    
    /**
     * get alphabet for widget output
     */
    public function getAlphabetForWidget($language) {

    	$arrAlphabet = $this->getAlphabet($language);
    	
    	$this->checkOutputError($arrAlphabet);
    	
    	return($arrAlphabet);
    }
    
    /**
     * get new way of output for the widget
     */
    public function getAlphabetForWidgetNew($language){
    	
    	$objFilters = new UniteCreatorFiltersProcess();
    	    	
    	$arrAlphabet = $this->getAlphabet($language);
    	$this->checkOutputError($arrAlphabet);
    	
    	$arrPostsCount = array();
		$includeCount = false;
    	
		if(GlobalsProviderUC::$isUnderAjax == true){
			
			$includeCount = true;
			
    		$arrPostsCount = $objFilters->getAlphabetPostsCount();
		}
    	
		//prepare output
		
		$arrOutput = array();
		
		foreach($arrAlphabet as $letter){
			
			$item = array("letter"=>$letter);
			
			if($includeCount == true)
				$item["count"] = UniteFunctionsUC::getVal($arrPostsCount, $letter,0);
			
			$arrOutput[] = $item;
		}
		
		return($arrOutput);
    }
    
    
    
    /**
     * get the alphabet languages
     */
    public function getLanguages(){
    	
    	return(array_keys($this->alphabets));
    }
    
    /**
     * print the languages
     */
    public function printLanguages(){
    	
    	$langs = $this->getLanguages();
    	dmp($langs);
    	
    }
    
} 
