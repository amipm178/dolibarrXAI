<?php
/**
 * Regression tests for MCP audit log payload redaction.
 *
 * Run with the repository PHPUnit configuration, for example:
 *   phpunit test/phpunit/McpLogSanitizerTest.php
 */
require_once dirname(__DIR__, 2).'/htdocs/ai/class/mcplogsanitizer.class.php';

class McpLogSanitizerTest extends PHPUnit\Framework\TestCase
{
	public function testRequestLogsOnlyArgumentKeys(): void
	{
		$request = array(
			'jsonrpc' => '2.0',
			'id' => 7,
			'method' => 'tools/call',
			'params' => array(
				'name' => 'create_invoice',
				'arguments' => array('customer' => 'Sensitive Customer', 'amount' => 12345)
			)
		);

		$safe = McpLogSanitizer::sanitizeRequest($request);
		$encoded = json_encode($safe);

		$this->assertSame(array('customer', 'amount'), $safe['params']['arguments']['keys']);
		$this->assertStringNotContainsString('Sensitive Customer', $encoded);
		$this->assertStringNotContainsString('12345', $encoded);
	}

	public function testResponseLogsOmitResultContentAndErrorDetails(): void
	{
		$response = array(
			'jsonrpc' => '2.0',
			'id' => 7,
			'result' => array('content' => array(array('type' => 'text', 'text' => 'Private invoice total 999.00'))),
		);
		$safe = McpLogSanitizer::sanitizeResponse($response);
		$encoded = json_encode($safe);

		$this->assertSame(1, $safe['result']['content_count']);
		$this->assertStringNotContainsString('Private invoice total', $encoded);
		$this->assertStringNotContainsString('999.00', $encoded);

		$error = McpLogSanitizer::sanitizeResponse(array('error' => array('code' => -32000, 'message' => 'Private company name')));
		$this->assertStringNotContainsString('Private company name', json_encode($error));
		$this->assertSame(-32000, $error['error']['code']);
	}

	public function testRequestMetadataIsBounded(): void
	{
		$request = array(
			'jsonrpc' => '2.0',
			'id' => str_repeat('i', 200),
			'method' => 'tools/call',
			'params' => array('name' => str_repeat('t', 400), 'arguments' => array())
		);
		$safe = McpLogSanitizer::sanitizeRequest($request);

		$this->assertSame('[string-id]', $safe['id']);
		$this->assertSame(255, strlen($safe['params']['name']));
	}

	public function testArgumentKeyMetadataIsBounded(): void
	{
		$arguments = array();
		for ($i = 0; $i < 75; $i++) {
			$arguments[str_repeat('k', 120).$i] = 'private';
		}
		$keys = McpLogSanitizer::argumentKeys($arguments);

		$this->assertCount(50, $keys);
		$this->assertSame('[redacted-key]', $keys[0]);
	}

	public function testUnknownKeysWithControlCharactersAreRedacted(): void
	{
		$keys = McpLogSanitizer::argumentKeys(array("customer\nforged-log-entry" => 'private-value'));
		$this->assertSame(array('[redacted-key]'), $keys);
	}

	public function testRequestMetadataDoesNotLogUntrustedIdsOrNames(): void
	{
		$request = array(
			'jsonrpc' => '2.0',
			'id' => 'person@example.test',
			'method' => 'unknown/private-method',
			'params' => array('name' => 'person@example.test', 'arguments' => array())
		);
		$safe = McpLogSanitizer::sanitizeRequest($request);
		$encoded = json_encode($safe);

		$this->assertSame('[string-id]', $safe['id']);
		$this->assertSame('[unknown]', $safe['method']);
		$this->assertSame('[redacted]', $safe['params']['name']);
		$this->assertStringNotContainsString('person@example.test', $encoded);
	}

	public function testUnknownArgumentKeysCannotLeakPersonalData(): void
	{
		$keys = McpLogSanitizer::argumentKeys(array(
			'person@example.test' => 'private',
			'Jane Doe' => 'private',
			'JaneDoe' => 'private',
			'customer' => 'private'
		));

		$this->assertSame(
			array('[redacted-key]', '[redacted-key]', '[redacted-key]', 'customer'),
			$keys
		);
	}

	public function testJsonEncodedArgumentObjectReturnsKeysOnly(): void
	{
		$this->assertSame(
			array('email', 'note'),
			McpLogSanitizer::argumentKeys('{"email":"person@example.test","note":"secret"}')
		);
	}
}
