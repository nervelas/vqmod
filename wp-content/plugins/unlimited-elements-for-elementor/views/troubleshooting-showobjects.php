<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved. 
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
if ( ! defined( 'ABSPATH' ) ) exit;

// Admin only: require manage_options (Administrator)
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'unlimited-elements-for-elementor' ) );
}
?>

<h1>Unlimited Elements - Show Objects</h1>


<?php 

/**
 * This page is meant for admin troubleshooting only.
 * Keep all logic self-contained in this view file.
 */

class UETroubleshootingShowObjectsUC{

	const QUERY_OBJECT_TYPE = "ue_object_type";
	const QUERY_IDENTIFIER = "ue_object_identifier";
	const QUERY_NONCE = "ue_object_nonce";

	/** @var string */
	private $objectType = "post";

	/** @var string */
	private $identifier = "";

	public function render(){

		$this->readRequest();
		$this->renderStyles();
		$this->renderForm();
		$this->renderResult();
	}

	private function readRequest(){

		$type = isset($_REQUEST[self::QUERY_OBJECT_TYPE]) ? sanitize_key(wp_unslash($_REQUEST[self::QUERY_OBJECT_TYPE])) : "";
		$identifier = isset($_REQUEST[self::QUERY_IDENTIFIER]) ? sanitize_text_field(wp_unslash($_REQUEST[self::QUERY_IDENTIFIER])) : "";

		if(in_array($type, array("post","term","user"), true))
			$this->objectType = $type;

		$this->identifier = trim($identifier);
	}

	private function renderStyles(){

		echo "<style>
			.ue-troubleshooting-wrap{max-width:1200px}
			.ue-troubleshooting-form{background:#fff;border:1px solid #ccd0d4;padding:16px;border-radius:6px;margin:16px 0}
			.ue-troubleshooting-row{display:flex;flex-direction:column;gap:12px;align-items:stretch}
			.ue-troubleshooting-field{max-width:720px}
			.ue-troubleshooting-field label{display:block;font-weight:600;margin-bottom:6px}
			.ue-troubleshooting-field input[type=text]{width:100%;max-width:720px}
			.ue-troubleshooting-field select{max-width:320px}
			.ue-troubleshooting-actions{display:flex;gap:8px;flex-wrap:wrap}
			.ue-dump{background:#0b1020;color:#e7e7e7;padding:12px;border-radius:6px;overflow:auto;max-height:520px}
			.ue-section{background:#fff;border:1px solid #ccd0d4;padding:16px;border-radius:6px;margin:12px 0}
			.ue-section h2{margin:0 0 10px 0}
			.ue-kv{width:100%;border-collapse:collapse}
			.ue-kv th,.ue-kv td{border:1px solid #e5e5e5;padding:8px;vertical-align:top;text-align:left}
			.ue-muted{color:#646970}
		</style>";
	}

	private function renderForm(){

		$nonce = wp_create_nonce("ue_troubleshooting_showobjects");
		$pageParam = isset($_GET["page"]) ? sanitize_key(wp_unslash($_GET["page"])) : "unlimitedelements";
		$clearUrl = add_query_arg(array(
			"page" => $pageParam,
			"view" => "troubleshooting-showobjects",
		), admin_url("admin.php"));

		echo "<div class='ue-troubleshooting-wrap'>";
		echo "<div class='ue-troubleshooting-form'>";
		echo "<form method='get'>";

		// Preserve current page/view.
		echo "<input type='hidden' name='page' value='" . esc_attr($pageParam) . "'>";
		echo "<input type='hidden' name='view' value='troubleshooting-showobjects'>";

		echo "<input type='hidden' name='".esc_attr(self::QUERY_NONCE)."' value='".esc_attr($nonce)."'>";

		echo "<div class='ue-troubleshooting-row'>";

		echo "<div class='ue-troubleshooting-field'>";
		echo "<label for='ue_object_type'>Object type</label>";
		echo "<select id='ue_object_type' name='".esc_attr(self::QUERY_OBJECT_TYPE)."'>";
		$this->renderOption("post", "Post");
		$this->renderOption("term", "Term");
		$this->renderOption("user", "User");
		echo "</select>";
		echo "</div>";

		echo "<div class='ue-troubleshooting-field'>";
		echo "<label for='ue_object_identifier'>ID or slug</label>";
		echo "<input id='ue_object_identifier' type='text' name='".esc_attr(self::QUERY_IDENTIFIER)."' value='".esc_attr($this->identifier)."' placeholder='e.g. 123 or hello-world'>";
		echo "<div class='ue-muted' style='margin-top:6px'>For terms/users, slug search is best-effort (taxonomy/user field auto-detection).</div>";
		echo "</div>";

		echo "<div class='ue-troubleshooting-actions'>";
		submit_button("Show " . ucfirst($this->objectType), "primary", "", false);
		if($this->identifier !== "")
			echo " <a class='button' href='".esc_url($clearUrl)."'>Clear</a>";
		echo "</div>";

		echo "</div>"; // row
		echo "</form>";
		echo "</div>"; // form box
	}

	private function renderOption($value, $label){
		$selected = selected($this->objectType, $value, false);
		echo "<option value='".esc_attr($value)."' {$selected}>".esc_html($label)."</option>";
	}

	private function renderResult(){

		if($this->identifier === ""){
			echo "<div class='ue-section'><div class='ue-muted'>Enter an ID or slug and click the button to inspect the object.</div></div>";
			echo "</div>"; // wrap
			return;
		}

		$nonce = isset($_REQUEST[self::QUERY_NONCE]) ? sanitize_text_field(wp_unslash($_REQUEST[self::QUERY_NONCE])) : "";
		if(empty($nonce) || wp_verify_nonce($nonce, "ue_troubleshooting_showobjects") !== 1){
			echo "<div class='ue-section'><strong>Security check failed.</strong> Please reload the page and try again.</div>";
			echo "</div>"; // wrap
			return;
		}

		switch($this->objectType){
			case "post":
				$this->renderPost($this->identifier);
			break;
			case "term":
				$this->renderTerm($this->identifier);
			break;
			case "user":
				$this->renderUser($this->identifier);
			break;
		}

		echo "</div>"; // wrap
	}

	private function isNumericID($value){
		return (is_string($value) || is_int($value)) && preg_match('/^\d+$/', (string)$value);
	}

	private function renderPost($identifier){

		$post = null;

		if($this->isNumericID($identifier)){
			$post = get_post((int)$identifier);
		}else{
			$slug = sanitize_title($identifier);
			$posts = get_posts(array(
				"name" => $slug,
				"post_type" => "any",
				"post_status" => "any",
				"numberposts" => 1,
				"suppress_filters" => false,
			));
			if(!empty($posts))
				$post = $posts[0];
		}

		if(empty($post)){
			echo "<div class='ue-section'><strong>Post not found.</strong> Identifier: <code>".esc_html($identifier)."</code></div>";
			return;
		}

		$postID = (int)$post->ID;

		$this->renderMainDataSection("Post", array(
			"ID" => $postID,
			"post_type" => $post->post_type,
			"post_status" => $post->post_status,
			"post_title" => $post->post_title,
			"post_name (slug)" => $post->post_name,
			"post_author" => $post->post_author,
			"post_date" => $post->post_date,
			"post_modified" => $post->post_modified,
			"permalink" => get_permalink($postID),
		));

		// Related terms
		$taxonomies = get_object_taxonomies($post->post_type, "names");
		$termsByTax = array();
		foreach($taxonomies as $tax){
			$terms = wp_get_post_terms($postID, $tax, array("fields" => "all"));
			if(is_wp_error($terms) || empty($terms))
				continue;
			$termsByTax[$tax] = $terms;
		}
		$this->renderObjectSection("Related terms (WP_Term objects)", $termsByTax);

		// Meta
		$meta = get_post_meta($postID);
		$this->renderObjectSection("Post meta (raw)", $meta);

		$this->renderObjectSection("Post object (raw)", $post);
	}

	private function renderTerm($identifier){

		$term = null;

		if($this->isNumericID($identifier)){
			$term = get_term((int)$identifier);
		}else{
			$slug = sanitize_title($identifier);
			$taxonomies = get_taxonomies(array(), "names");
			foreach($taxonomies as $tax){
				$found = get_term_by("slug", $slug, $tax);
				if(!empty($found) && !is_wp_error($found)){
					$term = $found;
					break;
				}
			}
		}

		if(empty($term) || is_wp_error($term)){
			echo "<div class='ue-section'><strong>Term not found.</strong> Identifier: <code>".esc_html($identifier)."</code></div>";
			return;
		}

		$termID = (int)$term->term_id;

		$this->renderMainDataSection("Term", array(
			"term_id" => $termID,
			"taxonomy" => $term->taxonomy,
			"name" => $term->name,
			"slug" => $term->slug,
			"description" => $term->description,
			"count" => $term->count,
			"link" => get_term_link($term),
		));

		$meta = get_term_meta($termID);
		$this->renderObjectSection("Term meta (raw)", $meta);
		$this->renderObjectSection("Term object (raw)", $term);
	}

	private function renderUser($identifier){

		$user = null;

		if($this->isNumericID($identifier)){
			$user = get_user_by("id", (int)$identifier);
		}else{
			$clean = trim($identifier);
			$user = get_user_by("login", $clean);
			if(empty($user))
				$user = get_user_by("slug", $clean);
			if(empty($user) && is_email($clean))
				$user = get_user_by("email", $clean);
		}

		if(empty($user)){
			echo "<div class='ue-section'><strong>User not found.</strong> Identifier: <code>".esc_html($identifier)."</code></div>";
			return;
		}

		$userID = (int)$user->ID;

		$this->renderMainDataSection("User", array(
			"ID" => $userID,
			"user_login" => $user->user_login,
			"user_nicename" => $user->user_nicename,
			"display_name" => $user->display_name,
			"user_email" => $user->user_email,
			"roles" => implode(", ", (array)$user->roles),
		));

		$meta = get_user_meta($userID);
		$this->renderObjectSection("User meta (raw)", $meta);
		$this->renderObjectSection("User object (raw)", $user);
	}

	private function renderMainDataSection($title, array $data){

		echo "<div class='ue-section'>";
		echo "<h2>".esc_html($title)." main data</h2>";
		echo "<table class='ue-kv'>";
		echo "<tbody>";
		foreach($data as $key => $value){
			echo "<tr>";
			echo "<th style='width:240px'>".esc_html((string)$key)."</th>";
			echo "<td>".esc_html(is_string($value) || is_numeric($value) ? (string)$value : wp_json_encode($value))."</td>";
			echo "</tr>";
		}
		echo "</tbody>";
		echo "</table>";
		echo "</div>";
	}

	private function renderObjectSection($title, $data){

		echo "<div class='ue-section'>";
		echo "<h2>".esc_html($title)."</h2>";
		echo "<pre class='ue-dump'>".esc_html($this->stringify($data))."</pre>";
		echo "</div>";
	}

	private function stringify($value){
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		return print_r($value, true);
	}
}

$page = new UETroubleshootingShowObjectsUC();
$page->render();
?>
