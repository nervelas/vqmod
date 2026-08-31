<?php

/**
 * @link https://developers.google.com/youtube/v3/docs
 */
class UEGoogleAPIYouTubeService extends UEGoogleAPIClient{

	/**
	 * Get the playlist items.
	 *
	 * @param string $playlistId
	 * @param array $params
	 *
	 * @return UEGoogleAPIPlaylistItem[]
	 */
	public function getPlaylistItems($playlistId, $params = array()){

		$playlistId = $this->normalizePlaylistId($playlistId);

		$params["playlistId"] = $playlistId;
		$params["part"] = "snippet,contentDetails";

		$response = $this->get("/playlistItems", $params);
		$items = UniteFunctionsUC::getVal($response, "items", array());

		if(empty($items) === true)
			return array();

		return UEGoogleAPIPlaylistItem::transformAll($items);
	}

	/**
	 * Normalize playlist id (accept full YouTube URL or raw id).
	 *
	 * @param string $playlistId
	 *
	 * @return string
	 */
	private function normalizePlaylistId($playlistId){

		$playlistId = trim($playlistId);

		if(empty($playlistId) === true)
			return $playlistId;

		// https://www.youtube.com/playlist?list=PLxxx
		// https://www.youtube.com/watch?v=xxx&list=RDxxx
		if(preg_match('/[?&]list=([^&]+)/', $playlistId, $matches))
			return urldecode($matches[1]);

		return $playlistId;
	}

	/**
	 * Get the base URL for the API.
	 *
	 * @return string
	 */
	protected function getBaseUrl(){
		
		return "https://www.googleapis.com/youtube/v3";
	}

}
