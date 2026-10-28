<?php
define('DOL_DOCUMENT_ROOT', __DIR__);
$GLOBALS['dolglobals'] = ['MAIN_INFO_SOCIETE_NOM' => 'ACME'];
function dol_syslog($m, $l = 0) { $GLOBALS['syslog'][] = $m; }
function getDolGlobalString($k, $d = '') { return $GLOBALS['dolglobals'][$k] ?? $d; }
function getDolGlobalInt($k, $d = 0) { return (int) ($GLOBALS['dolglobals'][$k] ?? $d); }
function dol_strtolower($s) { return strtolower($s); }
function dol_html_entity_decode($s, $f) { return html_entity_decode($s, $f); }
function dol_string_nounprintableascii($s, $x = 1) { return preg_replace('/[\x00-\x1F\x7F]/', '', $s); }
class User { public $id = 1; public $login = 'u'; function hasRight($a, $b = '', $c = '') { return true; } }
class Conf { public $currency = 'EUR'; public $global; function __construct() { $this->global = new stdClass; } }
class HookManager { public $resArray = []; function __construct($db = null) {} function initHooks($a) {} function executeHooks($a, &$b, &$c, &$d) { return 0; } }
$hookmanager = new HookManager();
require DOL_DOCUMENT_ROOT.'/ai/class/mcp_protocol.class.php';
require DOL_DOCUMENT_ROOT.'/ai/class/mcpauth.class.php';
$fail = 0;
function ok($cond, $label) { global $fail; echo ($cond ? "PASS" : "FAIL")." - $label\n"; if (!$cond) $fail++; }
function t($f) { try { return $f(); } catch (\Throwable $e) { return ['__thrown' => get_class($e)]; } }
function srv() { return new MCPServer(new stdClass, new Conf, new User); }
function req($method, $params = null, $id = 1) { $r = ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method]; if ($params !== null) $r['params'] = $params; return $r; }

$res = t(fn() => srv()->handleRequest(req('tools/list')));
$names = array_column($res['result']['tools'] ?? [], 'name');
ok(in_array('api_products_stock', $names, true), 'tools/list exposes the business tool');
ok(!array_intersect(['respond_to_user','ask_for_confirmation','ask_for_clarification','reject_general_question'], $names), 'tools/list hides system tools');
$GLOBALS['dolglobals']['AI_MCP_SERVER_ALLOWED_TOOLS'] = 'NONE';
$res = t(fn() => srv()->handleRequest(req('tools/list')));
ok(($res['result']['tools'] ?? null) === [], 'allow-list NONE => empty tools/list');
unset($GLOBALS['dolglobals']['AI_MCP_SERVER_ALLOWED_TOOLS']);
$h = new McpHandler(new stdClass, new User, new Conf, McpHandler::CTX_ASSISTANT); $h->loadTools();
ok(in_array('respond_to_user', array_column($h->getToolsSchema(), 'name'), true), 'assistant context keeps system tools');
$res = t(fn() => srv()->handleRequest(req('tools/call', ['name' => 'respond_to_user', 'arguments' => ['message' => 'hi']])));
ok(($res['result']['isError'] ?? false) === true, 'tools/call on a system tool is refused');
$res = t(fn() => srv()->handleRequest(array_merge(req('tools/list'), ['params' => 'x'])));
ok(($res['error']['code'] ?? 0) === -32602, 'scalar params => -32602 (got '.json_encode($res).')');
$res = t(fn() => srv()->handleRequest(req('tools/call', ['name' => ['a'], 'arguments' => []])));
ok(($res['error']['code'] ?? 0) === -32602, 'tools/call name array => -32602');
$res = t(fn() => srv()->handleRequest(req('tools/call', ['name' => 'api_products_stock', 'arguments' => 'str'])));
ok(($res['error']['code'] ?? 0) === -32602, 'tools/call arguments string => -32602');
$res = t(fn() => srv()->handleRequest(req('tools/call', ['name' => 'api_products_stock', 'arguments' => ['id' => 3]])));
ok(($res['result']['isError'] ?? null) === false, 'valid tools/call still works');
$res = t(fn() => srv()->handleRequest(req('resources/read', ['uri' => ['x']])));
ok(($res['error']['code'] ?? 0) === -32602, 'resources/read uri array => -32602');
$res = t(fn() => srv()->handleRequest(req('prompts/get', ['name' => 'inventory_health', 'arguments' => ['product_name' => ['x']]])));
ok(isset($res['result']['messages']), 'prompts/get with array product_name does not fatal');
$res = t(fn() => srv()->handleRequest(req('resources/read', ['uri' => 'dolibarr://nope<script>'])));
ok(($res['error']['code'] ?? 0) === -32602 && strpos(json_encode($res), 'script') === false, 'unknown resource => -32602 without echo');
$res = t(fn() => srv()->handleRequest(req('prompts/get', ['name' => 'nope'])));
ok(($res['error']['code'] ?? 0) === -32602, 'unknown prompt => -32602');
$res = t(fn() => srv()->handleRequest(req('prompts/get', ['name' => 'inventory_health', 'arguments' => ['product_name' => '0']])));
ok(($res['result']['messages'] ?? null) && array_column($res['result']['messages'], 'role') === ['user', 'user'], 'prompt uses only MCP roles');
$txt = json_encode($res);
ok(strpos($txt, 'analyze_stock_forecast') === false && strpos($txt, 'api_products_stock') !== false, 'prompt references real tools');
$res = t(fn() => srv()->handleRequest(req('resources/read', ['uri' => 'dolibarr://company/info'])));
ok(strpos($res['result']['contents'][0]['text'] ?? '', 'ACME') !== false, 'company info via getDolGlobalString');
$res = t(fn() => srv()->handleRequest(req('tools/call', ['name' => 'api_bad_utf8', 'arguments' => []])));
ok(is_string($res['result']['content'][0]['text'] ?? null) && $res['result']['content'][0]['text'] !== '', 'invalid UTF-8 result still yields text');
$res = t(fn() => srv()->handleRequest(req('tools/call', ['name' => 'api_demo_write', 'arguments' => ['requestState' => ['x']]])));
ok(strpos($res['result']['content'][0]['text'] ?? '', 'Invalid confirmation') !== false, 'array requestState refused');
$res = srv()->handleRequest(['id' => 1]);
ok($res['error']['code'] === -32600 && $res['id'] === 1, 'Invalid Request keeps its id');
ok(srv()->handleRequest(req('no/such'))['error']['code'] === -32601, 'unknown method');
ok(srv()->handleRequest(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']) === null, 'notification => null');
ok(srv()->handleRequest(req('initialize', []))['result']['resultType'] === 'complete', 'initialize ok');
$res = srv()->handleRequest(req('tools/call', ['name' => 'no_such_tool_zzz', 'arguments' => []]));
ok($res['result']['isError'] === true && strpos($res['result']['content'][0]['text'], 'no_such_tool_zzz') !== false, 'failing tool still reported in result');
$a = new McpAuth(new stdClass);
ok(t(fn() => $a->getCredential([], ['key' => ['x']])) === '', 'array ?key[]= is not a credential');
ok(t(fn() => $a->getCredential(['HTTP_AUTHORIZATION' => ['x']], [])) === '', 'array Authorization ignored');
ok($a->getCredential(['HTTP_AUTHORIZATION' => 'Bearer abc123'], []) === 'abc123', 'Bearer still works');
ok($a->getCredential([], ['api_key' => 'k1']) === 'k1', 'query key still works');
echo $fail ? "\n$fail FAILED\n" : "\nALL PASSED\n";
exit($fail ? 1 : 0);
