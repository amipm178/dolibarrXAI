<?php
/* Copyright (C) 2026 dolibarrXAI contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Helpers to normalize generated website content before returning it to the editor.
 */
class AiContentNormalizer
{
	/**
	 * Remove document wrappers and code fences from generated page HTML and make the
	 * returned fragment available to Dolibarr's inline website editor.
	 *
	 * @param string $content Generated HTML or an HTML fragment
	 * @return string Normalized page fragment
	 */
	public static function normalizeWebpage($content)
	{
		$content = trim((string) $content);

		if (preg_match('/^\s*```(?:html)?\s*(.*?)\s*```\s*$/is', $content, $matches)) {
			$content = trim($matches[1]);
		}

		if (preg_match('/<body\b[^>]*>(.*?)<\/body\s*>/is', $content, $matches)) {
			$content = trim($matches[1]);
		} else {
			$content = preg_replace('/<!doctype\b[^>]*>/i', '', $content);
			$content = preg_replace('/<head\b[^>]*>.*?<\/head\s*>/is', '', $content);
			$content = preg_replace('/<\/?(?:html|body)\b[^>]*>/i', '', $content);
			$content = trim($content);
		}

		if ($content === '') {
			return '';
		}

		// Existing editable sections have their own IDs and should not be nested in
		// another editable area.
		if (preg_match('/<[a-z][a-z0-9]*\b(?=[^>]*\sid=["\'][^"\']+["\'])(?=[^>]*\scontenteditable=["\']true["\'])[^>]*>/i', $content)) {
			return $content;
		}

		$usedIds = array();
		if (preg_match_all('/\sid=["\']([^"\']+)["\']/i', $content, $matches)) {
			$usedIds = $matches[1];
		}

		$id = 'ai-generated-content';
		$suffix = 1;
		while (in_array($id, $usedIds, true)) {
			$id = 'ai-generated-content-'.$suffix;
			$suffix++;
		}

		return '<section id="'.$id.'" contenteditable="true">'.$content.'</section>';
	}
}
