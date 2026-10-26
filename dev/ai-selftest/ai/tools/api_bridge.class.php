<?php
class ToolApiBridge extends McpTool
{
	public function getDefinitions(): array
	{
		return [
			['name' => 'api_products_stock', 'description' => 'stock', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]]],
			['name' => 'api_bad_utf8', 'description' => 'x', 'inputSchema' => ['type' => 'object']],
			['name' => 'api_demo_write', 'description' => 'w', 'inputSchema' => ['type' => 'object']],
		];
	}
	public function getRequiredRights(string $toolName) { return array(); }
	public function writeConfirmationPreview(string $toolName, array $args) { return $toolName === 'api_demo_write' ? 'Write something' : ''; }
	public function execute(string $toolName, array $args)
	{
		if ($toolName === 'api_bad_utf8') { return ['label' => "caf\xE9 \xFF"]; }
		return ['ok' => true, 'args' => $args];
	}
}
