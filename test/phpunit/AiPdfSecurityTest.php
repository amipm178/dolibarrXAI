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
 * Security invariants for PDF report generation from assistant results.
 */
class AiPdfSecurityTest extends TestCase
{
	/**
	 * Bound report input and validate nested structures before rendering.
	 *
	 * @return void
	 */
	public function testPdfInputIsBoundedAndShapeChecked()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/assistant/download_pdf.php');
		$this->assertStringContainsString("file_get_contents('php://input', false, null, 0, 5 * 1024 * 1024 + 1)", $source);
		$this->assertStringContainsString('dol_strlen($json) > 5 * 1024 * 1024', $source);
		$this->assertStringContainsString('json_last_error() !== JSON_ERROR_NONE || !is_array($data)', $source);
		$this->assertStringContainsString('aiPdfFormatValue((string) $k, $v)', $source);
		$this->assertStringContainsString("aiCheckCsrfToken('ai/assistant/download_pdf.php')", $source);
		$this->assertStringContainsString('} catch (Throwable $e) {', $source);
		$this->assertStringContainsString('get_class($e)', $source);
	}
}
