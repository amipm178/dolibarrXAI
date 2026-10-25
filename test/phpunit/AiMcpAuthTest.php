<?php
/* Copyright (C) 2026 dolibarrXAI contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

use PHPUnit\Framework\TestCase;

if (!function_exists('dol_string_nounprintableascii')) {
	function dol_string_nounprintableascii($value, $mode = 0)
	{
		return $value;
	}
}
require_once dirname(__FILE__).'/../../htdocs/ai/class/mcpauth.class.php';

/**
 * Tests extraction of MCP credentials from supported request sources.
 */
class AiMcpAuthTest extends TestCase
{
	/**
	 * Reject non-string credential sources and continue with a valid fallback.
	 *
	 * @return void
	 */
	public function testNonScalarHeaderDoesNotHideValidFallback()
	{
		$auth = new McpAuth(null);

		$credential = $auth->getCredential(
			array(
				'HTTP_DOLAPIKEY' => array('invalid'),
				'HTTP_AUTHORIZATION' => 'Bearer valid-bearer-key'
			),
			array()
		);
		$this->assertSame('valid-bearer-key', $credential);

		$credential = $auth->getCredential(
			array(
				'HTTP_AUTHORIZATION' => array('invalid'),
				'HTTP_X_API_KEY' => 'valid-api-key'
			),
			array()
		);
		$this->assertSame('valid-api-key', $credential);
	}

	/**
	 * Reject array-valued query parameters and accept a later scalar alias.
	 *
	 * @return void
	 */
	public function testNonScalarQueryCredentialDoesNotHideValidAlias()
	{
		$auth = new McpAuth(null);
		$credential = $auth->getCredential(
			array('REDIRECT_HTTP_AUTHORIZATION' => array('invalid')),
			array('DOLAPIKEY' => array('invalid'), 'api_key' => 'valid-query-key')
		);

		$this->assertSame('valid-query-key', $credential);
	}

	/**
	 * Malformed containers and credentials are treated as missing credentials.
	 *
	 * @return void
	 */
	public function testMalformedCredentialValuesReturnEmptyString()
	{
		$auth = new McpAuth(null);

		$this->assertSame('', $auth->getCredential(array('HTTP_DOLAPIKEY' => array('invalid')), array('key' => array('invalid'))));
		$this->assertSame('', $auth->getCredential('invalid', 'invalid'));
	}
}
