<?php
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function normalizeQuery(string $query): string
{
    return trim(preg_replace('/\s+/', ' ', $query));
}

function buildSearchUrl(string $query): string
{
    return 'result.php?q=' . urlencode($query);
}

function buildImageUrl(string $query): string
{
    return 'images.php?q=' . urlencode($query);
}

function buildNewsUrl(string $query): string
{
    return 'news.php?q=' . urlencode($query);
}

function buildVideoUrl(string $query): string
{
    return 'videos.php?q=' . urlencode($query);
}

function buildMapUrl(string $query): string
{
    return 'maps.php?q=' . urlencode($query);
}

function externalSearchUrl(string $baseUrl, string $query): string
{
    return $baseUrl . urlencode($query);
}

function fetchPage(string $url)
{
    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36',
        ]);
        $content = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return $content !== false && $statusCode < 400 ? $content : false;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36',
        ],
    ]);

    return @file_get_contents($url, false, $context);
}

function fallbackThumbnail(string $domain): string
{
    return 'https://www.google.com/s2/favicons?domain=' . urlencode($domain) . '&sz=256';
}

function absoluteUrl(string $url, string $baseUrl): string
{
    if (preg_match('/^https?:\/\//i', $url)) {
        return $url;
    }

    if (str_starts_with($url, '//')) {
        return 'https:' . $url;
    }

    $parts = parse_url($baseUrl);

    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return $url;
    }

    $base = $parts['scheme'] . '://' . $parts['host'];

    if (str_starts_with($url, '/')) {
        return $base . $url;
    }

    $path = isset($parts['path']) ? preg_replace('/\/[^\/]*$/', '/', $parts['path']) : '/';

    return $base . $path . $url;
}

function extractMetaThumbnail(string $html, string $baseUrl): ?string
{
    $patterns = [
        '/<meta[^>]+(?:property|name)=["\']og:image(?::secure_url)?["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i',
        '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\']og:image(?::secure_url)?["\'][^>]*>/i',
        '/<meta[^>]+(?:property|name)=["\']twitter:image(?::src)?["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i',
        '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\']twitter:image(?::src)?["\'][^>]*>/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $matches)) {
            $thumbnail = html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');

            if ($thumbnail !== '') {
                return absoluteUrl($thumbnail, $baseUrl);
            }
        }
    }

    return null;
}

function cleanThumbnailUrl(string $url, string $baseUrl): ?string
{
    $url = html_entity_decode(trim($url), ENT_QUOTES, 'UTF-8');
    $url = stripcslashes($url);
    $url = str_replace(['\\u0026', '\u0026'], '&', $url);

    if ($url === '' || str_starts_with($url, 'data:')) {
        return null;
    }

    return absoluteUrl($url, $baseUrl);
}

function extractSearchThumbnail(string $html, string $baseUrl): ?string
{
    $patterns = [
        '/https?:\\\\?\/\\\\?\/i\.ytimg\.com\\\\?\/vi\\\\?\/[^"\'\\\\]+\\\\?\/(?:hqdefault|mqdefault|maxresdefault)\.jpg/i',
        '/https?:\\\\?\/\\\\?\/i\.vimeocdn\.com\\\\?\/video\\\\?\/[^"\'\\\\]+\.(?:jpg|webp)/i',
        '/https?:\\\\?\/\\\\?\/s\d+\.dmcdn\.net\\\\?\/v\\\\?\/[^"\'\\\\]+/i',
        '/https?:\\\\?\/\\\\?\/[^"\'\\\\]*tiktokcdn[^"\'\\\\]+\.(?:jpg|jpeg|webp)/i',
        '/https?:\\\\?\/\\\\?\/static-cdn\.jtvnw\.net\\\\?\/previews-ttv\\\\?\/[^"\'\\\\]+/i',
        '/https?:\\\\?\/\\\\?\/archive\.org\\\\?\/services\\\\?\/img\\\\?\/[^"\'\\\\]+/i',
        '/https?:\\\\?\/\\\\?\/[^"\'\\\\]*fbcdn[^"\'\\\\]+\.(?:jpg|jpeg|png|webp)/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $matches)) {
            return cleanThumbnailUrl($matches[0], $baseUrl);
        }
    }

    return null;
}

function applyProviderThumbnails(array $results): array
{
    if (!function_exists('curl_multi_init')) {
        return $results;
    }

    $multiHandle = curl_multi_init();
    $handles = [];

    foreach ($results as $index => $result) {
        $handle = curl_init($result['url']);
        curl_setopt_array($handle, [
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_USERAGENT => 'EziSearch Video Preview/1.0',
        ]);
        curl_multi_add_handle($multiHandle, $handle);
        $handles[$index] = $handle;
    }

    do {
        $status = curl_multi_exec($multiHandle, $running);

        if ($running) {
            curl_multi_select($multiHandle, 1);
        }
    } while ($running && $status === CURLM_OK);

    foreach ($handles as $index => $handle) {
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $content = curl_multi_getcontent($handle);

        if ($content !== false && $content !== '' && $statusCode < 400) {
            $thumbnail = extractSearchThumbnail($content, $results[$index]['url'])
                ?? extractMetaThumbnail($content, $results[$index]['url']);

            if ($thumbnail !== null) {
                $results[$index]['thumbnail'] = $thumbnail;
                $results[$index]['thumbnailFit'] = 'cover';
            }
        }

        curl_multi_remove_handle($multiHandle, $handle);
        curl_close($handle);
    }

    curl_multi_close($multiHandle);

    return $results;
}

function decodeYouTubeText(string $value): string
{
    $decoded = json_decode('"' . str_replace('"', '\"', $value) . '"');

    if (is_string($decoded)) {
        return html_entity_decode($decoded, ENT_QUOTES, 'UTF-8');
    }

    return html_entity_decode(stripslashes($value), ENT_QUOTES, 'UTF-8');
}

function extractYouTubeResults(string $html, int $limit = 30): array
{
    preg_match_all('/"videoRenderer"\s*:\s*\{(.*?)(?:"showActionMenu"|"\}\s*,\s*"trackingParams")/s', $html, $blocks);

    $results = [];
    $seen = [];

    foreach ($blocks[1] as $block) {
        if (!preg_match('/"videoId"\s*:\s*"([^"]+)"/', $block, $idMatch)) {
            continue;
        }

        $videoId = $idMatch[1];

        if (isset($seen[$videoId])) {
            continue;
        }

        $title = '';

        if (preg_match('/"title"\s*:\s*\{\s*"runs"\s*:\s*\[\s*\{\s*"text"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $block, $titleMatch)) {
            $title = decodeYouTubeText($titleMatch[1]);
        } elseif (preg_match('/"title"\s*:\s*\{\s*"simpleText"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $block, $titleMatch)) {
            $title = decodeYouTubeText($titleMatch[1]);
        }

        if ($title === '') {
            continue;
        }

        $channel = 'YouTube';

        if (preg_match('/"longBylineText"\s*:\s*\{\s*"runs"\s*:\s*\[\s*\{\s*"text"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $block, $channelMatch)) {
            $channel = decodeYouTubeText($channelMatch[1]);
        } elseif (preg_match('/"ownerText"\s*:\s*\{\s*"runs"\s*:\s*\[\s*\{\s*"text"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $block, $channelMatch)) {
            $channel = decodeYouTubeText($channelMatch[1]);
        }

        $metadata = [];

        if (preg_match_all('/"simpleText"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $block, $metaMatches)) {
            foreach ($metaMatches[1] as $meta) {
                $text = decodeYouTubeText($meta);

                if ($text !== '' && $text !== $title && !in_array($text, $metadata, true)) {
                    $metadata[] = $text;
                }
            }
        }

        $results[] = [
            'site' => $channel,
            'title' => $title,
            'url' => 'https://www.youtube.com/watch?v=' . $videoId,
            'thumbnail' => 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg',
            'thumbnailFit' => 'cover',
            'snippet' => count($metadata) > 0 ? implode(' · ', array_slice($metadata, 0, 3)) : 'YouTube video result related to your search.',
        ];
        $seen[$videoId] = true;

        if (count($results) >= $limit) {
            break;
        }
    }

    return $results;
}

function buildYouTubeSearchCard(string $query, string $modifier): array
{
    $search = trim($query . ' ' . $modifier);
    $title = $modifier === '' ? "$query videos on YouTube" : "$query $modifier";

    return [
        'site' => 'YouTube Search',
        'title' => $title,
        'url' => externalSearchUrl('https://www.youtube.com/results?search_query=', $search),
        'thumbnail' => fallbackThumbnail('youtube.com'),
        'thumbnailFit' => 'contain',
        'snippet' => 'Open YouTube results for this focused search.',
    ];
}

function buildVideoResults(string $query): array
{
    $youtubeUrl = externalSearchUrl('https://www.youtube.com/results?search_query=', $query);
    $content = fetchPage($youtubeUrl);
    $limit = 48;
    $results = $content === false || $content === '' ? [] : extractYouTubeResults($content, $limit);
    $modifiers = [
        '',
        'highlights',
        'latest',
        'best moments',
        'best dunks',
        'best assists',
        'best blocks',
        'game winner',
        'clutch moments',
        'interview',
        'documentary',
        'news',
        'reaction',
        'analysis',
        'top plays',
        'full game',
        'playoffs',
        'finals',
        'all star',
        'rookie highlights',
        'shorts',
        'training',
        'workout',
        'career highlights',
        'compilation',
        'explained',
        'podcast',
        'press conference',
        'micd up',
        'funny moments',
        'motivational',
        'behind the scenes',
        'debate',
        'espn',
        'nba',
        'first take',
        'undisputed',
        'house of highlights',
        'bleacher report',
        'interview 2026',
        'highlights 2026',
        'news today',
        'recent game',
        'best plays ever',
        'top 10',
        'top 50',
        'story',
        'legacy',
        'documentary full',
        'film study',
        'breakdown',
        'comparison',
        'vs michael jordan',
        'vs stephen curry',
        'lakers',
        'cavaliers',
        'miami heat',
        '2026',
        'today',
    ];

    foreach ($modifiers as $modifier) {
        if (count($results) >= $limit) {
            break;
        }

        $results[] = buildYouTubeSearchCard($query, $modifier);
    }

    return $results;
}

$query = normalizeQuery($_GET['q'] ?? '');
$videoResults = $query === '' ? [] : buildVideoResults($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $query === '' ? 'Video results' : e($query) . ' videos - EziSearch' ?></title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <style>
        :root {
            color-scheme: light;
            --bg: #ffffff;
            --text: #202124;
            --muted: #5f6368;
            --line: #dadce0;
            --field: #ffffff;
            --panel: #ffffff;
            --blue: #1a0dab;
            --green: #188038;
            --chip: #f1f3f4;
            --chip-hover: #e8eaed;
            --focus: rgba(26, 115, 232, 0.2);
            --shadow: 0 1px 6px rgba(32, 33, 36, 0.16);
        }

        body.dark-mode {
            color-scheme: dark;
            --bg: #111827;
            --text: #eef6f5;
            --muted: #a7b4c5;
            --line: #2d3a4d;
            --field: #182233;
            --panel: #182233;
            --blue: #8ab4f8;
            --green: #81c995;
            --chip: #223047;
            --chip-hover: #2d3a4d;
            --focus: rgba(20, 184, 166, 0.28);
            --shadow: 0 1px 8px rgba(0, 0, 0, 0.38);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: var(--text);
            background: var(--bg);
            transition: background 180ms ease, color 180ms ease;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .topbar {
            display: flex;
            align-items: center;
            gap: 28px;
            min-height: 76px;
            padding: 16px 28px 12px;
            border-bottom: 1px solid var(--line);
        }

        .brand {
            font-size: 24px;
            font-weight: 800;
            color: #0f766e;
        }

        body.dark-mode .brand {
            color: #14b8a6;
        }

        .search-form {
            position: relative;
            display: flex;
            align-items: center;
            width: min(690px, 100%);
            height: 46px;
            border: 1px solid var(--line);
            border-radius: 24px;
            padding: 0 8px 0 18px;
            background: var(--field);
            box-shadow: var(--shadow);
        }

        .search-form:focus-within {
            outline: 4px solid var(--focus);
        }

        .search-input {
            flex: 1;
            min-width: 0;
            border: 0;
            outline: 0;
            font: inherit;
            font-size: 16px;
            color: var(--text);
            background: transparent;
        }

        .search-button,
        .voice-button {
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border: 0;
            border-radius: 50%;
            color: #1a73e8;
            background: transparent;
            cursor: pointer;
        }

        body.dark-mode .search-button,
        body.dark-mode .voice-button {
            color: #8ab4f8;
        }

        .search-button:hover,
        .voice-button:hover {
            background: var(--chip);
        }

        .voice-button:disabled {
            cursor: not-allowed;
            opacity: 0.45;
        }

        .voice-button.is-listening {
            color: #e11d48;
            background: rgba(225, 29, 72, 0.12);
        }

        .search-button svg,
        .voice-button svg {
            width: 20px;
            height: 20px;
        }

        .theme-toggle {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
            min-height: 42px;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 4px 12px 4px 5px;
            color: var(--text);
            background: var(--panel);
            cursor: pointer;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            box-shadow: var(--shadow);
        }

        .theme-toggle:hover {
            background: var(--chip);
        }

        .theme-toggle-track {
            position: relative;
            display: inline-flex;
            align-items: center;
            width: 54px;
            height: 32px;
            border-radius: 999px;
            background: var(--chip);
        }

        .theme-toggle-thumb {
            position: absolute;
            left: 4px;
            display: grid;
            place-items: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            color: #fff;
            background: #0f766e;
            transition: transform 180ms ease, background 180ms ease;
        }

        body.dark-mode .theme-toggle-thumb {
            transform: translateX(22px);
            background: #14b8a6;
        }

        .theme-toggle svg {
            width: 16px;
            height: 16px;
        }

        .moon-icon {
            display: none;
        }

        body.dark-mode .sun-icon {
            display: none;
        }

        body.dark-mode .moon-icon {
            display: block;
        }

        .tabs {
            display: flex;
            gap: 22px;
            padding: 0 0 0 178px;
            border-bottom: 1px solid var(--line);
            color: var(--muted);
            font-size: 14px;
        }

        .tab {
            padding: 12px 0 10px;
            border-bottom: 3px solid transparent;
        }

        .tab.is-active {
            color: #1a73e8;
            border-color: #1a73e8;
        }

        .videos-shell {
            width: min(960px, calc(100% - 32px));
            margin-left: 178px;
            padding: 14px 0 48px;
        }

        .voice-status {
            min-height: 20px;
            margin: 10px 0 8px;
            color: var(--muted);
            font-size: 13px;
        }

        .meta {
            margin: 0 0 18px;
            color: var(--muted);
            font-size: 14px;
        }

        .video-list {
            display: grid;
            gap: 18px;
        }

        .video-card {
            display: grid;
            grid-template-columns: 220px minmax(0, 1fr);
            gap: 16px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--line);
        }

        .video-thumb-wrap {
            position: relative;
            display: block;
            overflow: hidden;
            border-radius: 8px;
            background: var(--chip);
        }

        .video-thumb {
            display: block;
            width: 100%;
            aspect-ratio: 16 / 9;
            object-fit: cover;
            padding: 0;
        }

        .video-thumb.is-contain {
            object-fit: contain;
            padding: 34px;
        }

        .play-badge {
            position: absolute;
            left: 50%;
            top: 50%;
            display: grid;
            place-items: center;
            width: 46px;
            height: 32px;
            border-radius: 8px;
            color: #fff;
            background: rgba(0, 0, 0, 0.72);
            transform: translate(-50%, -50%);
        }

        .video-site {
            display: block;
            margin-bottom: 4px;
            color: var(--green);
            font-size: 13px;
        }

        .video-title {
            display: block;
            margin-bottom: 7px;
            color: var(--blue);
            font-size: 20px;
            line-height: 1.3;
        }

        .video-card:hover .video-title {
            text-decoration: underline;
        }

        .video-snippet {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.58;
        }

        .empty-state {
            padding: 32px 0;
        }

        .empty-state h1 {
            margin: 0 0 10px;
            font-size: 24px;
            font-weight: 400;
        }

        .empty-state p {
            margin: 0;
            color: var(--muted);
            line-height: 1.5;
        }

        @media (max-width: 820px) {
            .topbar {
                align-items: stretch;
                flex-direction: column;
                gap: 14px;
                padding: 16px;
            }

            .theme-toggle {
                align-self: flex-start;
                margin-left: 0;
            }

            .tabs {
                padding-left: 16px;
                overflow-x: auto;
            }

            .videos-shell {
                margin-left: auto;
                margin-right: auto;
            }

            .video-card {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="index.php" aria-label="Back to EziSearch home">EziSearch</a>
        <form class="search-form" action="videos.php" method="get" role="search">
            <input class="search-input" id="searchInput" type="search" name="q" value="<?= e($query) ?>" autocomplete="off" list="searchRecommendations" aria-label="Search videos">
            <datalist id="searchRecommendations">
                <option value="LeBron James highlights">
                <option value="LeBron James best dunks">
                <option value="NBA top plays">
                <option value="Valorant highlights">
                <option value="Mobile Legends gameplay">
                <option value="PHP tutorial video">
                <option value="JavaScript tutorial">
                <option value="AI tools review">
                <option value="Esports documentary">
                <option value="Web development course">
            </datalist>
            <button class="voice-button" id="voiceButton" type="button" title="Voice search" aria-label="Voice search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"></path>
                    <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                    <line x1="12" x2="12" y1="19" y2="22"></line>
                </svg>
            </button>
            <button class="search-button" type="submit" aria-label="Search videos">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"></circle>
                    <path d="m20 20-4-4"></path>
                </svg>
            </button>
        </form>
        <button class="theme-toggle" id="themeToggle" type="button" aria-pressed="false" aria-label="Switch to dark mode">
            <span class="theme-toggle-track" aria-hidden="true">
                <span class="theme-toggle-thumb">
                    <svg class="sun-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="4"></circle>
                        <path d="M12 2v2"></path>
                        <path d="M12 20v2"></path>
                        <path d="m4.93 4.93 1.41 1.41"></path>
                        <path d="m17.66 17.66 1.41 1.41"></path>
                        <path d="M2 12h2"></path>
                        <path d="M20 12h2"></path>
                        <path d="m6.34 17.66-1.41 1.41"></path>
                        <path d="m19.07 4.93-1.41 1.41"></path>
                    </svg>
                    <svg class="moon-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3a6 6 0 0 0 9 7.5A9 9 0 1 1 12 3Z"></path>
                    </svg>
                </span>
            </span>
            <span id="themeToggleText">Light</span>
        </button>
    </header>

    <nav class="tabs" aria-label="Result types">
        <a class="tab" href="<?= buildSearchUrl($query) ?>">All</a>
        <a class="tab" href="<?= buildImageUrl($query) ?>">Images</a>
        <a class="tab" href="<?= buildNewsUrl($query) ?>">News</a>
        <a class="tab is-active" href="<?= buildVideoUrl($query) ?>">Videos</a>
        <a class="tab" href="<?= buildMapUrl($query) ?>">Maps</a>
    </nav>

    <main class="videos-shell">
        <div class="voice-status" id="voiceStatus" aria-live="polite"></div>

        <?php if ($query === ''): ?>
            <section class="empty-state">
                <h1>Search videos</h1>
                <p>Type or speak a topic above to find related videos from different websites.</p>
            </section>
        <?php else: ?>
            <p class="meta">Video results for <?= e($query) ?></p>
            <div class="video-list">
                <?php foreach ($videoResults as $video): ?>
                    <a class="video-card" href="<?= e($video['url']) ?>" target="_blank" rel="noopener noreferrer">
                        <span class="video-thumb-wrap">
                            <img class="video-thumb <?= ($video['thumbnailFit'] ?? 'cover') === 'contain' ? 'is-contain' : '' ?>" src="<?= e($video['thumbnail']) ?>" alt="<?= e($video['title']) ?>" loading="lazy">
                            <span class="play-badge" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                                    <path d="M8 5v14l11-7z"></path>
                                </svg>
                            </span>
                        </span>
                        <span>
                            <span class="video-site"><?= e($video['site']) ?></span>
                            <span class="video-title"><?= e($video['title']) ?></span>
                            <p class="video-snippet"><?= e($video['snippet']) ?></p>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        const themeToggleText = document.getElementById('themeToggleText');
        const input = document.getElementById('searchInput');
        const searchForm = document.querySelector('.search-form');
        const voiceButton = document.getElementById('voiceButton');
        const voiceStatus = document.getElementById('voiceStatus');
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

        function applyTheme(theme) {
            const isDark = theme === 'dark';

            document.body.classList.toggle('dark-mode', isDark);
            themeToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            themeToggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
            themeToggleText.textContent = isDark ? 'Dark' : 'Light';
            localStorage.setItem('theme', theme);
        }

        applyTheme(localStorage.getItem('theme') || 'light');

        themeToggle.addEventListener('click', () => {
            const nextTheme = document.body.classList.contains('dark-mode') ? 'light' : 'dark';
            applyTheme(nextTheme);
        });

        searchForm.addEventListener('submit', (event) => {
            const query = input.value.trim();

            if (query === '') {
                event.preventDefault();
                voiceStatus.textContent = 'Please type or speak something to search first.';
                input.focus();
            }
        });

        if (!SpeechRecognition) {
            voiceButton.disabled = true;
            voiceButton.title = 'Voice search is not supported in this browser';
            voiceStatus.textContent = 'Voice search works best in Chrome or Edge.';
        } else {
            const recognition = new SpeechRecognition();
            recognition.lang = 'en-US';
            recognition.interimResults = false;
            recognition.maxAlternatives = 1;

            voiceButton.addEventListener('click', () => {
                voiceStatus.textContent = 'Listening...';
                voiceButton.classList.add('is-listening');
                recognition.start();
            });

            recognition.addEventListener('result', (event) => {
                const transcript = event.results[0][0].transcript;
                input.value = transcript;
                voiceStatus.textContent = `Heard: ${transcript}`;

                if (input.form.requestSubmit) {
                    input.form.requestSubmit();
                } else {
                    input.form.submit();
                }
            });

            recognition.addEventListener('speechend', () => {
                recognition.stop();
            });

            recognition.addEventListener('end', () => {
                voiceButton.classList.remove('is-listening');
            });

            recognition.addEventListener('error', (event) => {
                voiceButton.classList.remove('is-listening');
                voiceStatus.textContent = event.error === 'not-allowed'
                    ? 'Microphone permission was blocked.'
                    : 'Voice search could not start.';
            });
        }
    </script>
</body>
</html>
