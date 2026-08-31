<?php

class UEGoogleAPICalendarEvent extends UEGoogleAPIModel{

	/**
	 * Get the identifier.
	 *
	 * @return int
	 */
	public function getId(){

		$id = $this->getAttribute("id");

		return $id;
	}

	/**
	 * Get the title.
	 *
	 * @return string
	 */
	public function getTitle(){

		$title = $this->getAttribute("summary");

		return empty($title) ? "" : $title;
	}

	/**
	 * Get the description.
	 *
	 * @param bool $asHtml
	 *
	 * @return string
	 */
	public function getDescription($asHtml = false){

		$description = $this->getAttribute("description");
		$description = UniteFunctionsUC::sanitizeHTMLRemoveJS($description);

		if($asHtml === true)
			$description = nl2br($description);

		return $description;
	}

	/**
	 * Get the location.
	 *
	 * @return string
	 */
	public function getLocation(){

		$location = $this->getAttribute("location");

		return empty($location) ? "" : $location;
	}

	/**
	 * Get the URL.
	 *
	 * @return string
	 */
	public function getUrl(){

		$url = $this->getAttribute("htmlLink");

		return empty($url) ? "" : $url;
	}

	/**
	 * Get the start date.
	 *
	 * @param string $format
	 *
	 * @return string
	 */
	public function getStartDate($format){
		
		$start = $this->getAttribute("start");
		
		$date = $this->getDate($start, $format);
		
		return $date;
	}

	/**
	 * Get the end date.
	 *
	 * @param string $format
	 *
	 * @return string
	 */
	public function getEndDate($format){

		$end = $this->getAttribute("end");
		$date = $this->getDate($end, $format);
		
		return $date;
	}

	/**
	 * Get the date.
	 *
	 * @param object $time
	 * @param string $format
	 *
	 * @return string
	 */
	private function getDate($time, $format){

		if(empty($time))
			return "";

		$date = UniteFunctionsUC::getVal($time, "date", null);
		$dateTime = UniteFunctionsUC::getVal($time, "dateTime", null);

		$date = $date ?: $dateTime;

		if(empty($date))
			return "";

		$timestamp = strtotime($date);

		if($timestamp === false)
			return "";

		return uelm_date($format, $timestamp);
	}

}
