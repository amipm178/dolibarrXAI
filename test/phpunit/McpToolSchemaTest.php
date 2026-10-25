<?php
/**
 * Regression tests for MCP input-schema normalization.
 *
 * Run with the repository PHPUnit configuration, for example:
 *   phpunit test/phpunit/McpToolSchemaTest.php
 */
require_once dirname(__DIR__, 2).'/htdocs/ai/class/mcptool.class.php';

class McpToolSchemaTest extends PHPUnit\Framework\TestCase
{
	public function testConstArrayIsPreservedAsOneEnumValue()
	{
		$schema = McpTool::normalizeInputSchema(array('const' => array('alpha', 'beta')));

		$this->assertSame('array', $schema['type']);
		$this->assertSame(array(array('alpha', 'beta')), $schema['enum']);
		$this->assertArrayNotHasKey('const', $schema);
	}

	public function testEmptyArrayConstIsTypedAsArray()
	{
		$schema = McpTool::normalizeInputSchema(array('const' => array()));

		$this->assertSame('array', $schema['type']);
		$this->assertSame(array(array()), $schema['enum']);
	}

	public function testNestedSchemasAreNormalized()
	{
		$schema = McpTool::normalizeInputSchema(array(
			'type' => 'object',
			'properties' => array(
				'status' => array('const' => 'ready', '$id' => 'ignored'),
			),
		));

		$this->assertSame(array('ready'), $schema['properties']['status']['enum']);
		$this->assertSame('string', $schema['properties']['status']['type']);
		$this->assertArrayNotHasKey('$id', $schema['properties']['status']);
	}

	public function testDataKeywordsAreNotTreatedAsSchemas()
	{
		$enumValue = array('const' => 'business data', '$id' => 'keep-this');
		$defaultValue = array('const' => 'default data', '$schema' => 'keep-this-too');
		$schema = McpTool::normalizeInputSchema(array(
			'type' => 'object',
			'enum' => array($enumValue),
			'default' => $defaultValue,
		));

		$this->assertSame(array($enumValue), $schema['enum']);
		$this->assertSame($defaultValue, $schema['default']);
	}

	public function testTupleItemsAreNormalizedIndividually()
	{
		$schema = McpTool::normalizeInputSchema(array(
			'type' => 'array',
			'items' => array(
				array('const' => 'first'),
				array('const' => 2),
			),
		));

		$this->assertSame(array('first'), $schema['items'][0]['enum']);
		$this->assertSame(array(2), $schema['items'][1]['enum']);
	}
}
