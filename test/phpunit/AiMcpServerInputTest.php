<?php
/* Copyright (C) 2026 dolibarrXAI contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests request-boundary protections in the public MCP HTTP entry point.
 */
class AiMcpServerInputTest extends TestCase
{
	/**
	 * The endpoint bounds its stream read and reports oversized requests as 413.
	 *
	 * @return void
	 */
	public function testRequestBodyIsBoundedBeforeItIsLoadedIntoMemory()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/server/mcp_server.php');

		$this->assertIsString($source);
		$this->assertStringContainsString("file_get_contents('php://input', false, null, 0, \$maxRequestBytes + 1)", $source);
		$this->assertStringContainsString('if (strlen($rawInput) > $maxRequestBytes)', $source);
		$this->assertStringContainsString('http_response_code(413);', $source);
		$this->assertStringContainsString('"message" => "Request too large"', $source);
	}

	/**
	 * Header retrieval and outer error handling tolerate PHP runtime errors.
	 *
	 * @return void
	 */
	public function testMalformedHeadersCannotBreakNormalization()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/server/mcp_server.php');

		$this->assertStringContainsString("is_array(\$headers) ? array_change_key_case(\$headers, CASE_LOWER) : array()", $source);
		$this->assertStringContainsString('} catch (Throwable $e) {', $source);
		$this->assertStringContainsString("get_class(\$e)", $source);
	}

	/**
	 * The optional REST-compatible entity header is applied before Dolibarr loads.
	 *
	 * @return void
	 */
	public function testEntityHeaderIsValidatedBeforeBootstrap()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/server/mcp_server.php');
		$entityDefinition = strpos($source, "define('DOLENTITY', \$entityId)");
		$mainBootstrap = strpos($source, "require '../../main.inc.php'");

		$this->assertNotFalse($entityDefinition);
		$this->assertNotFalse($mainBootstrap);
		$this->assertLessThan($mainBootstrap, $entityDefinition);
		$this->assertStringContainsString("define('NOSESSION', 1)", $source);
	}

	/**
	 * MCP API-key authentication follows REST entity scoping and switches context.
	 *
	 * @return void
	 */
	public function testApiKeyIdentityIsScopedToItsEntity()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/class/mcpauth.class.php');

		$this->assertStringContainsString("\$entityJoin = \$transverse && defined('DOLENTITY')", $source);
		$this->assertStringContainsString('INNER JOIN ".$this->db->prefix()."usergroup_user as gu', $source);
		$this->assertStringContainsString('AND oat.entity = gu.entity', $source);
		$this->assertStringContainsString('$this->switchEntityContext($targetEntity)', $source);
		$this->assertStringContainsString('Look up the API-key owner and entity scope', $source);
		$this->assertStringContainsString('public function userCanAccessCurrentEntity($candidate)', $source);
		$this->assertStringContainsString('AND entity = ".$entity." LIMIT 1', $source);
	}
}
