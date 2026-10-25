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
 * Security invariants for the AI assistant's persisted conversation history.
 */
class AiChatHistorySecurityTest extends TestCase
{
	/**
	 * Every history lookup and mutation must stay within the user's current entity.
	 *
	 * @return void
	 */
	public function testConversationMutationsAreEntityScoped()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/ajax/chat_history.php');

		$this->assertStringContainsString("AND entity = \".((int) getEntity('ai'))", $source);
		$this->assertStringContainsString("AND c.entity = \".((int) getEntity('ai'))", $source);
	}

	/**
	 * Bound request-body reads so the history endpoint cannot load unbounded JSON into memory.
	 *
	 * @return void
	 */
	public function testHistoryRequestBodyHasServerSideSizeLimit()
	{
		$source = file_get_contents(dirname(__FILE__).'/../../htdocs/ai/ajax/chat_history.php');
		$this->assertStringContainsString("file_get_contents('php://input', false, null, 0, 1024 * 1024 + 1)", $source);
		$this->assertStringContainsString('http_response_code(413)', $source);
	}
}
