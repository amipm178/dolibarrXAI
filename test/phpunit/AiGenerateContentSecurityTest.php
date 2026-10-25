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
 * Security invariants for the legacy AI content-generation endpoint.
 */
class AiGenerateContentSecurityTest extends TestCase
{
	/**
	 * Keep the legacy form-generation endpoint module-gated and protected against CSRF.
	 *
	 * @return void
	 */
	public function testGenerationRequiresAssistantAccessAndCsrfToken()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/ajax/generate_content.php');

		$this->assertStringContainsString("if (!isModEnabled('ai'))", $source);
		$this->assertStringNotContainsString('AI_ASSISTANT_ENABLED', $source);
		$this->assertStringNotContainsString('$user->hasRight(\'ai\', \'assistant\', \'use\')', $source);
		$this->assertStringContainsString("aiCheckCsrfToken('ai/ajax/generate_content.php')", $source);
		$this->assertLessThan(strpos($source, "file_get_contents('php://input'"), strpos($source, "aiCheckCsrfToken('ai/ajax/generate_content.php')"));
	}

	/**
	 * Never write user-provided prompt contents to the Dolibarr log.
	 *
	 * @return void
	 */
	public function testPromptContentsAreNotLogged()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/ajax/generate_content.php');
		$this->assertStringNotContainsString('instruction=".dol_substr($instructions', $source);
		$this->assertStringContainsString('instruction_length=".dol_strlen($instructions)', $source);
	}

	/**
	 * Escape error text returned by the configured AI provider before it reaches HTML callers.
	 *
	 * @return void
	 */
	public function testProviderErrorsAreRenderedAsText()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/ajax/generate_content.php');
		$this->assertStringContainsString('"Error : " . dolPrintText((string) $generatedContent[\'message\'])', $source);
		$this->assertStringContainsString('"Error returned by API call: " . dolPrintText((string) $generatedContent[\'message\'])', $source);
	}

	/**
	 * The form endpoint must accept declared features only, never low-level provider operations.
	 *
	 * @return void
	 */
	public function testOnlyDeclaredAiFeaturesAndFormatsAreAccepted()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/ajax/generate_content.php');
		$this->assertStringContainsString('$availableFeatures = getListOfAIFeatures();', $source);
		$this->assertStringContainsString('!array_key_exists($function, $availableFeatures)', $source);
		$this->assertStringContainsString("array('', 'text', 'html')", $source);
		$this->assertStringContainsString("array('dolibarr', 'experimental')", $source);
		$this->assertStringContainsString("in_array(\$function, array('videogeneration', 'audiogeneration'), true)", $source);
		$this->assertStringContainsString('http_response_code(501)', $source);
	}

	/**
	 * Image generation must return a validated image source instead of an empty response.
	 *
	 * @return void
	 */
	public function testImageGenerationReturnsOnlySafeImageSources()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/ajax/generate_content.php');
		$aiSource = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/class/ai.class.php');
		$this->assertStringContainsString('print $generatedContent;', $source);
		$this->assertStringContainsString('data:image\\/', $source);
		$this->assertStringContainsString("'prompt' => \$fullInstructions", $aiSource);
		$this->assertStringContainsString("\$function == 'imagegeneration' && \$this->apiService == 'google'", $aiSource);
		$this->assertStringContainsString("'responseModalities' => array('IMAGE')", $aiSource);
		$this->assertStringContainsString("'b64_json'", $aiSource);
	}

	/**
	 * Require credentials for every built-in remote provider configured in the service catalog.
	 *
	 * @return void
	 */
	public function testRemoteServicesDeclareApiKeyRequirement()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/lib/ai.lib.php');
	$this->assertSame(8, preg_match_all("/'requires_key'\\s*=> true/", $source));
	}

	/**
	 * Bound body reads so an oversized prompt cannot consume unbounded PHP memory.
	 *
	 * @return void
	 */
	public function testRequestBodyHasServerSideSizeLimit()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/ajax/generate_content.php');
		$this->assertStringContainsString("file_get_contents('php://input', false, null, 0, 1024 * 1024 + 1)", $source);
		$this->assertStringContainsString('http_response_code(413)', $source);
	}

	/**
	 * AI call logs must describe requests without disclosing provider secrets or response text.
	 *
	 * @return void
	 */
	public function testAiCallLogsContainOnlyNonSensitiveMetadata()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/class/ai.class.php');
		$this->assertStringContainsString('$apiCallLog = "Call API for service=".$this->apiService', $source);
		$this->assertStringContainsString('generatedContent returned (".dol_strlen($generatedContent)', $source);
		$this->assertStringNotContainsString('apiKey=', $source);
		$this->assertStringNotContainsString('apiEndpoint=', $source);
		$this->assertStringNotContainsString('generatedContent returned: ".dol_trunc($generatedContent', $source);
	}

	/**
	 * Debug mode must not write prompts, API headers, payloads, or answer bodies.
	 *
	 * @return void
	 */
	public function testDebugLogContainsMetadataButNoRequestOrResponseContents()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/class/ai.class.php');

		$this->assertStringContainsString('AI request metadata:', $source);
		$this->assertStringContainsString('AI response metadata:', $source);
		$this->assertStringNotContainsString('var_export($headers', $source);
		$this->assertStringNotContainsString('var_export($payload', $source);
		$this->assertStringNotContainsString('$fullInstructions."\\n"', $source);
		$this->assertStringNotContainsString('$response[\'content\']), true)', $source);
		$this->assertStringContainsString('} catch (Throwable $e) {', $source);
	}

	/**
	 * Malformed successful provider responses must fail through the normal error path.
	 *
	 * @return void
	 */
	public function testMalformedProviderResponseIsRejectedBeforeExtraction()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/class/ai.class.php');

		$this->assertStringContainsString('if (!is_array($decodedResponse))', $source);
		$this->assertStringContainsString("AI provider returned an unexpected response structure", $source);
	}
}
