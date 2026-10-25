<?php
/* Copyright (C) 2026 dolibarrXAI contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

use PHPUnit\Framework\TestCase;

require_once dirname(__FILE__).'/../../htdocs/ai/class/mcplogsanitizer.class.php';

/**
 * Tests that MCP audit metadata omits tool arguments and response contents.
 */
class AiMcpLogSanitizerTest extends TestCase
{
	/**
	 * Request logs retain field names but never retain argument values.
	 *
	 * @return void
	 */
	public function testSanitizeRequestRedactsArgumentValues()
	{
		$request = array(
			'jsonrpc' => '2.0',
			'id' => 12,
			'method' => 'tools/call',
			'params' => array(
				'name' => 'create_invoice',
				'arguments' => array('customer' => 'Acme secret', 'amount' => '9876.54')
			)
		);

		$encoded = json_encode(McpLogSanitizer::sanitizeRequest($request));

		$this->assertFalse(strpos($encoded, 'Acme secret') !== false);
		$this->assertFalse(strpos($encoded, '9876.54') !== false);
		$this->assertTrue(strpos($encoded, 'customer') !== false);
		$this->assertTrue(strpos($encoded, 'amount') !== false);
	}

	/**
	 * Response logs retain status metadata but omit business output and errors.
	 *
	 * @return void
	 */
	public function testSanitizeResponseRedactsContentAndErrorDetails()
	{
		$response = array(
			'jsonrpc' => '2.0',
			'id' => 12,
			'result' => array(
				'isError' => true,
				'content' => array(array('type' => 'text', 'text' => 'Customer secret'))
			)
		);

		$encoded = json_encode(McpLogSanitizer::sanitizeResponse($response));

		$this->assertFalse(strpos($encoded, 'Customer secret') !== false);
		$this->assertTrue(strpos($encoded, '"isError":true') !== false);
		$this->assertTrue(strpos($encoded, '"content_count":1') !== false);

		$error = array('error' => array('code' => -32603, 'message' => 'Private internal detail'));
		$encodedError = json_encode(McpLogSanitizer::sanitizeResponse($error));
		$this->assertFalse(strpos($encodedError, 'Private internal detail') !== false);
		$this->assertTrue(strpos($encodedError, '-32603') !== false);
	}

	/**
	 * Malformed or non-object arguments do not break log sanitization.
	 *
	 * @return void
	 */
	public function testArgumentKeysHandlesInvalidInput()
	{
		$this->assertSame(array(), McpLogSanitizer::argumentKeys('{invalid json'));
		$this->assertSame(array(), McpLogSanitizer::argumentKeys('private scalar'));
	}
}
