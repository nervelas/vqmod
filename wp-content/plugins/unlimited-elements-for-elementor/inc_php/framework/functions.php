<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved. 
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
if ( ! defined( 'ABSPATH' ) ) exit;

//---------------------------------------------------------------------------------------------------------------------	
	
	if(!function_exists("dmp")){
		function dmp($str){ 
						
			echo "<div align='left' style='direction:ltr;color:black;'>";
			echo "<pre>";
			print_r($str);
			echo "</pre>";
			echo "</div>";
		}
	}
	
	if(!function_exists("dmpHtml")){
		function dmpHtml($str){
			dmp(htmlspecialchars($str));
		}
	}
	 
	if(!function_exists("dmpGet")){
		
		function dmpGet($str){
			
			$html = "";
			
			$html .= "<div align='left' style='direction:ltr;color:black;'>";
			
			$html .= "<pre>";
			$html .= print_r($str, true);
			$html .= "</pre>";
			$html .= "</div>";
			
			return($html);
		}
		
	}
	
	if(!function_exists("uelm_echo")){
		function uelm_echo($str) {
			if( is_array($str) || is_object($str) ) {
				return;
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo($str);
		}
	}

	if (!function_exists("uelm_date")) {
		
		function uelm_date($format, $time = null) {
			
			if(empty($time))
				$time = time();
			
			$timezone = new DateTimeZone(date_default_timezone_get());

			$datetime = new DateTime('@' . $time);
			$datetime->setTimezone($timezone);			
			$formatted = $datetime->format($format);
			
			return $formatted;
		}
		
	}




	
	
	 
	
	
