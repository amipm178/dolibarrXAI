<?php
/**
 * Regression tests for MCP tool-loading path confinement.
 *
 * Run with the repository PHPUnit configuration, for example:
 *   phpunit test/phpunit/McpHandlerSecurityTest.php
 */
if (!defined('DOL_DOCUMENT_ROOT')) {
	define('DOL_DOCUMENT_ROOT', dirname(__DIR__, 2).'/htdocs');
}
require_once DOL_DOCUMENT_ROOT.'/ai/class/mcp.class.php';

class McpHandlerSecurityTest extends PHPUnit\\Framework\\TestCase
{
	private $testRoot;
	private $outsideRoot;

	protected function setUp(): void
	{
		$suffix = bin2hex(random_bytes(6));
		$this->testRoot = sys_get_temp_dir().'/dolibarr-mcp-tools-'.$suffix;
		$this->outsideRoot = $this->testRoot.'-outside';
		mkdir($this->testRoot, 0700, true);
		mkdir($this->outsideRoot, 0700, true);
	}

	protected function tearDown(): void
	{
		foreach (array($this->testRoot, $this->outsideRoot) as $directory) {
			if (!is_dir($directory)) {
				continue;
			}
			$items = scandir($directory);
			foreach ($items as $item) {
				if ($item === '.' || $item === '..') {
					continue;
				}
				$path = $directory.'/'.$item;
				if (is_link($path) || is_file($path)) {
					unlink($path);
				} elseif (is_dir($path)) {
					foreach (scandir($path) as $child) {
						if ($child !== '.' && $child !== '..') {
							unlink($path.'/'.$child);
						}
					}
					rmdir($path);
				}
			}
			rmdir($directory);
		}
	}

	public function testAcceptsFilesInsideToolsDirectory(): void
	{
		$file = $this->testRoot.'/safe.class.php';
		file_put_contents($file, '<?php // test');

		$this->assertTrue(McpHandler::isPathWithinDirectory($file, $this->testRoot));
	}

	public function testRejectsSiblingDirectoryWithMatchingStringPrefix(): void
	{
		$file = $this->outsideRoot.'/outside.class.php';
		file_put_contents($file, '<?php // test');

		$this->assertFalse(McpHandler::isPathWithinDirectory($file, $this->testRoot));
	}

	public function testRejectsSymlinkEscapingToolsDirectory(): void
	{
		$outsideFile = $this->outsideRoot.'/outside.class.php';
		file_put_contents($outsideFile, '<?php // test');
		$link = $this->testRoot.'/linked.class.php';

		if (!function_exists('symlink') || !@symlink($outsideFile, $link)) {
			$this->markTestSkipped('Symlinks are not available in this test environment.');
		}

		$this->assertFalse(McpHandler::isPathWithinDirectory($link, $this->testRoot));
	}
}
