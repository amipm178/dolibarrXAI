<?php
/* Copyright (C) 2026	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2026	Nick Fragoulis
 * Copyright (C) 2026	MDW						<mdeweerd@users.noreply.github.com>
 * Copyright (C) 2026	Jose Martinez			<jose.martinez@pichinov.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * or see https://www.gnu.org/
 */

/**
 * \file    htdocs/ai/server/mcp_server.php
 * \ingroup ai
 * \brief   File of the MCP Server service
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', 1);
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', 1);
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', 1);
}
define('NOLOGIN', 1);

require '../../main.inc.php';
/**
 * @var DoliDB $db
 * @var Conf $conf
 * @var User $user
 */
require_once DOL_DOCUMENT_ROOT . '/ai/class/mcp_protocol.class.php';
require_once DOL_DOCUMENT_ROOT . '/ai/class/mcpauth.class.php';

while (ob_get_level()) {
	ob_end_clean();
}

// Security check (a test on api_key is also done later)
if (!isModEnabled('ai') || !getDolGlobalString('AI_MCP_ENABLED')) {
	http_response_code(503);
	echo json_encode([
		"jsonrpc" => "2.0",
		"error" => ["code" => -32000, "message" => "MCP Server Disabled"]
	]);
	exit;
}


/*
 * View
 */

// Headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Request headers, used by the transport-header validation further down.
$headers = function_exists('getallheaders') ? getallheaders() : [];
$headers = array_change_key_case($headers, CASE_LOWER);

$mcpAuth = new McpAuth($db);

if ($mcpAuth->authenticate() < 0) {
	if ($mcpAuth->httpcode == 401) {
		// RFC 6750 section 3: a rejected Bearer request must say what it wanted.
		header('WWW-Authenticate: ' . $mcpAuth->getWwwAuthenticateHeader());
	}

	http_response_code($mcpAuth->httpcode);
	echo json_encode([
		"jsonrpc" => "2.0",
		"error" => ["code" => -32000, "message" => $mcpAuth->error]
	]);
	exit;
}


// Determine the user the request runs as. An individual API key names its own
// owner, already loaded and checked by McpAuth; the shared server key falls
// back to the AI_MCP_USER_ID service user.
$serviceUser = $mcpAuth->user;

if ($serviceUser === null) {
	$userId = getDolGlobalInt('AI_MCP_USER_ID');

	if ($userId <= 0) {
		http_response_code(503);
		echo json_encode([
			"jsonrpc" => "2.0",
			"error" => ["code" => -32000, "message" => "MCP Server Misconfigured: authenticate with a user API key, or set AI_MCP_USER_ID for the shared server key"]
		]);
		exit;
	}

	$serviceUser = new User($db);
	if ($serviceUser->fetch($userId) <= 0) {
		http_response_code(503);
		echo json_encode([
			"jsonrpc" => "2.0",
			"error" => ["code" => -32000, "message" => "MCP Service User is unavailable"]
		]);
		exit;
	}
	if (empty($serviceUser->statut)) {
		dol_syslog('[MCP Server] Shared-key service user is disabled; refusing request.', LOG_NOTICE);
		http_response_code(503);
		echo json_encode([
			"jsonrpc" => "2.0",
			"error" => ["code" => -32000, "message" => "MCP Service User is disabled"]
		]);
		exit;
	}
	$serviceUser->loadRights();
	if (!$serviceUser->hasRight('ai', 'assistant', 'use')) {
		dol_syslog('[MCP Server] Shared-key service user lacks ai/assistant/use permission; refusing request.', LOG_NOTICE);
		http_response_code(403);
		echo json_encode([
			"jsonrpc" => "2.0",
			"error" => ["code" => -32000, "message" => "MCP Service User is not allowed to use the AI assistant"]
		]);
		exit;
	}
}

// Promote the user to the global $user so MCP tools that legitimately rely on
// the `global $user` pattern (Dolibarr core convention) see an authenticated
// user. Without this, there is no PHP web session in HTTP MCP context and any
// tool reading `global $user` would treat the request as unauthenticated even
// though authentication succeeded above.
global $user;
$user = $serviceUser;

// Load the AI request log helper so we can persist tools/call invocations to
// llx_ai_request_log (same table the AI Assistant web UI logs to). This gives
// administrators a single place to audit external MCP client activity.
require_once DOL_DOCUMENT_ROOT . '/ai/lib/ai.lib.php';
require_once DOL_DOCUMENT_ROOT . '/ai/class/mcplogsanitizer.class.php';

/**
 * Persist an MCP tools/call invocation to the llx_ai_request_log table.
 * Mirrors what parse_intent.php does for the web AI Assistant.
 * Only tools/call is logged -- lifecycle methods (initialize, ping,
 * notifications/initialized, tools/list, ...) are skipped to avoid noise.
 *
 * @param array<string,mixed>	$req		The JSON-RPC request
 * @param ?mixed				$resp		The JSON-RPC response (null for notifications)
 * @param float					$tStart		microtime(true) captured before processing
 * @return void								No return value, only logs the request
 */
function mcp_log_request(array $req, $resp, float $tStart): void
{
	global $db, $serviceUser;

	$method = isset($req['method']) ? (string) $req['method'] : '';
	if ($method !== 'tools/call' || !function_exists('ai_log_request')) {
		return;
	}

	$params   = isset($req['params']) && is_array($req['params']) ? $req['params'] : [];
	$toolName = isset($params['name']) && is_string($params['name']) ? $params['name'] : '';
	$toolArgs = isset($params['arguments']) ? $params['arguments'] : [];

	$argumentKeys = McpLogSanitizer::argumentKeys($toolArgs);
	$safeToolName = preg_replace('/[\\x00-\\x1F\\x7F]/', '', substr($toolName, 0, 255));
	$query = '[MCP] ' . $safeToolName . ' argument keys: ' . implode(',', $argumentKeys);

	// Keep audit metadata, not customer/business values or tool result content.
	$responseShape = array('tool' => $safeToolName, 'arguments' => array('keys' => $argumentKeys));

	$status = 'Success';
	$errorMsg = '';
	if (is_array($resp) && isset($resp['error'])) {
		$status = 'Error';
		$errorMsg = 'MCP request failed; response details redacted';
	} elseif (is_array($resp) && !empty($resp['result']['isError'])) {
		$status = 'Error';
		$errorMsg = 'MCP tool returned an error; response details redacted';
	}

	$safeRequest = json_encode(McpLogSanitizer::sanitizeRequest($req));
	$rawResStr = json_encode(McpLogSanitizer::sanitizeResponse($resp));

	ai_log_request(
		$db,
		$serviceUser,
		$query,
		$responseShape,
		'mcp',
		microtime(true) - $tStart,
		1.0,
		$status,
		$errorMsg,
		$safeRequest,
		$rawResStr
	);
}

// Request handling
try {
	$tStart = microtime(true);

	// Basic payload size limit. Return the correct transport status and
	// JSON-RPC error directly rather than misreporting these as parse failures.
	$rawInput = file_get_contents('php://input');
	if ($rawInput === false) {
		http_response_code(400);
		echo json_encode(array("jsonrpc" => "2.0", "id" => null, "error" => array("code" => -32700, "message" => "Parse error")));
		exit;
	}
	if (strlen($rawInput) > 1024 * 1024) {
		http_response_code(413);
		echo json_encode(array("jsonrpc" => "2.0", "id" => null, "error" => array("code" => -32000, "message" => "Request too large")));
		exit;
	}

	$request = json_decode($rawInput, true);
	if (json_last_error() !== JSON_ERROR_NONE) {
		http_response_code(400);
		echo json_encode(array("jsonrpc" => "2.0", "id" => null, "error" => array("code" => -32700, "message" => "Parse error")));
		exit;
	}
	if (!is_array($request)) {
		http_response_code(400);
		echo json_encode(array("jsonrpc" => "2.0", "id" => null, "error" => array("code" => -32600, "message" => "Invalid Request")));
		exit;
	}

	$server = new MCPServer($db, $conf, $serviceUser);

	// Preserve the top-level JSON shape: json_decode(..., true) maps both an
	// empty JSON object ({}) and an empty JSON array ([]) to an empty PHP array.
	// The raw first non-whitespace character reliably distinguishes batch input.
	$trimmedInput = ltrim($rawInput);
	$isBatch = ($trimmedInput !== '' && $trimmedInput[0] === '[');

	// Batch request handling
	if ($isBatch && is_array($request)) {
		// Limit batch size
		if (count($request) > 20) {
			http_response_code(413);
			echo json_encode([
				"jsonrpc" => "2.0",
				"error" => ["code" => -32000, "message" => "Batch too large"]
			]);
			exit;
		}

		// JSON-RPC forbids an empty batch. Return one Invalid Request object,
		// rather than the invalid empty response array [].
		if (count($request) === 0) {
			echo json_encode(["jsonrpc" => "2.0", "id" => null, "error" => ["code" => -32600, "message" => "Invalid Request"]]);
			exit;
		}

		$responses = [];

		// Transport headers describe the HTTP request, not individual batch
		// items: if a Mcp-* / MCP-Protocol-Version header disagrees with ANY
		// item, the header lies about the request and the WHOLE batch fails
		// with a single error and HTTP 400 (review finding on #40356). This
		// also keeps the rate-limiting contract simple: a proxy trusting the
		// headers never lets a mismatching batch through as 200.
		foreach ($request as $precheck) {
			if (!is_array($precheck)) {
				continue;
			}
			$headerError = $server->validateTransportHeaders($headers, $precheck);
			if ($headerError !== null || $server->getHttpStatus() !== 200) {
				http_response_code($server->getHttpStatus());
				if ($headerError === null) {
					// The offending item was a notification (no id): the error
					// response was suppressed per JSON-RPC, but the transport
					// status must still tell the truth.
					$headerError = ["jsonrpc" => "2.0", "id" => null, "error" => ["code" => -32020, "message" => "Transport header does not match a batch item"]];
				}
				echo json_encode($headerError);
				exit;
			}
		}

		// Answer to all MCP requests following the MCP protocol
		foreach ($request as $req) {
			$reqStart = microtime(true);
			if (!is_array($req)) {
				// A malformed batch member is an Invalid Request, not an item to
				// silently discard. Keep processing the remaining batch members.
				$responses[] = ["jsonrpc" => "2.0", "id" => null, "error" => ["code" => -32600, "message" => "Invalid Request"]];
				continue;
			}

			$res = $server->handleRequest($req);

			if ($res !== null) {
				$responses[] = $res;
			}

			// Log each tools/call separately so the admin log viewer shows them individually.
			mcp_log_request($req, $res, $reqStart);
		}

		// A batch made entirely of notifications has no JSON-RPC response.
		// Streamable HTTP represents that case with 202 and an empty body.
		if (count($responses) === 0) {
			http_response_code(202);
		} else {
			echo json_encode($responses);
		}
	} else {
		// Single request
		if (!is_array($request)) {
			throw new Exception("Invalid request format");
		}

		$response = $server->validateTransportHeaders($headers, $request);
		if ($response === null && $server->getHttpStatus() === 200) {
			$response = $server->handleRequest($request);
		}

		if ($server->getHttpStatus() !== 200) {
			http_response_code($server->getHttpStatus());
			if ($response === null) {
				// The offending request was a notification (no id): the error
				// response body was suppressed per JSON-RPC, but a 400 must
				// not go out empty - same handling as the batch path.
				$response = ["jsonrpc" => "2.0", "id" => null, "error" => ["code" => -32020, "message" => "Transport header does not match the request"]];
			}
		}

		// Streamable HTTP notifications have no JSON-RPC response body and use
		// 202 Accepted. Transport errors above still produce their explicit error.
		if ($response === null && $server->getHttpStatus() === 200) {
			http_response_code(202);
		}

		// Log before emitting the response so a logging failure cannot append a
		// second JSON document to an already-started HTTP response.
		mcp_log_request($request, $response, $tStart);

		if ($response !== null) {
			echo json_encode($response);
		}
	}
} catch (Throwable $e) {
	// Do not log exception messages here: they may contain user-controlled
	// input or business values. Parsing errors are handled explicitly above.
	dol_syslog('[MCP Server] Unhandled endpoint failure (' . get_class($e) . ')', LOG_ERR);

	if (!headers_sent()) {
		http_response_code(500);
	}
	echo json_encode(array(
		"jsonrpc" => "2.0",
		"id" => null,
		"error" => array(
			"code" => -32603,
			"message" => "Internal server error"
		)
	));
}
