<?php
/**
 * Regression tests for malformed MCP JSON-RPC params.
 */
if (!defined('DOL_DOCUMENT_ROOT')) {
	define('DOL_DOCUMENT_ROOT', dirname(__DIR__, 2).'/htdocs');
}
require_once DOL_DOCUMENT_ROOT.'/ai/class/mcp_protocol.class.php';

class McpProtocolParamsTest extends PHPUnit\\Framework\\TestCase
{
	private function makeServerWithoutRuntime(): MCPServer
	{
		$reflection = new ReflectionClass('MCPServer');
		return $reflection->newInstanceWithoutConstructor();
	}

	public function testScalarParamsReturnInvalidParamsInsteadOfThrowing(): void
	{
		$server = $this->makeServerWithoutRuntime();
		$response = $server->handleRequest(array(
			'jsonrpc' => '2.0',
			'id' => 1,
			'method' => 'ping',
			'params' => 'unexpected scalar'
		));

		$this->assertSame(-32602, $response['error']['code']);
		$this->assertSame('Invalid params', $response['error']['message']);
	}

	public function testExplicitNullParamsReturnInvalidParams(): void
	{
		$server = $this->makeServerWithoutRuntime();
		$response = $server->handleRequest(array(
			'jsonrpc' => '2.0',
			'id' => 10,
			'method' => 'ping',
			'params' => null
		));

		$this->assertSame(-32602, $response['error']['code']);
	}

	public function testScalarMetaReturnsInvalidParams(): void
	{
		$server = $this->makeServerWithoutRuntime();
		$response = $server->handleRequest(array(
			'jsonrpc' => '2.0',
			'id' => 2,
			'method' => 'ping',
			'params' => array('_meta' => 'not-an-object')
		));

		$this->assertSame(-32602, $response['error']['code']);
	}

	public function testNonStringProtocolVersionReturnsInvalidParams(): void
	{
		$server = $this->makeServerWithoutRuntime();
		$response = $server->handleRequest(array(
			'jsonrpc' => '2.0',
			'id' => 3,
			'method' => 'ping',
			'params' => array('_meta' => array(
				'io.modelcontextprotocol/protocolVersion' => array('2026-07-28')
			))
		));

		$this->assertSame(-32602, $response['error']['code']);
	}

	public function testUnsupportedStringProtocolVersionRemainsExplicit(): void
	{
		$server = $this->makeServerWithoutRuntime();
		$response = $server->handleRequest(array(
			'jsonrpc' => '2.0',
			'id' => 4,
			'method' => 'ping',
			'params' => array('_meta' => array(
				'io.modelcontextprotocol/protocolVersion' => '2099-01-01'
			))
		));

		$this->assertSame(-32022, $response['error']['code']);
		$this->assertSame(400, $server->getHttpStatus());
	}

	public function testMethodSpecificValuesMustHaveExpectedTypes(): void
	{
		$cases = array(
			array('method' => 'initialize', 'params' => array('protocolVersion' => array('2026-07-28'))),
			array('method' => 'tools/call', 'params' => array('name' => array('tool'))),
			array('method' => 'tools/call', 'params' => array('name' => 'api_products_list', 'arguments' => 'not-an-object')),
			array('method' => 'resources/read', 'params' => array('uri' => array('dolibarr://company/info'))),
			array('method' => 'prompts/get', 'params' => array('name' => 'inventory_health', 'arguments' => 'not-an-object'))
		);

		foreach ($cases as $index => $case) {
			$server = $this->makeServerWithoutRuntime();
			$response = $server->handleRequest(array(
				'jsonrpc' => '2.0',
				'id' => 20 + $index,
				'method' => $case['method'],
				'params' => $case['params']
			));
			$this->assertSame(-32602, $response['error']['code'], $case['method']);
		}
	}

	public function testTransportValidationDoesNotCoerceArrayProtocolVersion(): void
	{
		$server = $this->makeServerWithoutRuntime();
		$request = array(
			'id' => 5,
			'method' => 'ping',
			'params' => array('_meta' => array(
				'io.modelcontextprotocol/protocolVersion' => array('2026-07-28')
			))
		);
		$response = $server->validateTransportHeaders(
			array('MCP-Protocol-Version' => '2026-07-28'),
			$request
		);

		// The precheck must not coerce malformed metadata or abort a batch.
		$this->assertNull($response);
		$dispatchResponse = $server->handleRequest(array(
			'jsonrpc' => '2.0',
			'id' => 5,
			'method' => 'ping',
			'params' => $request['params']
		));
		$this->assertSame(-32602, $dispatchResponse['error']['code']);
	}
}
