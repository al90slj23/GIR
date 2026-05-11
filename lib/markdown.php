<?php
declare(strict_types=1);

require_once __DIR__ . '/Parsedown.php';

function markdown_rewrite_relative_urls(string $markdown, string $fullName, string $branch = 'HEAD'): string
{
    $fullName = trim($fullName);
    if ($fullName === '' || strpos($fullName, '/') === false) {
        return $markdown;
    }
    list($owner, $repo) = array_pad(explode('/', $fullName, 2), 2, '');
    if ($owner === '' || $repo === '') {
        return $markdown;
    }
    $rawBase = 'https://raw.githubusercontent.com/' . $owner . '/' . $repo . '/' . rawurlencode($branch) . '/';
    $blobBase = 'https://github.com/' . $owner . '/' . $repo . '/blob/' . rawurlencode($branch) . '/';

    $isAbsolute = static function (string $url): bool {
        $url = trim($url);
        if ($url === '') {
            return true;
        }
        if ($url[0] === '#' || $url[0] === '?') {
            return true;
        }
        if (preg_match('/^([a-z][a-z0-9+\-.]*:|\/\/)/i', $url)) {
            return true;
        }
        return false;
    };

    $normalizeRelative = static function (string $url): string {
        $url = ltrim($url, './');
        while (strpos($url, '../') === 0) {
            $url = substr($url, 3);
        }
        return $url;
    };

    $markdown = preg_replace_callback(
        '/!\[([^\]]*)\]\(\s*([^)\s]+)(\s+"[^"]*")?\s*\)/',
        function ($matches) use ($isAbsolute, $normalizeRelative, $rawBase) {
            $url = $matches[2];
            if ($isAbsolute($url)) {
                return $matches[0];
            }
            $rewritten = $rawBase . $normalizeRelative($url);
            $title = $matches[3] ?? '';
            return '![' . $matches[1] . '](' . $rewritten . $title . ')';
        },
        $markdown
    );

    $markdown = preg_replace_callback(
        '/(?<!\!)\[([^\]]+)\]\(\s*([^)\s]+)(\s+"[^"]*")?\s*\)/',
        function ($matches) use ($isAbsolute, $normalizeRelative, $blobBase, $rawBase) {
            $url = $matches[2];
            if ($isAbsolute($url)) {
                return $matches[0];
            }
            $normalized = $normalizeRelative($url);
            $base = preg_match('/\.(png|jpe?g|gif|svg|webp|ico|bmp)(\?|#|$)/i', $normalized) ? $rawBase : $blobBase;
            $rewritten = $base . $normalized;
            $title = $matches[3] ?? '';
            return '[' . $matches[1] . '](' . $rewritten . $title . ')';
        },
        $markdown
    );

    $markdown = preg_replace_callback(
        '/<img\b([^>]*?)\bsrc=(["\'])([^"\']+)\2/i',
        function ($matches) use ($isAbsolute, $normalizeRelative, $rawBase) {
            $url = $matches[3];
            if ($isAbsolute($url)) {
                return $matches[0];
            }
            return '<img' . $matches[1] . 'src=' . $matches[2] . $rawBase . $normalizeRelative($url) . $matches[2];
        },
        $markdown
    );

    return $markdown;
}

function render_markdown_html(string $markdown, string $fullName, string $branch = 'HEAD'): string
{
    if (trim($markdown) === '') {
        return '';
    }
    $rewritten = markdown_rewrite_relative_urls($markdown, $fullName, $branch);
    $parser = new Parsedown();
    $parser->setSafeMode(true);
    $parser->setBreaksEnabled(false);
    $parser->setUrlsLinked(true);
    $html = $parser->text($rewritten);

    $html = preg_replace_callback(
        '/<img\b([^>]*)>/i',
        function ($matches) {
            $attrs = $matches[1];
            $extra = '';
            if (stripos($attrs, ' loading=') === false) {
                $extra .= ' loading="lazy"';
            }
            if (stripos($attrs, ' referrerpolicy=') === false) {
                $extra .= ' referrerpolicy="no-referrer"';
            }
            return '<img' . $attrs . $extra . '>';
        },
        $html
    );

    $html = preg_replace_callback(
        '/<a\b([^>]*)>/i',
        function ($matches) {
            $attrs = $matches[1];
            $extra = '';
            if (stripos($attrs, ' target=') === false) {
                $extra .= ' target="_blank"';
            }
            if (stripos($attrs, ' rel=') === false) {
                $extra .= ' rel="noreferrer nofollow"';
            }
            return '<a' . $attrs . $extra . '>';
        },
        $html
    );

    return $html;
}

function detect_text_language(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return 'en';
    }
    $cjkCount = preg_match_all('/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}\x{20000}-\x{2A6DF}]/u', $text);
    if (!is_int($cjkCount)) {
        $cjkCount = 0;
    }
    $totalLetters = preg_match_all('/\p{L}/u', $text);
    if (!is_int($totalLetters) || $totalLetters === 0) {
        return 'en';
    }
    $ratio = $cjkCount / max(1, $totalLetters);
    return $ratio >= 0.2 ? 'zh' : 'en';
}
