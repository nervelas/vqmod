<?php

/**
 * @package Unlimited Elements
 * @author UniteCMS http://unitecms.net
 * @copyright Copyright (c) 2016 UniteCMS
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class UCAdminNoticeSheetsPilot extends UCAdminNoticeAbstract{

	/**
	 * get the notice identifier
	 */
	public function getId(){

		return 'sheetspilot1';
	}

	/**
	 * get the notice html
	 */
	public function getHtml(){

		$heading = __('New! Automate Your WordPress Workflow With AI', 'unlimited-elements-for-elementor');
		$content = __('Edit your WordPress content in a live spreadsheet, save AI prompts, and fully automate your entire workflow.<br />⚡ Edit thousands of posts in one live spreadsheet<br />🤖 Save reusable AI prompts and generate content in seconds<br />🔄 Automate repetitive WordPress tasks', 'unlimited-elements-for-elementor');

		$installText = __('Install SheetsPilot Now', 'unlimited-elements-for-elementor');
		$installUrl = UniteFunctionsWPUC::getInstallPluginLink('sheetspilot');
		$installUrl = UniteFunctionsUC::addUrlParams($installUrl, array('uc_dismiss_notice' => $this->getId()));

		$id = $this->getId();

		$logoUrl = GlobalsUC::$urlPluginImages . 'banners/logo-sheetspilot.jpg';

		$builder = new UCAdminNoticeBuilder($id);
		$builder = $this->initBuilder($builder);

		$builder->dismissible();
		$builder->color(UCAdminNoticeBuilder::COLOR_INFO);
		$builder->withLogo($logoUrl);
		$builder->withHeading($heading);
		$builder->withContent($content);
		$builder->withLinkAction($installText, $installUrl);

		$html = $builder->build();

		return $html;
	}

	/**
	 * initialize the notice
	 */
	protected function init(){

		$this->setDuration(168); // 7 days in hours
	}

	/**
	 * check if the notice condition is allowed
	 */
	protected function isConditionAllowed(){

		if($this->isSheetsPilotInstalled() === true)
			return false;

		return true;
	}

	/**
	 * check if the SheetsPilot plugin is installed
	 */
	private function isSheetsPilotInstalled(){

		if(defined( 'SHEETSPILOT_INC' ))
			return true;

		return false;
	}

}
