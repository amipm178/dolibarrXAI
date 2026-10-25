<?php
/**
 * Regression tests for the inventory-health MCP prompt.
 */
if (!defined('DOL_DOCUMENT_ROOT')) {
	define('DOL_DOCUMENT_ROOT', dirname(__DIR__, 2).'/htdocs');
}
require_once DOL_DOCUMENT_ROOT.'/ai/class/mcp_protocol.class.php';

class McpInventoryHealthPromptTest extends PHPUnit\\Framework\\TestCase
{
	private function getPrompt(array $params)
	{
		$reflection = new ReflectionClass('MCPServer');
		$server = $reflection->newInstanceWithoutConstructor();
		$method = $reflection->getMethod('handlePromptGet');
		$method->setAccessible(true);
		return $method->invoke($server, $params);
	}

	public function testPromptUsesToolsThatExistInTheBridge(): void
	{
		$prompt = $this->getPrompt(array(
			'name' => 'inventory_health',
			'arguments' => array('product_name' => 'Widget')
		));
		$text = $prompt['messages'][1]['content']['text'];

		$this->assertStringContainsString('api_products_list', $text);
		$this->assertStringContainsString('api_products_get', $text);
		$this->assertStringContainsString('api_stockmovements_list', $text);
		$this->assertStringNotContainsString('analyze_stock_forecast', $text);
		$this->assertStringContainsString('Never invent missing data', $text);
	}

	public function testProductNameKeepsAccentsAndDropsControlCharacters(): void
	{
		$productName = "Café\x07";
		$prompt = $this->getPrompt(array(
			'name' => 'inventory_health',
			'arguments' => array('product_name' => $productName)
		));
		$text = $prompt['messages'][2]['content']['text'];

		$this->assertStringContainsString('Café', $text);
		$this->assertStringNotContainsString("\x07", $text);
	}
}
