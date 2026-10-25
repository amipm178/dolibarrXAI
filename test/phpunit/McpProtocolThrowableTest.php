<?php
/**
 * Regression tests for contained MCP dispatch failures.
 */
if (!defined('DOL_DOCUMENT_ROOT')) {
	define('DOL_DOCUMENT_ROOT', dirname(__DIR__, 2).'/htdocs');
}
if (!defined('LOG_ERR')) {
	define('LOG_ERR', 3);
}
if (!function_exists('dol_syslog')) {
	function dol_syslog($message, $level = 0)
	{
		// Deliberately discard test log output.
	}
}
require_once DOL_DOCUMENT_ROOT.'/ai/class/mcp_protocol.class.php';

class McpProtocolThrowableTest extends PHPUnit\Framework\TestCase
{
	public function testEngineErrorBecomesGenericJsonRpcError(): void
	{
		$reflection = new ReflectionClass('MCPServer');
		$server = $reflection->newInstanceWithoutConstructor();

		// Without the constructor, tools/list raises an Error when it accesses
		// the uninitialized handler. The protocol boundary must contain it.
		$response = $server->handleRequest(array(
			'jsonrpc' => '2.0',
			'id' => 9,
			'method' => 'tools/list',
			'params' => array()
		));

		$this->assertSame(-32000, $response['error']['code']);
		$this->assertSame('Internal server error', $response['error']['message']);
		$this->assertStringNotContainsString('uninitialized', strtolower(json_encode($response)));
	}
}
