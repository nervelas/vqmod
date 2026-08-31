<?php

class UEGoogleAPISheetValues extends UEGoogleAPIModel{

	/**
	 * Get the values.
	 *
	 * @return array
	 */
	public function getValues(){

		$values = $this->getAttribute("values", array());

		return $values;
	}


	/**
	 * Get the values with links.
	 *
	 * @return array
	 */
	public function getValuesWithLinksAndAttributes() {
		$data = $this->getAttribute("sheets", array());
		$results = array();

		foreach ($data as $sheet) {
			foreach ($sheet["data"] as $block) {
				foreach ($block["rowData"] as $row) {
					$results[] = $this->renderRowsAndCells($row["values"]);
				}
			}
		}

		return $results;
	}


	/**
	 * render rows and cell for table.
	 *
	 * @return string
	 */
	private function renderRowsAndCells($cells) {
		$rowValues = array();

		foreach ($cells as $cell) {
			$rawText = $cell["formattedValue"] ?? "";
			$link    = $cell["hyperlink"] ?? null;
			$format  = $cell["effectiveFormat"]["textFormat"] ?? array();
			$styles  = array();

			if(!empty($format["bold"])) $styles[] = "font-weight:bold";
			if(!empty($format["italic"])) $styles[] = "font-style:italic";
			if(!empty($format["underline"])) $styles[] = "text-decoration:underline";
			if(!empty($format["strikethrough"])) $styles[] = "text-decoration:line-through";

			$rgb = $format["foregroundColor"] ?? $format["foregroundColorStyle"]["rgbColor"] ?? null;
			if(is_array($rgb) && (isset($rgb["red"]) || isset($rgb["green"]) || isset($rgb["blue"]))) {
				$r        = round(($rgb["red"] ?? 0) * 255);
				$g        = round(($rgb["green"] ?? 0) * 255);
				$b        = round(($rgb["blue"] ?? 0) * 255);
				$hex      = sprintf("#%02x%02x%02x", $r, $g, $b);
				$styles[] = "color:$hex";
			}

			// Allow HTML from Sheets while stripping potentially unsafe JS.
			$safeText = UniteFunctionsUC::sanitizeHTMLRemoveJS($rawText);
			if(!empty($styles)) {
				$styleAttr = htmlspecialchars(implode(";", $styles));
				$safeText  = "<span style=\"$styleAttr\">$safeText</span>";
			}

			if($link)
				$safeText = '<a href="' . htmlspecialchars($link) . '" target="_blank">' . $safeText . '</a>';

			$rowValues[] = $safeText;
		}

		return $rowValues;
	}

}
