<?php
/**
 * @package Unlimited Elements
 * @author UniteCMS Enhanced
 * @copyright Copyright (c) 2016-2024 UniteCMS
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */

if(!defined('ABSPATH')) exit;


class UniteCreatorBreadcrumbs {
	
	public static $showDebug = false;
	
    /**
     * Get page items for breadcrumb
     *
     * @param array $params Widget configuration parameters
     * @return array Breadcrumb items
     */
    public function getBreadcrumbItems($params) {
    	
    	//set debug
    	
		$isDebug = HelperUC::hasPermissionsFromQuery("ucbreadcrumbsdebug");
		if($isDebug == true)
			self::$showDebug = true;
		
		$debugWrapperStyle = 'background:#fff; border:2px dashed #999; padding:1em 1.25em; margin:1em 0; font-family:inherit;';
		if(self::$showDebug == true)
			echo '<div class="uc-breadcrumbs-debug" style="' . esc_attr($debugWrapperStyle) . '">';
			
    	if(self::$showDebug == true)
    		dmp("Breadcrumbs Debug");
    	
        $items = array();
        
        $home_text = $this->getParamValueByKey('home_text', $params);
        $show_home = $this->getParamValueByKey('show_home', $params);
        $show_category_breadcrumbs = $this->getParamValueByKey('show_category_breadcrumbs', $params, 'true');
        $categories_show_direction = $this->getParamValueByKey('categories_show_direction', $params, 'home');
        $max_category_depth = intval($this->getParamValueByKey('max_category_depth', $params, '2'));
        $show_blog_page = $this->getParamValueByKey('show_blog_page', $params, 'true');
        $search_page_text = $this->getParamValueByKey('search_page_text', $params, 'Results For:');

        if(self::$showDebug == true) {
            dmp("Options:");
            dmp(array(
                'show_home' => $show_home,
                'home_text' => $home_text,
                'show_category_breadcrumbs' => $show_category_breadcrumbs,
                'categories_show_direction' => $categories_show_direction,
                'max_category_depth' => $max_category_depth,
                'show_blog_page' => $show_blog_page,
                'search_page_text' => $search_page_text,
            ));
        }

        if($show_home === 'true') {
            $items[] = $this->getHomeItem($home_text);
        }



	    if(is_front_page()) {

        	if(self::$showDebug == true)
    			dmp("---- Front Page -----");

        } elseif(is_home()) {

        	if(self::$showDebug == true)
    			dmp("---- Home -----");

            $items = array_merge($items, $this->getBreadcrumbs_blogHome());
        } elseif(is_category() || is_archive()) {

        	if(self::$showDebug == true)
    			dmp("---- Category or Archive -----");

            $items = array_merge($items, $this->getBreadcrumbs_category($show_category_breadcrumbs, $categories_show_direction, $max_category_depth, $show_blog_page));
        } elseif(is_page()) {

	        if(self::$showDebug == true)
    			dmp("---- Page -----");

            $items = array_merge($items, $this->getBreadcrumbs_page());
        } elseif(is_single()) {

        	if(self::$showDebug == true)
    			dmp("---- Single -----");

            $items = array_merge($items, $this->getBreadcrumbs_single($show_category_breadcrumbs, $categories_show_direction, $max_category_depth, $show_blog_page));

        } elseif(is_post_type_archive()) {

        	if(self::$showDebug == true)
    			dmp("---- Post Type Archive -----");

            $items = array_merge($items, $this->getBreadcrumbs_postTypeArchive());

        } elseif(is_tag()) {

        	if(self::$showDebug == true)
    			dmp("---- Tag -----");

            $items = array_merge($items, $this->getBreadcrumbs_tag());

        } elseif(is_author()) {

        	if(self::$showDebug == true)
    			dmp("---- Author -----");

            $items = array_merge($items, $this->getBreadcrumbs_author());

        } elseif(is_search()) {

        	if(self::$showDebug == true)
    			dmp("---- Search -----");

            $items = array_merge($items, $this->getBreadcrumbs_search($search_page_text));

        } elseif(is_year() || is_month() || is_day()) {

        	if(self::$showDebug == true)
    			dmp("---- Date -----");

            $items = array_merge($items, $this->getBreadcrumbuelm_date());

        }


        if(self::$showDebug == true){

        	dmp("The Items:");
        	dmp($items);
        	echo '</div>';
        }


        
        return $items;
    }

    /**
     * Get home breadcrumb item
     *
     * @param string $home_text Text for home link
     * @return array Home breadcrumb item
     */
    private function getHomeItem($home_text) {
    	
        $frontPageID = get_option('page_on_front');
        $currentPageID = get_queried_object_id();
		
        if($frontPageID && $frontPageID == $currentPageID) {
            return array(
                'text' => html_entity_decode($home_text, ENT_QUOTES, 'UTF-8'),
                'url' => '',
                'type' => ''
            );
        } else {
            return array(
                'text' => html_entity_decode($home_text, ENT_QUOTES, 'UTF-8'),
                'url' => home_url('/'),
                'type' => ''
            );
        }
    }

    /**
     * Get breadcrumbs for blog home page
     *
     * @return array Breadcrumb items
     */
    private function getBreadcrumbs_blogHome(){
    	
        $items = array();

        $postsPageID = get_option('page_for_posts');
        if($postsPageID) {
            $page = get_post($postsPageID);
            $items[] = array(
                'text' => html_entity_decode($page->post_title, ENT_QUOTES, 'UTF-8'),
                'url' => '',
                'typ' => ''
            );
        }

        return $items;
    }

    /**
     * get taxonomy label of some category
     */
    private function getCategoryType($category){
    	
    	$taxonomyName = UniteFunctionsWPUC::getTermTaxonomyName($category);
    	        
        return($taxonomyName);
    }
    
    /**
     * Get breadcrumbs for category archives
     *
     * @param string $show_category_breadcrumbs Whether to show category breadcrumbs
     * @param string $categories_show_direction Direction to show categories from
     * @param int $max_category_depth Maximum depth of categories to show
     * @param string $show_blog_page Whether to show the blog/post type page in breadcrumbs
     * @return array Breadcrumb items
     */
    private function getBreadcrumbs_category($show_category_breadcrumbs, $categories_show_direction, $max_category_depth, $show_blog_page = 'true') {
        
    	$items = array();

        if($show_category_breadcrumbs !== 'true') {
            return $items;
        }

        $current_category = get_queried_object();

        if(!$current_category) {
            return $items;
        }

        if($current_category instanceof WP_Post_Type) {
            $label = !empty($current_category->labels->name) ? $current_category->labels->name : $current_category->name;
            $url = ($current_category->has_archive) ? get_post_type_archive_link($current_category->name) : '';
            $items[] = array(
                'text' => html_entity_decode($label, ENT_QUOTES, 'UTF-8'),
                'url' => $url,
                'type' => ''
            );
            return $items;
        }

	    $taxonomy = $current_category->taxonomy;

        if($show_blog_page === 'true') {
            if(self::$showDebug == true)
                dmp("add blog/post type option (category)");
            if($taxonomy === 'category') {
                $items = $this->addBlogPage($items);
            } else {
                $objTax = get_taxonomy($taxonomy);
                if(!empty($objTax->object_type) && is_array($objTax->object_type)) {
                    $postType = $objTax->object_type[0];
                    $objPostType = get_post_type_object($postType);
                    $items = $this->addPostTypeItem($items, $objPostType);
                }
            }
        }

        $ancestors = get_ancestors($current_category->term_id,  $taxonomy);
        $ancestors = array_reverse($ancestors);
        $ancestors_to_show = $ancestors;

        if(count($ancestors) > $max_category_depth) {
            if($categories_show_direction === 'home') {
                $ancestors_to_show = array_slice($ancestors, 0, $max_category_depth);
            } else {
                $ancestors_to_show = array_slice($ancestors, -$max_category_depth);
            }
        }

        foreach($ancestors_to_show as $ancestor_id) {
        	
            $ancestor_obj = get_term($ancestor_id,  $taxonomy);
            if(!is_wp_error($ancestor_obj)) {
            	            	
                $items[] = array(
                    'text' => html_entity_decode($ancestor_obj->name, ENT_QUOTES, 'UTF-8'),
                    'url' => get_category_link($ancestor_id),
                	'type' => $this->getCategoryType($ancestor_obj)
                );
            }
        }
        
        $items[] = array(
            'text' => html_entity_decode($current_category->name, ENT_QUOTES, 'UTF-8'),
            'url' => '',
        	'type' => $this->getCategoryType($current_category)
        );



        return $items;
    }



	/**
     * Get breadcrumbs for a page
     *
     * @return array Breadcrumb items
     */
    private function getBreadcrumbs_page() {
        
    	$items = array();
        $currentPageID = get_queried_object_id();
        $ancestors = get_post_ancestors($currentPageID);
        $ancestors = array_reverse($ancestors);
		
        foreach($ancestors as $ancestor_id) {
            $items[] = array(
                'text' => html_entity_decode(get_the_title($ancestor_id), ENT_QUOTES, 'UTF-8'),
                'url' => get_permalink($ancestor_id),
            	'type' => UniteFunctionsWPUC::getPostTypeTitleByPost($ancestor_id)
            );
        }

        $items[] = array(
            'text' => html_entity_decode(get_the_title(), ENT_QUOTES, 'UTF-8'),
            'url' => '',
            'type' => UniteFunctionsWPUC::getPostTypeTitle()
        );
		
        return $items;
    }
	
    /**
     * add blog page
     */
    private function addBlogPage($items){
    	    	
      $postsPageID = get_option('page_for_posts');

      if(empty($postsPageID))
      	return($items);
      
      $posts_page = get_post($postsPageID);

      if(empty($posts_page))
      	 return($items);
     	
      $items[] = array(
        'text' => html_entity_decode($posts_page->post_title, ENT_QUOTES, 'UTF-8'),
		'url' => get_permalink($postsPageID),
		'type' => UniteFunctionsWPUC::getPostTypeTitle($posts_page->post_type)
	  );
	  
      return($items);
    }
    
    /**
     * add post type item
     */
    private function addPostTypeItem($items, $objPostType){
    	
    	if(empty($objPostType))
    		return($items);
    	
    	if($objPostType->has_archive == false)
    		return($items);
	    
	    $items[] = array(
	    	'text' => html_entity_decode($objPostType->labels->name, ENT_QUOTES, 'UTF-8'),
	    	'url' => get_post_type_archive_link($objPostType->name),
	    	'type' => ""
	    );
	    
	    return($items);
    }
    
    
    /**
     * Get breadcrumbs for a single post
     *
     * @param string $show_category_breadcrumbs Whether to show category breadcrumbs
     * @param string $categories_show_direction Direction to show categories from
     * @param int $max_category_depth Maximum depth of categories to show
     * @param string $show_blog_page Whether to show the blog page in breadcrumbs
     * @return array Breadcrumb items
     */
    private function getBreadcrumbs_single($show_category_breadcrumbs, $categories_show_direction, $max_category_depth, $show_blog_page) {

        
    	$items = array();
					
        if($show_blog_page === 'true'){
			  
        	if(self::$showDebug == true)
		       	 dmp("add blog option");
        	
        	$postType = get_post_type();
			$objPostType = get_post_type_object($postType);
        				 
			$isBuiltIn = ($objPostType && $objPostType->_builtin);
        				
			if($isBuiltIn == true)
        		$items = $this->addBlogPage($items);
        	else 
        		$items = $this->addPostTypeItem($items, $objPostType);
        }
        	
        if($show_category_breadcrumbs === 'true') {
        	
            $categories = get_the_category();

            if(!empty($categories)) {
            	
                $category = $this->getMostSpecificCategory($categories);
				                
                if($category) {
                    $ancestors = get_ancestors($category->term_id, 'category');
                    $ancestors = array_reverse($ancestors);
                    $all_categories = array_merge($ancestors, array($category->term_id));

                    $categories_to_show = array();

                    if(count($all_categories) > $max_category_depth) {
                        if($categories_show_direction === 'home') {
                            $categories_to_show = array_slice($all_categories, 0, $max_category_depth);
                        } else {
                            $categories_to_show = array_slice($all_categories, -$max_category_depth);
                        }
                    } else {
                        $categories_to_show = $all_categories;
                    }

                    foreach($categories_to_show as $cat_id) {
                        $cat_obj = get_term($cat_id, 'category');
                        if(!is_wp_error($cat_obj)) {
                            
                        	$is_current = ($cat_id === $category->term_id);
                                                    	
                            $items[] = array(
                                'text' => html_entity_decode($cat_obj->name, ENT_QUOTES, 'UTF-8'),
                                'url' => $is_current ? get_category_link($cat_id) : get_category_link($cat_id),
                            	'type' => $this->getCategoryType($category)
                            );
                        }
                    }
                }
            }
        }
		
        
        $items[] = array(
            'text' => html_entity_decode(get_the_title(), ENT_QUOTES, 'UTF-8'),
            'url' => '',
			'type' => UniteFunctionsWPUC::getPostTypeTitle()
        );

        return $items;
    }

    /**
     * Get breadcrumbs for post type archives
     *
     * @return array Breadcrumb items
     */
    private function getBreadcrumbs_postTypeArchive() {
        
    	$items = array();

        $post_type = get_queried_object();
        if($post_type && $post_type instanceof WP_Post_Type) {
            $label = !empty($post_type->labels->name) ? $post_type->labels->name : $post_type->name;
            $url = ($post_type->has_archive) ? get_post_type_archive_link($post_type->name) : '';
            $items[] = array(
                'text' => html_entity_decode($label, ENT_QUOTES, 'UTF-8'),
                'url' => $url,
                'type' => ''
            );
        }

        return $items;
    }

    /**
     * Get breadcrumbs for tag archives
     *
     * @return array Breadcrumb items
     */
    private function getBreadcrumbs_tag() {
    	
        $items = array();

        $items[] = array(
            'text' => html_entity_decode(single_tag_title('', false), ENT_QUOTES, 'UTF-8'),
            'url' => '',
        	'type' => ''
        );
		
        return $items;
    }

    /**
     * Get breadcrumbs for author archives
     *
     * @return array Breadcrumb items
     */
    private function getBreadcrumbs_author() {
        
    	$items = array();

        $items[] = array(
            'text' => html_entity_decode(get_the_author(), ENT_QUOTES, 'UTF-8'),
            'url' => '',
        	'type' => ''
        );

        return $items;
    }

    /**
     * Get breadcrumbs for search results
     *
     * @param string $search_page_text Text to display for search results
     * @return array Breadcrumb items
     */
    private function getBreadcrumbs_search($search_page_text) {
        $items = array();

        $items[] = array(
            'text' => html_entity_decode($search_page_text . ' "' . get_search_query() . '"', ENT_QUOTES, 'UTF-8'),
            'url' => '',
        	'type' => ''
       	);

        return $items;
    }

    /**
     * Get breadcrumbs for date archives
     *
     * @return array Breadcrumb items
     */
    private function getBreadcrumbuelm_date() {
        $items = array();

        if(is_year()) {
            $items[] = array(
                'text' => html_entity_decode(get_the_date('Y'), ENT_QUOTES, 'UTF-8'),
                'url' => '',
                'type' => ''
            );
        } elseif(is_month()) {
            $items[] = array(
                'text' => html_entity_decode(get_the_date('F Y'), ENT_QUOTES, 'UTF-8'),
                'url' => '',
                'type' => ''
            );
        } elseif(is_day()) {
            $items[] = array(
                'text' => html_entity_decode(get_the_date('F j, Y'), ENT_QUOTES, 'UTF-8'),
                'url' => '',
                'type' => ''
            );
        }

        return $items;
    }

    /**
     * Get the most specific (deepest) category for a post
     *
     * @param array $categories List of categories
     * @return WP_Term|null Most specific category
     */
    private function getMostSpecificCategory($categories) {
        if(empty($categories)) return null;

        $most_specific = null;
        $max_depth = 0;

        foreach($categories as $category) {
            $current_ancestors = get_ancestors($category->term_id, 'category');
            $current_depth = count($current_ancestors) + 1;

            if($current_depth > $max_depth) {
                $max_depth = $current_depth;
                $most_specific = $category;
            }

            elseif($current_depth == $max_depth) {
                $current_parent_depth = $current_ancestors ? count(get_ancestors($current_ancestors[0], 'category')) : 0;
                $most_specific_parent_depth = $most_specific ? count(get_ancestors($most_specific->parent, 'category')) : 0;

                if($current_parent_depth > $most_specific_parent_depth) {
                    $most_specific = $category;
                }
            }
        }

        return $most_specific;
    }

    /**
     * Get Widget param value by key with default value support
     *
     * @param string $key Parameter key
     * @param array $data Parameter data
     * @param mixed $default Default value
     * @return mixed Parameter value
     */
    private function getParamValueByKey($key, $data, $default = '') {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        foreach($data as $item) {
            if(is_array($item) && isset($item['name']) && $item['name'] == $key) {
                return $item['value'] ?? $default;
            }
        }

        return $default;
    }
}
