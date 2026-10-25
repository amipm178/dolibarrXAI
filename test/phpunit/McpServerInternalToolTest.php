<?php
/**
 * Regression tests ensuring internal assistant tools are unavailable to external MCP clients.
 */
if (!defined('DOL_DOCUMENT_ROOT')) {
	define('DOL_DOCUMENT_ROOT', dirname(__DIR__, 2).'/htdocs');
}
if (!function_exists('getDolGlobalString')) {
	function getDolGlobalString($key, $default = '')
	{
		return '';
	}
}
if (!function_exists('dol_syslog')) {
	function dol_syslog($message, $level = 0)
	{
		// The standalone test does not bootstrap Dolibarr's logger.
	}
}
require_once DOL_DOCUMENT_ROOT.'/ai/class/mcp.class.php';

class McpServerInternalToolTestDouble extends McpTool
{
	public $executions = 0;
	private $systemClass;
	private $systemDefinition;
	private $toolName;

	public function __construct($toolName, $systemClass, $systemDefinition)
	{
		$this->toolName = $toolName;
		$this->systemClass = $systemClass;
		$this->systemDefinition = $systemDefinition;
	}

	public function getDefinitions(): array
	{
		return array(array(
			'name' => $this->toolName,
			'description' => 'Internal test tool',
			'inputSchema' => array('type' => 'object'),
			'is_system' => $this->systemDefinition
		));
	}

	public function execute(string $toolName, array $args)
	{
		$this->executions++;
		return array('ok' => true);
	}

	public function isSystem()
	{
		return $this->systemClass;
	}
}

class McpServerInternalToolTest extends PHPUnit\Framework\TestCase
{
	private function makeHandler($toolName, $systemClass, $systemDefinition)
	{
		$handler = new McpHandler(null, null, (object) array(), McpHandler::CTX_MCP_SERVER);
		$tool = new McpServerInternalToolTestDouble($toolName, $systemClass, $systemDefinition);
		$handler->loadedTools = array($toolName => $tool);
		$handler->toolsByName = array($toolName => $tool);
		return array($handler, $tool);
	}

	public function testClassMarkedSystemIsNotListedOrCallable(): void
	{
		list($handler, $tool) = $this->makeHandler('internal_class_tool', true, false);

		$this->assertSame(array(), $handler->getToolsSchema());
		$result = $handler->executeTool('internal_class_tool', array());
		$this->assertArrayHasKey('error', $result);
		$this->assertSame(0, $tool->executions);
	}

	public function testDefinitionMarkedSystemIsNotListedOrCallable(): void
	{
		list($handler, $tool) = $this->makeHandler('internal_definition_tool', false, true);

		$this->assertSame(array(), $handler->getToolsSchema());
		$result = $handler->executeTool('internal_definition_tool', array());
		$this->assertArrayHasKey('error', $result);
		$this->assertSame(0, $tool->executions);
	}
}
