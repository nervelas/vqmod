<?php

class UEGoogleAPIPlace extends UEGoogleAPIModel{

	/**
	 * Transform Places API (New) response into internal place model.
	 *
	 * @param array $attributes
	 *
	 * @return UEGoogleAPIPlace
	 */
	public static function transformNew($attributes){

		$data = array();

		$data["place_info"] = array(
			"title" => self::getLocalizedTextValue($attributes, "displayName"),
			"address" => UniteFunctionsUC::getVal($attributes, "formattedAddress"),
			"rating" => UniteFunctionsUC::getVal($attributes, "rating"),
			"reviews" => UniteFunctionsUC::getVal($attributes, "userRatingCount"),
		);

		$reviews = UniteFunctionsUC::getVal($attributes, "reviews", array());
		$data["reviews"] = UEGoogleAPIPlaceReview::transformAllNew($reviews);

		return self::transform($data);
	}

	/**
	 * Get plain text from a Places API (New) LocalizedText field.
	 *
	 * @param array $attributes
	 * @param string $key
	 *
	 * @return string
	 */
	private static function getLocalizedTextValue($attributes, $key){

		$value = UniteFunctionsUC::getVal($attributes, $key);

		if(is_array($value))
			return UniteFunctionsUC::getVal($value, "text", "");

		if($value === null)
			return "";

		return (string)$value;
	}

	/**
	 * Get the reviews.
	 *
	 * @return UEGoogleAPIPlaceReview[]
	 */
	public function getReviews(){
		
		$reviews = $this->getAttribute("reviews", array());
		$reviews = UEGoogleAPIPlaceReview::transformAll($reviews);
		
		return $reviews;
	}
	
	/**
	 * get place info if available
	 */
	public function getPlaceInfo(){
		
		$arrInfo = $this->getAttribute("place_info");
		
		return($arrInfo);
	}
	
	/**
	 * get place info if available
	 */
	public function getSearchParams(){
		
		$arrParams = $this->getAttribute("search_parameters");
		
		return($arrParams);
	}
	
	/**
	 * get search meta data
	 */
	public function getMetaData(){
		
		$arrParams = $this->getAttribute("search_metadata");
		
		return($arrParams);
	}
	
	
	
}
