<?php

/**
 * Class WPML_Elementor_Price_List
 */
class UNITE_CREATOR_WPML_Translation_Module extends WPML_Elementor_Module_With_Items{
//class UNITE_CREATOR_WPML_Translation_Module{
	
	private $ucIsInited = false;
	private $ucData = array();
	private $widgetName;
	
	/**
	 * init the class
	 */
	private function ucInit(){
		
		if($this->ucIsInited == true)
			return(false);
		
		$this->ucIsInited = true;
			
		$class = get_class($this);
		
		$widgetName = str_replace("UE_WPML_INTEGRATION__", "", $class);
		
		if(empty($widgetName))
			return(false);
		
		$this->widgetName = $widgetName;
			
		$arrData = UniteFunctionsUC::getVal(UniteCreatorWpmlIntegrate::$arrWidgetItemsData, $widgetName);
		
		if(empty($arrData))
			return(false);
		
		$arrData = UniteFunctionsUC::arrayToAssoc($arrData,"field");
		
		$this->ucData = $arrData;
	}
	
	
	/**
	 * @return string
	 */
	public function get_items_field() {
		
		$this->ucInit();
		
		if($this->widgetName == "ucaddon_ue_sort_filter" || $this->widgetName == "ue_sort_filter_elementor")
			return("fields_fields");
		
		return 'uc_items';
	}
	

	/**
	 * @return array
	 */
	public function get_fields() {
		
		$this->ucInit();
		
		if(empty($this->ucData))
			return(array());
		
		$arrFields = array();
		foreach($this->ucData as $field => $arrField){
			$arrFields[] = $field;
		}
		
		return $arrFields;
	}

	/**
	 * @param string $field
	 *
	 * @return string
	 */
	protected function get_title( $field ) {
		
		$this->ucInit();
		
		$arrField = UniteFunctionsUC::getVal($this->ucData, $field);

		$title = UniteFunctionsUC::getVal($arrField, "type");
		
		return($title);		
	}
	

	/**
	 * @param string $field
	 *
	 * @return string
	 */
	protected function get_editor_type( $field ) {
		
		$this->ucInit();
		
		$arrField = UniteFunctionsUC::getVal($this->ucData, $field);

		$type = UniteFunctionsUC::getVal($arrField, "editor_type");
		
		return($type);		
	}
	
	/**
	 * print test settings
	 */
	public function printTest(){
		
		$fields = $this->get_fields();
		
		foreach($fields as $field){
			
			$title = $this->get_title($field);
			$type = $this->get_editor_type($field);
			
			dmp("---------------");
			dmp($field);
			dmp($title);
			dmp($type);
		}
		
		if(empty($fields))
			dmp("no fields found");
		
	}
	
}