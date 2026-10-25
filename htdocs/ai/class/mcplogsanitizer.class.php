<?php
/* Copyright (C) 2026 amipm178/dolibarrXAI contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Helpers for writing useful MCP audit metadata without persisting business payloads.
 */
class McpLogSanitizer
{
	/**
	 * Return argument names only. Values are deliberately never copied to logs.
	 *
	 * @param mixed $arguments Tool arguments, as an array or JSON string.
	 * @return string[]
	 */
	public static function argumentKeys($arguments)
	{
		if (is_string($arguments)) {
			$decoded = json_decode($arguments, true);
			if (!is_array($decoded)) {
				return array();
			}
			$arguments = $decoded;
		}
		if (!is_array($arguments)) {
			return array();
		}

		$keys = array_slice(array_keys($arguments), 0, 50);
		return array_map(function ($key) { return preg_replace('/[\\x00-\\x1F\\x7F]/', '', substr((string) $key, 0, 100)); }, $keys);
	}

	/**
	 * Keep only the MCP request metadata needed for auditing.
	 *
	 * @param array<string,mixed> $request MCP request.
	 * @return array<string,mixed>
	 */
	public static function sanitizeRequest(array $request)
	{
		$safe = array();
		foreach (array('jsonrpc', 'id', 'method') as $key) {
			if (!array_key_exists($key, $request)) {
				continue;
			}
			$value = $request[$key];
			if ($key === 'id' && (is_int($value) || is_float($value) || $value === null)) {
				$safe[$key] = $value;
			} elseif (is_scalar($value)) {
				$safe[$key] = substr((string) $value, 0, 100);
			} else {
				$safe[$key] = '[invalid]';
			}
		}

		$params = isset($request['params']) && is_array($request['params']) ? $request['params'] : array();
		if (!empty($params)) {
			$safe['params'] = array();
			if (isset($params['name']) && is_scalar($params['name'])) {
				$safe['params']['name'] = substr((string) $params['name'], 0, 255);
			}
			if (array_key_exists('arguments', $params)) {
				$safe['params']['arguments'] = array(
					'redacted' => true,
					'keys' => self::argumentKeys($params['arguments'])
				);
			}
		}

		return $safe;
	}

	/**
	 * Keep response status metadata, never result content or raw error messages.
	 *
	 * @param mixed $response MCP response.
	 * @return array<string,mixed>
	 */
	public static function sanitizeResponse($response)
	{
		if (!is_array($response)) {
			return array('redacted' => true, 'response_type' => gettype($response));
		}

		$safe = array();
		foreach (array('jsonrpc', 'id') as $key) {
			if (!array_key_exists($key, $response)) {
				continue;
			}
			$value = $response[$key];
			if ($key === 'id' && (is_int($value) || is_float($value) || $value === null)) {
				$safe[$key] = $value;
			} elseif (is_scalar($value)) {
				$safe[$key] = substr((string) $value, 0, 100);
			} else {
				$safe[$key] = '[invalid]';
			}
		}

		if (isset($response['error']) && is_array($response['error'])) {
			$error = array('message' => 'Error details redacted');
			if (isset($response['error']['code']) && is_numeric($response['error']['code'])) {
				$error['code'] = (int) $response['error']['code'];
			}
			$safe['error'] = $error;
		}

		if (isset($response['result']) && is_array($response['result'])) {
			$result = array();
			if (array_key_exists('isError', $response['result'])) {
				$result['isError'] = (bool) $response['result']['isError'];
			}
			if (isset($response['result']['content']) && is_array($response['result']['content'])) {
				$result['content_count'] = count($response['result']['content']);
			}
			if (isset($response['result']['resultType']) && is_scalar($response['result']['resultType'])) {
				$result['resultType'] = (string) $response['result']['resultType'];
			}
			$safe['result'] = $result;
		}

		return $safe;
	}
}
