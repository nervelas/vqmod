<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved. 
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
if ( ! defined( 'ABSPATH' ) ) exit;

class UniteCreatorAddonType_Layout_Section extends UniteCreatorAddonType_Layout{
	
	
	/**
	 * init the addon type
	 */
	protected function initChild(){
		
		parent::initChild();
				
		$this->typeName = GlobalsUC::ADDON_TYPE_LAYOUT_SECTION;
		
		$this->isBasicType = false;
		$this->textSingle = "Section";	//can't translate here
		$this->textPlural = "Sections";
		$this->layoutTypeForCategory = $this->typeName;
		
		$this->textShowType = $this->textSingle;
		$this->displayType = self::DISPLAYTYPE_MANAGER;
		$this->allowImportFromCatalog = false;
		$this->allowDuplicateTitle = false;
		$this->isAutoScreenshot = true;
		$this->allowNoCategory = false;
		$this->allowWebCatalog = true;
		$this->showPageSettings = false;
		$this->defaultBlankTemplate = true;
		$this->exportPrefix = "section_";
		$this->titlePrefix = $this->textSingle." - ";
		$this->allowManagerWebCatalog = true;
		$this->catalogKey = $this->typeName;
		
		$this->paramsSettingsType = "screenshot";
		$this->paramSettingsTitle = "Preview Image Settings";
		$this->showParamsTopBarButton = true;
		$this->putScreenshotOnGridSave = true;
	}
	
	
}
