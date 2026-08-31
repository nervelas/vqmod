<?php

/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved.
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class UCRequestsLogView extends WP_List_Table{

	/**
	 * Gets a list of columns.
	 *
	 * @return array
	 */
	public function get_columns(){

		$columns = array(
			"url" => __("Request URL", "unlimited-elements-for-elementor"),
			"time" => __("Time", "unlimited-elements-for-elementor"),
			"user" => __("User", "unlimited-elements-for-elementor"),
		);

		return $columns;
	}

	/**
	 * Prepares the list of items for displaying.
	 *
	 * @return void
	 */
	public function prepare_items(){

		// prepare columns
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array($columns, $hidden, $sortable);

		// prepare items
		$this->items = $this->prepareData();
	}

	/**
	 * Message to be displayed when there are no items
	 *
	 * @return void
	 */
	public function no_items(){

		esc_attr_e("No requests found.", "unlimited-elements-for-elementor");
	}

	/**
	 * Displays the table.
	 *
	 * @return void
	 */
	public function display(){

		// Check if changelog is enabled
		$isChangelogEnabled = HelperProviderUC::isAddonChangelogEnabled();
		
		if($isChangelogEnabled === false){
			$this->displayHeader();
			$this->displayDisabledMessage();
			$this->displayFooter();
			return;
		}

		$this->prepare_items();

		$this->displayHeader();

		parent::display();

		$this->displayFooter();
	}

	/**
	 * Renders the url column.
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	protected function column_url($item){

		$url = $item["url"];
		
		// Mask API key in URL
		$url = preg_replace('/([?&]api_key=)([^&]+)/', '$1***', $url);
		
		return '<code style="font-size: 11px;">' . esc_html($url) . '</code>';
	}

	/**
	 * Renders the time column.
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	protected function column_time($item){

		return esc_html($item["time"]);
	}

	/**
	 * Renders the user column.
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	protected function column_user($item){

		return esc_html($item["user"]);
	}

	/**
	 * Generates the table navigation above or below the table.
	 *
	 * @param string $which
	 *
	 * return void
	 */
	protected function display_tablenav($which){
		// hide navigation
	}

	/**
	 * Prepares the list of items.
	 *
	 * @return array
	 */
	private function prepareData(){

		global $wpdb;
		
		$items = array();
		
		// Get requests from changelog table
		$changelogService = new UniteCreatorAddonChangelog();
		$table = $changelogService->getTable();
		
		$sql = $wpdb->prepare(
			"SELECT id, text, user_id, created_at
			FROM {$table}
			WHERE type = %s
			ORDER BY created_at DESC
			LIMIT 50",
			'request'
		);
		
		$results = $wpdb->get_results($sql, ARRAY_A);
		
		foreach($results as $row){
			
			$url = $row["text"];
			$userId = intval($row["user_id"]);
			$createdAt = $row["created_at"];
			
			// Format time
			$time = mysql2date("j F Y H:i:s", $createdAt);
			
			// Get user or use "guest"
			$user = "guest";
			if($userId > 0){
				$userObj = get_user_by("id", $userId);
				if($userObj){
					$user = $userObj->user_login;
				}
			}
			
			$items[] = array(
				"url" => $url,
				"time" => $time,
				"user" => $user,
			);
		}
		
		return $items;
	}

	/**
	 * Display the header.
	 *
	 * @return void
	 */
	private function displayHeader(){

		$headerTitle = __("Requests Log", "unlimited-elements-for-elementor");

		require HelperUC::getPathTemplate("header");
	}

	/**
	 * Display disabled message.
	 *
	 * @return void
	 */
	private function displayDisabledMessage(){
		?>
		<div class="notice notice-warning">
			<p><?php esc_html_e("The changelog feature is disabled in the general settings. Please enable it to view the requests log.", "unlimited-elements-for-elementor"); ?></p>
		</div>
		<?php
	}

	/**
	 * Display the footer.
	 *
	 * @return void
	 */
	private function displayFooter(){

		$urlChangelog = HelperUC::getViewUrl(GlobalsUnlimitedElements::VIEW_CHANGELOG);

		?>
		<div style="margin-top: 20px;">
			<a class="button" href="<?php echo esc_url($urlChangelog); ?>">
				<?php echo esc_html__("Back to Changelog", "unlimited-elements-for-elementor"); ?>
			</a>
		</div>
		
		<?php
	}

}

$requestLog = new UCRequestsLogView();
$requestLog->display();

