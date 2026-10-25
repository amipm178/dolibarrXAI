<?php
/* Copyright (C) 2026 dolibarrXAI contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

use PHPUnit\Framework\TestCase;

if (!defined('DOL_DOCUMENT_ROOT')) {
	define('DOL_DOCUMENT_ROOT', dirname(__FILE__).'/../../htdocs');
}
require_once DOL_DOCUMENT_ROOT.'/ai/class/mcp_protocol.class.php';

/**
 * Tests that malformed MCP inputs return JSON-RPC errors instead of PHP errors.
 */
class AiMcpRequestValidationTest extends TestCase
{
	/**
	 * Construct the protocol router without loading tools for validation-only cases.
	 *
	 * @return MCPServer
	 */
	private function getUninitializedServer()
	{
		$reflection = new ReflectionClass('MCPServer');
		return $reflection->newInstanceWithoutConstructor();
	}

	/**
	 * Scalar params and invalid method fields are rejected before dispatch.
	 *
	 * @return void
	 */
	public function testMalformedParamsReturnInvalidParams()
	{
		$requests = array(
			array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => 'invalid'),
			array('jsonrpc' => '2.0', 'id' => 2, 'method' => 'initialize', 'params' => array('_meta' => 'invalid')),
			array('jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => array('name' => array('invalid'))),
			array('jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => array('name' => 'get_company_info', 'arguments' => 'invalid')),
			array('jsonrpc' => '2.0', 'id' => 5, 'method' => 'resources/read', 'params' => array('uri' => array('invalid'))),
			array('jsonrpc' => '2.0', 'id' => 6, 'method' => 'prompts/get', 'params' => array('name' => 'inventory_health', 'arguments' => 'invalid'))
		);

		foreach ($requests as $request) {
			$response = $this->getUninitializedServer()->handleRequest($request);
			$this->assertSame(-32602, $response['error']['code'], $request['method'].' must reject malformed params');
		}
	}

	/**
	 * Invalid JSON-RPC IDs are never echoed back as response IDs.
	 *
	 * @return void
	 */
	public function testInvalidRequestIdIsReplacedWithNull()
	{
		$response = $this->getUninitializedServer()->handleRequest(array(
			'jsonrpc' => '2.0',
			'id' => array('invalid'),
			'method' => 'ping'
		));

		$this->assertSame(-32600, $response['error']['code']);
		$this->assertSame(null, $response['id']);
	}

	/**
	 * Header checks safely handle malformed names in decoded request params.
	 *
	 * @return void
	 */
	public function testTransportHeaderValidationHandlesMalformedNames()
	{
		$server = $this->getUninitializedServer();
		$response = $server->validateTransportHeaders(
			array('Mcp-Name' => 'get_company_info'),
			array('jsonrpc' => '2.0', 'id' => 7, 'method' => 'tools/call', 'params' => array('name' => array('invalid')))
		);

		$this->assertSame(-32020, $response['error']['code']);
	}
}
