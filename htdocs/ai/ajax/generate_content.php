<?php
/* Copyright (C) 2008-2025  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2016  Regis Houssin           <regis.houssin@inodbox.com>
 * Copyright (C) 2012       J. Fernando Lagrange    <fernando@demo-tic.org>
 * Copyright (C) 2015       Raphaël Doursenaud      <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2023       Eric Seigne      		<eric.seigne@cap-rel.fr>
 * Copyright (C) 2024-2026  Frédéric France         <frederic.france@free.fr>
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
 *	\file			htdocs/ai/ajax/generate_content.php
 *  \brief          Ajax page to generate content
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1'); // Disables token renewal
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}
// The CSRF token is carried in the raw JSON body/header and must be checked below.
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1');
}

require '../../main.inc.php';

require_once DOL_DOCUMENT_ROOT.'/ai/class/ai.class.php';
require_once DOL_DOCUMENT_ROOT.'/ai/class/contentnormalizer.class.php';
require_once DOL_DOCUMENT_ROOT.'/ai/lib/ai.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

if (!isModEnabled('ai')) {
	accessforbidden('Module AI not enabled');
}

// This endpoint can incur provider costs, so reject cross-site requests.
aiCheckCsrfToken('ai/ajax/generate_content.php');


/*
 * View
 */

top_httphead();

//get data from AJAX
$rawData = file_get_contents('php://input', false, null, 0, 1024 * 1024 + 1);
if ($rawData === false || dol_strlen($rawData) > 1024 * 1024) {
	http_response_code(413);
	print 'Error : request too large.';
	exit;
}
$jsonData = json_decode($rawData, true);

if (!is_array($jsonData) || !isset($jsonData['instructions']) || !is_string($jsonData['instructions'])) {
	http_response_code(400);
	print 'Error : invalid JSON request.';
	exit;
}
$ai = new Ai($db);

// Get parameters
// Default value is 'textgeneration'.
// Can also be 'textgeneration', 'textgenerationemail', 'textgenerationwebpage', 'textrephraser', 'textspellchecker', 'imagegeneration', 'videogeneration', ...
if ((array_key_exists('function', $jsonData) && !is_string($jsonData['function']))
	|| (array_key_exists('format', $jsonData) && !is_string($jsonData['format']))) {
	http_response_code(400);
	print 'Error : invalid function or format.';
	exit;
}

$function = empty($jsonData['function']) ? 'textgeneration' : $jsonData['function'];

$format = empty($jsonData['format']) ? '' : $jsonData['format'];						// Can be '' for text, 'html', ...

$availableFeatures = getListOfAIFeatures();

if (!array_key_exists($function, $availableFeatures) || !in_array($format, array('', 'text', 'html'), true)) {
	http_response_code(400);
	print 'Error : invalid function or format.';
	exit;
}

if (in_array($function, array('videogeneration', 'audiogeneration'), true)) {
	http_response_code(501);
	print 'Error : this AI generation feature is not implemented yet.';
	exit;
}

if (!in_array($availableFeatures[$function]['status'], array('dolibarr', 'experimental'), true)
	&& !in_array($function, array('textgeneration', 'imagegeneration'), true)) {
	http_response_code(400);
	print 'Error : invalid function or format.';
	exit;
}

if ($format == "html") {
	$instructions = $jsonData['instructions'];
} else {
	$instructions = dol_string_nohtmltag($jsonData['instructions'], 2, 'UTF-8');
}


if (empty($instructions)) {
	http_response_code(400);
	print "Error : empty message.";
	exit;
}

// Keep request content out of logs; it can contain personal or confidential data.
dol_syslog("generate_content: function=".$function." format=".$format." instruction_length=".dol_strlen($instructions));

$generatedContent = $ai->generateContent($instructions, 'auto', $function, $format);

if (!is_array($generatedContent) && $function == 'textgenerationwebpage' && $format == 'html') {
	$generatedContent = AiContentNormalizer::normalizeWebpage($generatedContent);
}

if (is_null($generatedContent) || (is_array($generatedContent) && !empty($generatedContent['error']))) {
	// Output error
	if (!empty($generatedContent['code']) && $generatedContent['code'] == 429) {
		http_response_code($generatedContent['code']);
		print "Quota or allowed period exceeded. Retry Later !";
	} elseif (!empty($generatedContent['code']) && $generatedContent['code'] >= 400) {
		http_response_code($generatedContent['code']);
		print "Error : " . dolPrintText((string) $generatedContent['message']);
		print '<br><a href="'.DOL_MAIN_URL_ROOT.'/ai/admin/setup.php">'.$langs->trans('ErrorGoToModuleSetup').'</a>';
	} elseif (!empty($generatedContent['message'])) {
		if (!empty($generatedContent['code'])) {
			http_response_code($generatedContent['code']);
		}
		print "Error returned by API call: " . dolPrintText((string) $generatedContent['message']);
	} else {
		if (!empty($generatedContent['code'])) {
			http_response_code($generatedContent['code']);
		}
		print "Error API returned no answer";
	}
} else {
	if ($function == 'textgenerationemail' || $function == 'textgenerationwebpage') {
		if ($format == 'html') {
			print dolPrintHTML($generatedContent);	// Note that common HTML tags are NOT escaped (but a sanitization is done)
		} else {
			// We must not use dolPrintText because dolPrintText format data including accent in htmlentities for a HTML output. We need a non formatted output.
			//print dol_string_onlythesehtmltags('"How are you" is a French expression and this is an HTML <a> tag', 1, 1, 1);
			print dol_string_onlythesehtmltags($generatedContent, 1, 1, 1);
			//print dol_string_nohtmltag($generatedContent);
		}
	} elseif ($function == 'imagegeneration') {
		if (!preg_match('/^(https:\/\/[^\s"\'<>]+|data:image\/(png|jpeg|webp);base64,[A-Za-z0-9+\/=]+)$/', $generatedContent)) {
			http_response_code(502);
			print 'Error : invalid image response.';
		} else {
			print $generatedContent;
		}
	} else {
		// Default case 'textgeneration', 'texttranslation', 'textsummarize'
		if ($format == "html") {
			print dolPrintHTML($generatedContent);
		} else {
			// We must not use dolPrintText because dolPrintText format data including accent in htmlentities for a HTML output. We need a non formatted output.
			//print dol_string_onlythesehtmltags('"How are you" is a French expression and this is an HTML <a> tag', 1, 1, 1);
			print dol_string_onlythesehtmltags($generatedContent, 1, 1, 1);
		}
	}
}
