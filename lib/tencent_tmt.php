<?php
declare(strict_types=1);

/**
 * Tencent Cloud Machine Translation client.
 * Uses TC3-HMAC-SHA256 signing for the TextTranslate action.
 *
 * Free tier: 5,000,000 characters/month for the first 12 months.
 */

function tmt_credentials(): array
{
    global $config;
    $secretId = (string) ($config['tencent']['secret_id'] ?? '');
    $secretKey = (string) ($config['tencent']['secret_key'] ?? '');
    $region = (string) ($config['tencent']['tmt_region'] ?? 'ap-guangzhou');
    return ['secret_id' => $secretId, 'secret_key' => $secretKey, 'region' => $region];
}

function tmt_configured(): bool
{
    $creds = tmt_credentials();
    return $creds['secret_id'] !== '' && $creds['secret_key'] !== '';
}

/**
 * Translate text using Tencent TextTranslate API.
 *
 * @param string $text The source text. Single call must be <= 6000 bytes
 *                     (Tencent docs); chunk in caller if longer.
 * @param string $sourceLang e.g. 'en', 'zh', 'auto'
 * @param string $targetLang e.g. 'zh'
 * @return array ['ok' => bool, 'text' => string, 'error' => string]
 */
function tmt_translate(string $text, string $sourceLang = 'en', string $targetLang = 'zh'): array
{
    if (trim($text) === '') {
        return ['ok' => true, 'text' => '', 'error' => ''];
    }
    $creds = tmt_credentials();
    if ($creds['secret_id'] === '' || $creds['secret_key'] === '') {
        return ['ok' => false, 'text' => '', 'error' => 'tencent_tmt_not_configured'];
    }

    $service = 'tmt';
    $host = 'tmt.tencentcloudapi.com';
    $action = 'TextTranslate';
    $version = '2018-03-21';
    $region = $creds['region'];
    $algorithm = 'TC3-HMAC-SHA256';
    $timestamp = time();
    $date = gmdate('Y-m-d', $timestamp);

    $payload = json_encode([
        'SourceText' => $text,
        'Source' => $sourceLang,
        'Target' => $targetLang,
        'ProjectId' => 0,
    ], JSON_UNESCAPED_UNICODE);

    // 1. canonical request
    $httpRequestMethod = 'POST';
    $canonicalUri = '/';
    $canonicalQueryString = '';
    $canonicalHeaders = "content-type:application/json; charset=utf-8\nhost:{$host}\nx-tc-action:" . strtolower($action) . "\n";
    $signedHeaders = 'content-type;host;x-tc-action';
    $hashedRequestPayload = hash('SHA256', $payload);
    $canonicalRequest = $httpRequestMethod . "\n"
        . $canonicalUri . "\n"
        . $canonicalQueryString . "\n"
        . $canonicalHeaders . "\n"
        . $signedHeaders . "\n"
        . $hashedRequestPayload;

    // 2. string to sign
    $credentialScope = $date . '/' . $service . '/tc3_request';
    $hashedCanonicalRequest = hash('SHA256', $canonicalRequest);
    $stringToSign = $algorithm . "\n"
        . $timestamp . "\n"
        . $credentialScope . "\n"
        . $hashedCanonicalRequest;

    // 3. signature
    $secretDate = hash_hmac('SHA256', $date, 'TC3' . $creds['secret_key'], true);
    $secretService = hash_hmac('SHA256', $service, $secretDate, true);
    $secretSigning = hash_hmac('SHA256', 'tc3_request', $secretService, true);
    $signature = hash_hmac('SHA256', $stringToSign, $secretSigning);

    // 4. authorization header
    $authorization = $algorithm
        . ' Credential=' . $creds['secret_id'] . '/' . $credentialScope
        . ', SignedHeaders=' . $signedHeaders
        . ', Signature=' . $signature;

    $headers = [
        'Authorization: ' . $authorization,
        'Content-Type: application/json; charset=utf-8',
        'Host: ' . $host,
        'X-TC-Action: ' . $action,
        'X-TC-Timestamp: ' . $timestamp,
        'X-TC-Version: ' . $version,
        'X-TC-Region: ' . $region,
    ];

    $ch = curl_init('https://' . $host . '/');
    curl_setopt_array($ch, array_replace([
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_USERAGENT => 'GIR Tencent TMT Client',
    ], http_ssl_options()));

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        return ['ok' => false, 'text' => '', 'error' => 'curl_error: ' . $error];
    }
    if ($http < 200 || $http >= 300) {
        return ['ok' => false, 'text' => '', 'error' => 'http_' . $http . ': ' . substr((string) $response, 0, 200)];
    }
    $data = json_decode((string) $response, true);
    if (!is_array($data) || !isset($data['Response'])) {
        return ['ok' => false, 'text' => '', 'error' => 'bad_response'];
    }
    if (isset($data['Response']['Error'])) {
        return [
            'ok' => false,
            'text' => '',
            'error' => 'tencent_error: ' . ($data['Response']['Error']['Code'] ?? '') . ' ' . ($data['Response']['Error']['Message'] ?? ''),
        ];
    }
    $translated = (string) ($data['Response']['TargetText'] ?? '');
    return ['ok' => true, 'text' => $translated, 'error' => ''];
}

/**
 * Translate a long Markdown text by chunking on blank-line boundaries.
 * Tencent single call cap is 6000 bytes; we chunk well under that.
 *
 * Markdown protection: code blocks, inline code, URLs, image refs and
 * link refs are extracted into placeholders before translation and
 * restored after, so the API does not mangle Markdown syntax.
 */
function tmt_translate_long(string $text, string $sourceLang = 'en', string $targetLang = 'zh', int $maxChunkBytes = 4500): array
{
    $text = (string) $text;
    if (trim($text) === '') {
        return ['ok' => true, 'text' => '', 'error' => ''];
    }

    list($masked, $tokens) = tmt_mask_markdown($text);

    if (strlen($masked) <= $maxChunkBytes) {
        $result = tmt_translate($masked, $sourceLang, $targetLang);
        if (!$result['ok']) {
            return $result;
        }
        return ['ok' => true, 'text' => tmt_unmask_markdown($result['text'], $tokens), 'error' => ''];
    }

    // Split on double newlines (paragraph boundaries) to keep markdown structure.
    $paragraphs = preg_split('/(\n\s*\n)/', $masked, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($paragraphs === false) {
        $clipped = substr($masked, 0, $maxChunkBytes);
        $result = tmt_translate($clipped, $sourceLang, $targetLang);
        if (!$result['ok']) {
            return $result;
        }
        return ['ok' => true, 'text' => tmt_unmask_markdown($result['text'], $tokens), 'error' => ''];
    }

    $chunks = [];
    $current = '';
    foreach ($paragraphs as $part) {
        if (strlen($current) + strlen($part) > $maxChunkBytes && $current !== '') {
            $chunks[] = $current;
            $current = $part;
        } else {
            $current .= $part;
        }
    }
    if ($current !== '') {
        $chunks[] = $current;
    }

    $out = '';
    foreach ($chunks as $chunk) {
        if (trim($chunk) === '') {
            $out .= $chunk;
            continue;
        }
        if (strlen($chunk) > 6000) {
            $chunk = substr($chunk, 0, 6000);
        }
        $result = tmt_translate($chunk, $sourceLang, $targetLang);
        if (!$result['ok']) {
            return $result;
        }
        $out .= $result['text'];
    }
    return ['ok' => true, 'text' => tmt_unmask_markdown($out, $tokens), 'error' => ''];
}

/**
 * Replace Markdown structural pieces with opaque placeholders so the
 * MT engine does not translate or reformat them.
 *
 * Protected pieces (in order):
 *   1. fenced code blocks (``` ... ```)
 *   2. HTML attribute strings inside <... >
 *   3. inline code (`x`)
 *   4. image and link with explicit URL: ![alt](url) and [text](url)
 *   5. raw URLs http/https
 *
 * Placeholders look like  ZZ7K0123ZZ  — letters that survive Tencent
 * unchanged (verified) and are unlikely to appear in real text.
 */
function tmt_mask_markdown(string $text): array
{
    $tokens = [];
    $counter = 0;
    $makeToken = static function () use (&$counter): string {
        // Use letters + numbers, no special chars; >= 8 chars to avoid collisions.
        return 'ZZ7K' . str_pad((string) $counter++, 4, '0', STR_PAD_LEFT) . 'ZZ';
    };

    // 1. fenced code blocks
    $text = preg_replace_callback('/```[\s\S]*?```/', function ($m) use (&$tokens, $makeToken) {
        $tok = $makeToken();
        $tokens[$tok] = $m[0];
        return ' ' . $tok . ' ';
    }, $text);

    // 2. HTML tags (kept whole, attributes have URLs and quoted strings the
    //    MT would mangle). Only opening/self-closing/closing tags.
    $text = preg_replace_callback('/<\/?[a-zA-Z][a-zA-Z0-9-]*(?:\s+[^>]*)?\/?>/', function ($m) use (&$tokens, $makeToken) {
        $tok = $makeToken();
        $tokens[$tok] = $m[0];
        return ' ' . $tok . ' ';
    }, $text);

    // 3. inline code
    $text = preg_replace_callback('/`[^`\n]+`/', function ($m) use (&$tokens, $makeToken) {
        $tok = $makeToken();
        $tokens[$tok] = $m[0];
        return ' ' . $tok . ' ';
    }, $text);

    // 4. image markdown ![alt](url)
    $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/', function ($m) use (&$tokens, $makeToken) {
        $altTok = $makeToken();
        $urlTok = $makeToken();
        $tokens[$altTok] = $m[1];
        $tokens[$urlTok] = '(' . $m[2] . (isset($m[3]) ? $m[3] : '') . ')';
        return '![' . $altTok . ']' . $urlTok;
    }, $text);

    // 5. link markdown [text](url)
    $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/', function ($m) use (&$tokens, $makeToken) {
        $urlTok = $makeToken();
        $tokens[$urlTok] = '(' . $m[2] . (isset($m[3]) ? $m[3] : '') . ')';
        // text part stays translatable
        return '[' . $m[1] . ']' . $urlTok;
    }, $text);

    // 6. raw URLs
    $text = preg_replace_callback('/https?:\/\/[^\s<>")]+/', function ($m) use (&$tokens, $makeToken) {
        $tok = $makeToken();
        $tokens[$tok] = $m[0];
        return ' ' . $tok . ' ';
    }, $text);

    return [$text, $tokens];
}

function tmt_unmask_markdown(string $text, array $tokens): string
{
    if (!$tokens) return $text;
    // Translation engines may insert spaces inside our token. Build a flexible
    // regex per token that matches the same letters/digits with optional
    // whitespace between.
    foreach ($tokens as $tok => $original) {
        $chars = preg_split('//u', $tok, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $pattern = '';
        foreach ($chars as $i => $ch) {
            $pattern .= preg_quote($ch, '/');
            if ($i + 1 < count($chars)) {
                $pattern .= '\s*';
            }
        }
        $text = preg_replace('/' . $pattern . '/i', $original !== null ? $original : '', $text, 1);
        if ($text === null) {
            $text = '';
            break;
        }
    }
    // Replace fullwidth parens that the MT engine may have introduced around
    // restored tokens like (https://...).
    $text = preg_replace_callback('/[（(]([^()）]+)[)）]/u', function ($m) {
        return '(' . $m[1] . ')';
    }, $text);
    // Re-tighten Markdown structural pieces the MT may have loosened:
    //   "! [alt]"  -> "![alt]"
    $text = preg_replace('/!\s+\[/u', '![', $text);
    //   "[text] (url)"  -> "[text](url)"
    $text = preg_replace('/(\]\s+)(\()/u', ']$2', $text);
    //   "##标题" -> "## 标题"  (Tencent often eats the space after #)
    $text = preg_replace('/^(#{1,6})([^\s#])/mu', '$1 $2', $text);
    // Trim trailing spaces before newlines we may have introduced.
    $text = preg_replace('/[ \t]+(\n)/', '$1', $text);
    return $text;
}
