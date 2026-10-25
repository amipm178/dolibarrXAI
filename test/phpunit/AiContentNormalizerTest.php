<?php
/* Copyright (C) 2026 dolibarrXAI contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

use PHPUnit\Framework\TestCase;

require_once dirname(__FILE__).'/../../htdocs/ai/class/contentnormalizer.class.php';

/**
 * Tests for normalizing AI-generated website page content.
 */
class AiContentNormalizerTest extends TestCase
{
	/**
	 * Strip markdown fences, document head, and body wrappers around generated HTML.
	 *
	 * @return void
	 */
	public function testExtractsBodyFromFencedHtmlDocument()
	{
		$content = "```html\n<!doctype html><html><head><title>Page</title></head><body><h1>Hello</h1></body></html>\n```";
		$this->assertSame('<section id="ai-generated-content" contenteditable="true"><h1>Hello</h1></section>', AiContentNormalizer::normalizeWebpage($content));
	}

	/**
	 * Keep an existing editable element intact without adding a nested editor region.
	 *
	 * @return void
	 */
	public function testPreservesExistingEditableSection()
	{
		$content = '<section id="main-content" contenteditable="true"><h1>Hello</h1></section>';
		$this->assertSame($content, AiContentNormalizer::normalizeWebpage($content));
	}

	/**
	 * Generate an inline-editor ID that does not collide with IDs in the returned fragment.
	 *
	 * @return void
	 */
	public function testGeneratedEditorIdIsUnique()
	{
		$content = '<div id="ai-generated-content">Existing</div><p>New page</p>';
		$this->assertSame('<section id="ai-generated-content-1" contenteditable="true">'.$content.'</section>', AiContentNormalizer::normalizeWebpage($content));
	}

	/**
	 * Do not manufacture an empty editor section for an empty provider result.
	 *
	 * @return void
	 */
	public function testEmptyContentRemainsEmpty()
	{
		$this->assertSame('', AiContentNormalizer::normalizeWebpage(" \n "));
	}
}
