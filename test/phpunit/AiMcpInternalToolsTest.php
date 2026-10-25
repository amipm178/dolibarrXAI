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
if (!function_exists('getDolGlobalString')) {
	function getDolGlobalString($key)
	{
		return '';
	}
}
if (!function_exists('dol_syslog')) {
	function dol_syslog($message, $level = 0)
	{
		return;
	}
}
require_once DOL_DOCUMENT_ROOT.'/ai/class/mcp.class.php';

/**
 * Tests that internal assistant tools stay inside the first-party assistant.
 */
class AiMcpInternalToolsTest extends TestCase
{
	/**
	 * Build a handler without loading Dolibarr services or native tools.
	 *
	 * @param string  $context Tool context.
	 * @param McpTool $tool Tool fixture.
	 * @return McpHandler Handler with one registered tool fixture.
	 */
	private function getHandler($context, $tool)
	{
		$reflection = new ReflectionClass('McpHandler');
		$handler = $reflection->newInstanceWithoutConstructor();

		$contextProperty = $reflection->getProperty('toolcontext');
		$contextProperty->setAccessible(true);
		$contextProperty->setValue($handler, $context);

		$handler->loadedTools = array('fixture' => $tool);
		foreach ($tool->getDefinitions() as $definition) {
			$handler->toolsByName[$definition['name']] = $tool;
		}

		return $handler;
	}

	/**
	 * MCP clients cannot see class-level or definition-level system tools.
	 *
	 * @return void
	 */
	public function testMcpSchemaOmitsInternalTools()
	{
		$handler = $this->getHandler(McpHandler::CTX_MCP_SERVER, new AiMcpInternalClassFixture());
		$schema = $handler->getToolsSchema();
		$names = array_column($schema, 'name');

		$this->assertSame(array(), $names);

		$handler = $this->getHandler(McpHandler::CTX_MCP_SERVER, new AiMcpMixedDefinitionFixture());
		$schema = $handler->getToolsSchema();
		$names = array_column($schema, 'name');

		$this->assertSame(array('external_read_tool'), $names);
	}

	/**
	 * The first-party assistant still receives internal tools for orchestration.
	 *
	 * @return void
	 */
	public function testAssistantSchemaRetainsInternalTools()
	{
		$handler = $this->getHandler(McpHandler::CTX_ASSISTANT, new AiMcpInternalClassFixture());
		$schema = $handler->getToolsSchema();

		$this->assertSame(array('respond_to_user'), array_column($schema, 'name'));
		$this->assertTrue($schema[0]['is_system']);
	}

	/**
	 * Direct MCP calls to internal tools are rejected before execution.
	 *
	 * @return void
	 */
	public function testMcpExecutionRejectsInternalTools()
	{
		$handler = $this->getHandler(McpHandler::CTX_MCP_SERVER, new AiMcpInternalClassFixture());
		$result = $handler->executeTool('respond_to_user', array('message' => 'private'));

		$this->assertArrayHasKey('error', $result);
		$this->assertSame("Internal system tools are not available through the MCP server.", $result['error']);
	}
}

/**
 * Fixture representing class-level first-party assistant infrastructure.
 */
class AiMcpInternalClassFixture extends McpTool
{
	/**
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct(null, null, null);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function getDefinitions(): array
	{
		return array(array('name' => 'respond_to_user'));
	}

	/**
	 * @param string               $toolName Tool name.
	 * @param array<string, mixed> $args Tool arguments.
	 * @return array<string, mixed>
	 */
	public function execute(string $toolName, array $args)
	{
		return array('executed' => true);
	}

	/**
	 * @return bool
	 */
	public function isSystem()
	{
		return true;
	}
}

/**
 * Fixture mixing one internal definition with one external tool definition.
 */
class AiMcpMixedDefinitionFixture extends McpTool
{
	/**
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct(null, null, null);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function getDefinitions(): array
	{
		return array(
			array('name' => 'internal_action', 'is_system' => true),
			array('name' => 'external_read_tool')
		);
	}

	/**
	 * @param string               $toolName Tool name.
	 * @param array<string, mixed> $args Tool arguments.
	 * @return array<string, mixed>
	 */
	public function execute(string $toolName, array $args)
	{
		return array('executed' => true);
	}

	/**
	 * @param string $toolName Tool name.
	 * @return array<int,array<int,string>>
	 */
	public function getRequiredRights(string $toolName)
	{
		return array();
	}
}
