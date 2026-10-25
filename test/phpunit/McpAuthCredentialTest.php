<?php
/**
 * Regression tests for MCP credential type validation.
 */
if (!defined('DOL_DOCUMENT_ROOT')) {
	define('DOL_DOCUMENT_ROOT', dirname(__DIR__, 2).'/htdocs');
}
if (!function_exists('dol_string_nounprintableascii')) {
	function dol_string_nounprintableascii($value, $strip = 0)
	{
		return (string) $value;
	}
}
require_once DOL_DOCUMENT_ROOT.'/ai/class/mcpauth.class.php';

class McpAuthCredentialTest extends PHPUnit\Framework\TestCase
{
	private function makeAuth(): McpAuth
	{
		return new McpAuth(null);
	}

	public function testArrayQueryCredentialIsIgnored(): void
	{
		$auth = $this->makeAuth();
		$this->assertSame('', $auth->getCredential(array(), array('key' => array('not-a-string'))));
	}

	public function testArrayHeaderIsIgnoredAndStringHeaderIsAccepted(): void
	{
		$auth = $this->makeAuth();
		$this->assertSame('valid-header-key', $auth->getCredential(
			array('HTTP_DOLAPIKEY' => array('invalid'), 'HTTP_X_API_KEY' => 'valid-header-key'),
			array()
		));
	}

	public function testStringQueryCredentialRemainsSupported(): void
	{
		$auth = $this->makeAuth();
		$this->assertSame('valid-query-key', $auth->getCredential(array(), array('api_key' => 'valid-query-key')));
	}
}
