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
 * Keep AI_DEBUG diagnostics free of conversation text and model arguments.
 */
class AiAssistantDebugPrivacyTest extends TestCase
{
	/**
	 * Debug logs report sizes and counts without recording prompt or answer text.
	 *
	 * @return void
	 */
	public function testPromptHistoryAndResponseAreNotLogged()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/assistant/parse_intent.php');

		$this->assertStringContainsString('user query received (content redacted)', $source);
		$this->assertStringContainsString('conversation history prepared (content redacted', $source);
		$this->assertStringContainsString('cleaned model response prepared (content redacted)', $source);
		$this->assertStringContainsString('dynamic stop words prepared: count=', $source);
		$this->assertStringNotContainsString('dynamicStopWords: " . implode', $source);
		$this->assertStringNotContainsString("AI Validation: Tool '", $source);
		$this->assertStringNotContainsString("systemPrompt = '.\$systemPrompt", $source);
		$this->assertStringNotContainsString("query = '.\$query", $source);
		$this->assertStringNotContainsString("history = '.json_encode(\$history", $source);
		$this->assertStringNotContainsString("response var clean='.\$clean", $source);
		$this->assertStringNotContainsString("intentJSON = '.var_export(\$intentJSON", $source);
		$this->assertStringNotContainsString("'query' => \$query", $source);
	}

	/**
	 * Unexpected PHP errors are logged without their potentially sensitive message.
	 *
	 * @return void
	 */
	public function testCriticalErrorLogUsesExceptionMetadata()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/assistant/parse_intent.php');

		$this->assertStringContainsString('get_class($e)', $source);
		$this->assertStringNotContainsString('$e->getMessage() . " in "', $source);
	}

	/**
	 * Tool execution diagnostics never include argument or result values.
	 *
	 * @return void
	 */
	public function testToolExecutionDebugOutputContainsOnlyCounts()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/assistant/execute_tool.php');

		$this->assertStringContainsString('argument_count=', $source);
		$this->assertStringContainsString('field_count=', $source);
		$this->assertStringNotContainsString("arguments = ".'json_encode($input[\'arguments\']', $source);
		$this->assertStringNotContainsString('var_export($result', $source);
	}

	/**
	 * Tool execution bounds the raw request read and rejects oversized bodies.
	 *
	 * @return void
	 */
	public function testToolExecutionRequestBodyIsBounded()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/assistant/execute_tool.php');

		$this->assertStringContainsString("file_get_contents('php://input', false, null, 0, \$maxRequestBytes + 1)", $source);
		$this->assertStringContainsString('strlen($raw) > $maxRequestBytes', $source);
		$this->assertStringContainsString('http_response_code(413)', $source);
	}

	/**
	 * The chat command endpoint rejects oversized JSON before decoding it.
	 *
	 * @return void
	 */
	public function testCommandRequestBodyIsBounded()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/assistant/command.php');

		$this->assertStringContainsString("file_get_contents('php://input', false, null, 0, \$maxRequestBytes + 1)", $source);
		$this->assertStringContainsString('strlen($rawInput) > $maxRequestBytes', $source);
		$this->assertStringContainsString('http_response_code(413)', $source);
	}

	/**
	 * The assistant parser bounds incoming query and attachment bodies.
	 *
	 * @return void
	 */
	public function testAssistantParserRequestBodyIsBounded()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/assistant/parse_intent.php');

		$this->assertStringContainsString('16 * 1024 * 1024', $source);
		$this->assertStringContainsString("stream_get_contents(\$inputStream, \$maxRequestBytes + 1)", $source);
		$this->assertStringContainsString('strlen($raw_input) > $maxRequestBytes', $source);
		$this->assertStringContainsString('http_response_code(413)', $source);
	}

	/**
	 * Adapter debug logs redact provider endpoints and all response text.
	 *
	 * @return void
	 */
	public function testAdapterDebugOutputContainsOnlyResponseMetadata()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/class/llmadapter.class.php');

		$this->assertStringContainsString('AI provider response parsed:', $source);
		$this->assertStringNotContainsString('curl answer body=', $source);
		$this->assertStringNotContainsString('var_export($body', $source);
		$this->assertStringNotContainsString('Body snippet:', $source);
		$this->assertStringNotContainsString('callOpenAI url=', $source);
	}
}
