<?php
/**
 * Regression tests for malformed MCP JSON-RPC params.
 */
if (!defined('DOL_DOCUMENT_ROOT')) {
	define('DOL_DOCUMENT_ROOT', dirname(__DIR__, 2).'/htdocs');
}
require_once DOL_DOCUMENT_ROOT.'/ai/class/mcp_protocol.class.php';

class McpProtocolParamsTest extends PHPUnit\Framework\TestCase
{
	private function makeServerWithoutRuntime(): MCPServer
	{
		$reflection = new ReflectionClass('MCPServer');
		return $reflection->newInstanceWithoutConstructor();
	}

	public function testExplicitNullIdIsNotTreatedAsNotification(): void
	{
		$server = $this->makeServerWithoutRuntime();
		$response = $server->handleRequest(array(
			'jsonrpc' => '2.0',
			'id' => null,
			'method' => 'ping',
			'params' => array()
		));

		$this->assertTrue(is_array($response));
		$this->assertArrayHasKey('id', $response);
		$this->assertNull($response['id']);
		$this->assertSame('ok', $response['result']['status']);
	}

	public function testExplicitNullIdReceivesMethodNotFoundError(): void
	{
		$server = $this->makeServerWithoutRuntime();
		$response = $server->handleRequest(array(
			'jsonrpc' => '2.0',
			'id' => null,
			'method' => 'method/does-not-exist',
			'params' => array()
		));

		$this->assertTrue(is_array($response));
		$this->assertArrayHasKey('id', $response);
		$this->assertNull($response['id']);
		$this->assertSame(-32601, $response['error']['code']);
		$this->assertSame('Method not found', $response['error']['message']);
		$this->assertStringNotContainsString('method/does-not-exist', json_encode($response));
	}

	public function testUnsupportedMetaProtocolVersionIsNotEchoed(): void
	{
		$server = $this->makeServerWithoutRuntime();
		$version = 'private-version-'.str_repeat('x', 200);
		$response = $server->handleRequest(array(
			'jsonrpc' => '2.0',
			'id' => 5,
			'method' => 'ping',
			'params' => array('_meta' => array('io.modelcontextprotocol/protocolVersion' => $version))
		));

		$this->assertTrue(is_array($response));
		$this->assertSame(-32022, $response['error']['code']);
		$this->assertStringNotContainsString($version, json_encode($response));
	}

	public function testUnknownResourceUriIsNotEchoed(): void
	{
		$server = $this->makeServerWithoutRuntime();
		$uri = 'dolibarr://private/'.str_repeat('x', 200);
		$response = $server->handleRequest(array(
			'jsonrpc' => '2.0',
			'id' => 3,
			'method' => 'resources/read',
			'params' => array('uri' => $uri)
		));

		$this->assertTrue(is_array($response));
		$this->assertSame(-32000, $response['error']['code']);
		$this->assertStringNotContainsString($uri, json_encode($response));
	}

	public function testUnknownPromptNameIsNotEchoed(): void
	{
		$server = $this->makeServerWithoutRuntime();
		$name = 'private-prompt-'.str_repeat('x', 200);
		$response = $server->handleRequest(array(
			'jsonrpc' => '2.0',
			'id' => 4,
			'method' => 'prompts/get',
			'params' => array('name' => $name)
		));

		$this->assertTrue(is_array($response));
		$this->assertSame(-32000, $response['error']['code']);
		$this->assertStringNotContainsString($name, json_encode($response));
	}

	public function testMissingIdRemainsNotification(): void
	{
		$server = $this->makeServerWithoutRuntime();
		$response = $server->handleRequest(array(
			'jsonrpc' => '2.0',
			'method' => 'ping',
			'params' => array()
		));

		$this->assertNull($response);
	}

	public function testInvalidRequestIdsAreNotEchoed(): void
	{
		$invalidIds = array(array('bad-id'), true, false);
		foreach ($invalidIds as $index => $invalidId) {
			$server = $this->makeServerWithoutRuntime();
			$response = $server->handleRequest(array(
				'jsonrpc' => '2.0',
				'id' => $invalidId,
				'method' => 'ping',
				'params' => array()
			));
			$this->assertSame(-32600, $response['error']['code']);
			$this->assertNull($response['id']);
		}
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
