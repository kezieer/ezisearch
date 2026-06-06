<?php
function searchResultUrl(string $query): string
{
    return 'result.php?q=' . urlencode($query);
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function feedSummary(string $description): string
{
    $cleanText = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($description, ENT_QUOTES, 'UTF-8'))));

    if (strlen($cleanText) <= 160) {
        return $cleanText;
    }

    return substr($cleanText, 0, 157) . '...';
}

function feedCategorySlug(string $category): string
{
    if ($category === 'Technology') {
        return 'tech';
    }

    return strtolower(preg_replace('/[^a-z0-9]+/i', '-', $category));
}

function feedCategoryUrl(?string $category): string
{
    return $category === null ? 'index.php#topic-feed' : 'index.php?feed=' . urlencode(feedCategorySlug($category)) . '#topic-feed';
}

function fetchFeedContents(array $feeds): array
{
    if (function_exists('curl_multi_init')) {
        $multiHandle = curl_multi_init();
        $handles = [];

        foreach ($feeds as $feed) {
            $handle = curl_init($feed['url']);
            curl_setopt_array($handle, [
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_USERAGENT => 'EziSearch RSS Reader/1.0',
            ]);
            curl_multi_add_handle($multiHandle, $handle);
            $handles[$feed['url']] = $handle;
        }

        do {
            $status = curl_multi_exec($multiHandle, $running);

            if ($running) {
                curl_multi_select($multiHandle, 1);
            }
        } while ($running && $status === CURLM_OK);

        $contents = [];

        foreach ($handles as $url => $handle) {
            $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $content = curl_multi_getcontent($handle);

            if ($content !== false && $content !== '' && $statusCode < 400) {
                $contents[$url] = $content;
            }

            curl_multi_remove_handle($multiHandle, $handle);
            curl_close($handle);
        }

        curl_multi_close($multiHandle);

        return $contents;
    }

    $contents = [];
    $context = stream_context_create([
        'http' => [
            'timeout' => 2,
            'user_agent' => 'EziSearch RSS Reader/1.0',
        ],
    ]);

    foreach ($feeds as $feed) {
        $content = @file_get_contents($feed['url'], false, $context);

        if ($content !== false) {
            $contents[$feed['url']] = $content;
        }
    }

    return $contents;
}

function loadFeedItems(array $feeds, int $limit = 15, int $perFeedLimit = 3): array
{
    if (!function_exists('simplexml_load_string')) {
        return [];
    }

    $items = [];
    $feedContents = fetchFeedContents($feeds);

    foreach ($feeds as $feed) {
        $rssContent = $feedContents[$feed['url']] ?? false;

        if ($rssContent === false) {
            continue;
        }

        $rss = @simplexml_load_string($rssContent, 'SimpleXMLElement', LIBXML_NOCDATA);

        if (!$rss || !isset($rss->channel->item)) {
            continue;
        }

        $feedItemCount = 0;

        foreach ($rss->channel->item as $item) {
            $title = trim((string) $item->title);
            $link = trim((string) $item->link);

            if ($title === '' || $link === '') {
                continue;
            }

            $items[] = [
                'source' => $feed['name'],
                'category' => $feed['category'],
                'title' => $title,
                'link' => $link,
                'summary' => feedSummary((string) $item->description),
                'timestamp' => strtotime((string) $item->pubDate) ?: 0,
            ];

            $feedItemCount++;

            if ($feedItemCount >= $perFeedLimit) {
                break;
            }
        }
    }

    usort($items, function (array $first, array $second): int {
        return $second['timestamp'] <=> $first['timestamp'];
    });

    return array_slice($items, 0, $limit);
}

$feeds = [
    [
        'name' => 'The Manila Times',
        'category' => 'National',
        'url' => 'https://www.manilatimes.net/news/national/feed/',
    ],
    [
        'name' => 'Inquirer Esports',
        'category' => 'Esports',
        'url' => 'https://esports.inquirer.net/rss-2',
    ],
    [
        'name' => 'ONE Esports Mobile Legends',
        'category' => 'Esports',
        'url' => 'https://www.oneesports.gg/category/mobile-legends/feed/',
    ],
    [
        'name' => 'VLR.gg',
        'category' => 'Esports',
        'url' => 'https://vlr.gg/rss',
    ],
    [
        'name' => 'Dot Esports Valorant',
        'category' => 'Esports',
        'url' => 'https://dotesports.gg/valorant/feed',
    ],
    [
        'name' => 'Dot Esports League of Legends',
        'category' => 'Esports',
        'url' => 'https://dotesports.com/league-of-legends/feed',
    ],
    [
        'name' => 'HLTV',
        'category' => 'Esports',
        'url' => 'https://www.hltv.org/rss/news',
    ],
    [
        'name' => 'Esports.gg',
        'category' => 'Esports',
        'url' => 'https://esports.gg/feed/',
    ],
    [
        'name' => 'Dexerto Esports',
        'category' => 'Esports',
        'url' => 'https://www.dexerto.com/esports/feed/',
    ],
    [
        'name' => 'TechCrunch',
        'category' => 'Technology',
        'url' => 'https://techcrunch.com/feed/',
    ],
    [
        'name' => 'The Manila Times',
        'category' => 'Business',
        'url' => 'https://www.manilatimes.net/business/feed/',
    ],
];
$feedCategories = array_values(array_unique(array_column($feeds, 'category')));
$selectedFeed = strtolower(trim($_GET['feed'] ?? ''));
$selectedCategory = null;

foreach ($feedCategories as $category) {
    $categoryAliases = [feedCategorySlug($category)];

    if ($category === 'Esports') {
        $categoryAliases[] = 'esport';
    }

    if ($category === 'Technology') {
        $categoryAliases[] = 'technology';
    }

    if (in_array($selectedFeed, $categoryAliases, true)) {
        $selectedCategory = $category;
        break;
    }
}

$activeFeeds = $selectedCategory === null
    ? $feeds
    : array_values(array_filter(
        $feeds,
        function (array $feed) use ($selectedCategory): bool {
            return $feed['category'] === $selectedCategory;
        }
    ));

$feedItems = loadFeedItems($activeFeeds, 30, 8);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EziSearch</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <style>
        :root {
            color-scheme: light;
            --bg: #f7f8fb;
            --text: #1b2430;
            --muted: #637083;
            --line: #dfe5ee;
            --panel: #ffffff;
            --panel-soft: #f1f5f7;
            --panel-hover: #eef6f5;
            --body-gradient: radial-gradient(circle at 15% 10%, rgba(15, 118, 110, 0.12), transparent 28%),
                linear-gradient(135deg, #f7f8fb 0%, #eef3f7 54%, #f8fbfa 100%);
            --stage-bg: rgba(255, 255, 255, 0.84);
            --primary: #0f766e;
            --primary-dark: #115e59;
            --accent: #e11d48;
            --focus: rgba(15, 118, 110, 0.22);
            --shadow: 0 18px 50px rgba(29, 42, 58, 0.12);
        }

        body.dark-mode {
            color-scheme: dark;
            --bg: #111827;
            --text: #eef6f5;
            --muted: #a7b4c5;
            --line: #2d3a4d;
            --panel: #182233;
            --panel-soft: #223047;
            --panel-hover: #213d3d;
            --body-gradient: radial-gradient(circle at 15% 10%, rgba(20, 184, 166, 0.18), transparent 28%),
                linear-gradient(135deg, #0b1120 0%, #111827 56%, #10201f 100%);
            --stage-bg: rgba(24, 34, 51, 0.88);
            --primary: #14b8a6;
            --primary-dark: #5eead4;
            --accent: #fb7185;
            --focus: rgba(20, 184, 166, 0.3);
            --shadow: 0 18px 50px rgba(0, 0, 0, 0.32);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            color: var(--text);
            background: var(--body-gradient);
            transition: background 180ms ease, color 180ms ease;
        }

        .shell {
            width: min(1040px, calc(100% - 32px));
            margin: 0 auto;
            padding: 56px 0 40px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 36px;
            font-size: 18px;
            font-weight: 700;
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
            box-shadow: 0 10px 24px rgba(29, 42, 58, 0.08);
            transition: background 160ms ease, border-color 160ms ease, transform 160ms ease;
        }

        .theme-toggle:hover {
            background: var(--panel-soft);
        }

        .theme-toggle:active {
            transform: scale(0.98);
        }

        .theme-toggle:focus-visible {
            outline: 4px solid var(--focus);
        }

        .theme-toggle-track {
            position: relative;
            display: inline-flex;
            align-items: center;
            width: 54px;
            height: 32px;
            border-radius: 999px;
            background: var(--panel-soft);
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
            background: var(--primary);
            transition: transform 180ms ease, background 180ms ease;
        }

        body.dark-mode .theme-toggle-thumb {
            transform: translateX(22px);
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

        .brand-mark {
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            border-radius: 8px;
            color: #fff;
            background: linear-gradient(135deg, var(--primary), #2563eb);
            box-shadow: 0 10px 24px rgba(15, 118, 110, 0.22);
        }

        .search-stage {
            padding: clamp(28px, 6vw, 64px);
            border: 1px solid rgba(223, 229, 238, 0.9);
            border-radius: 8px;
            background: var(--stage-bg);
            box-shadow: var(--shadow);
            backdrop-filter: blur(18px);
        }

        h1 {
            max-width: 760px;
            margin: 0 0 24px;
            font-size: clamp(36px, 7vw, 72px);
            line-height: 0.98;
            letter-spacing: 0;
        }

        .search-form {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            min-height: 64px;
            padding: 8px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel);
            box-shadow: 0 12px 28px rgba(29, 42, 58, 0.08);
        }

        .search-field {
            position: relative;
            flex: 1 1 auto;
            min-width: 0;
        }

        .search-input {
            width: 100%;
            min-width: 0;
            height: 48px;
            border: 0;
            outline: 0;
            padding: 0 8px 0 12px;
            font-size: 18px;
            color: var(--text);
            background: transparent;
        }

        .search-input::placeholder {
            color: #8a95a5;
        }

        .suggestions {
            position: absolute;
            z-index: 20;
            top: calc(100% + 12px);
            left: 0;
            right: 0;
            display: none;
            max-height: 280px;
            margin: 0;
            padding: 6px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel);
            box-shadow: 0 18px 38px rgba(29, 42, 58, 0.16);
            list-style: none;
            overflow-y: auto;
        }

        .suggestions.is-visible {
            display: block;
        }

        .suggestion-button {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            min-height: 42px;
            border: 0;
            border-radius: 8px;
            padding: 0 12px;
            color: var(--text);
            background: transparent;
            cursor: pointer;
            font: inherit;
            text-align: left;
        }

        .suggestion-button:hover,
        .suggestion-button.is-active {
            background: var(--panel-hover);
        }

        .suggestion-button svg {
            flex: 0 0 auto;
            width: 18px;
            height: 18px;
            color: var(--muted);
        }

        .icon-button,
        .submit-button {
            display: inline-grid;
            place-items: center;
            flex: 0 0 auto;
            height: 48px;
            border: 0;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 160ms ease, background 160ms ease, color 160ms ease;
        }

        .icon-button {
            width: 48px;
            color: var(--muted);
            background: var(--panel-soft);
        }

        .icon-button:hover,
        .icon-button.is-listening {
            color: #fff;
            background: var(--accent);
        }

        .icon-button:focus-visible,
        .submit-button:focus-visible,
        .search-input:focus-visible {
            outline: 4px solid var(--focus);
        }

        .submit-button {
            min-width: 52px;
            padding: 0 18px;
            color: #fff;
            background: var(--primary);
            font-weight: 700;
        }

        .submit-button:hover {
            background: var(--primary-dark);
        }

        .icon-button:active,
        .submit-button:active {
            transform: scale(0.98);
        }

        .status-row {
            min-height: 24px;
            margin-top: 14px;
            color: var(--muted);
            font-size: 14px;
        }

        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .quick-links a {
            display: inline-flex;
            align-items: center;
            min-height: 36px;
            padding: 0 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            color: var(--text);
            background: var(--panel);
            text-decoration: none;
            font-size: 14px;
        }

        .topic-feed {
            margin-top: 28px;
            padding: 24px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--stage-bg);
            box-shadow: 0 12px 32px rgba(29, 42, 58, 0.08);
        }

        .topic-feed-header {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .topic-feed h2 {
            margin: 0;
            font-size: 24px;
            line-height: 1.2;
        }

        .topic-feed-note {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .feed-source-list {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .feed-source-link {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 6px 10px;
            border: 1px solid var(--line);
            border-radius: 999px;
            color: var(--muted);
            background: var(--panel);
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            transition: border-color 160ms ease, color 160ms ease, background 160ms ease;
        }

        .feed-source-link:hover,
        .feed-source-link.is-active {
            border-color: var(--primary);
            color: var(--primary-dark);
            background: var(--panel-hover);
        }

        .feed-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .feed-card {
            display: flex;
            flex-direction: column;
            min-height: 190px;
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 8px;
            color: var(--text);
            background: var(--panel);
            text-decoration: none;
            transition: transform 160ms ease, border-color 160ms ease, background 160ms ease;
        }

        .feed-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
            background: var(--panel-hover);
        }

        .feed-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
        }

        .feed-category {
            color: var(--primary-dark);
        }

        .feed-title {
            margin: 0 0 10px;
            font-size: 18px;
            line-height: 1.3;
        }

        .feed-summary {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
        }

        .feed-date {
            margin-top: auto;
            padding-top: 16px;
            color: var(--muted);
            font-size: 12px;
        }

        .feed-empty {
            margin: 0;
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 8px;
            color: var(--muted);
            background: var(--panel);
            line-height: 1.5;
        }

        .results {
            margin-top: 34px;
        }

        .results-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
            color: var(--muted);
            font-size: 15px;
        }

        .external-search {
            color: var(--primary-dark);
            font-weight: 700;
            text-decoration: none;
        }

        .result-list {
            display: grid;
            gap: 14px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .result-item {
            padding: 22px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel);
        }

        .result-item a {
            color: #075985;
            font-size: 20px;
            font-weight: 700;
            text-decoration: none;
        }

        .result-url {
            margin: 6px 0;
            color: var(--primary-dark);
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .result-description {
            margin: 0;
            color: var(--muted);
            line-height: 1.55;
        }

        .empty-state {
            padding: 24px;
            border: 1px solid var(--line);
            border-radius: 8px;
            color: var(--muted);
            background: var(--panel);
            line-height: 1.55;
        }

        svg {
            width: 22px;
            height: 22px;
        }

        @media (max-width: 640px) {
            .shell {
                width: min(100% - 20px, 1040px);
                padding-top: 24px;
            }

            .brand {
                align-items: flex-start;
                margin-bottom: 20px;
            }

            .theme-toggle {
                gap: 6px;
                padding-right: 8px;
                font-size: 13px;
            }

            .search-stage {
                padding: 20px;
            }

            h1 {
                font-size: 40px;
            }

            .search-form {
                gap: 6px;
                min-height: 58px;
                padding: 6px;
            }

            .search-input {
                height: 46px;
                font-size: 16px;
            }

            .icon-button,
            .submit-button {
                width: 46px;
                min-width: 46px;
                height: 46px;
                padding: 0;
            }

            .submit-button span {
                position: absolute;
                width: 1px;
                height: 1px;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
            }

            .topic-feed {
                padding: 18px;
            }

            .topic-feed-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .feed-source-list {
                justify-content: flex-start;
            }

            .feed-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (min-width: 641px) and (max-width: 900px) {
            .feed-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>
</head>
<body>
    <main class="shell">
        <div class="brand" aria-label="SearchWave">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="7"></circle>
                    <path d="m20 20-4-4"></path>
                </svg>
            </span>
            <span>EziSearch, Search Your Idea Today.</span>
            <button class="theme-toggle" id="themeToggle" type="button" aria-label="Switch to dark mode" aria-pressed="false">
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
        </div>

        <section class="search-stage">
           

            <form class="search-form" action="result.php" method="get" role="search">
                <div class="search-field">
                    <input
                        class="search-input"
                        id="searchInput"
                        name="q"
                        type="search"
                        autocomplete="off"
                        list="searchRecommendations"
                        placeholder="Type or speak a search..."
                        aria-label="Search query"
                        aria-autocomplete="list"
                        aria-controls="suggestions"
                        aria-expanded="false"
                    >
                    <datalist id="searchRecommendations">
                        <option value="LeBron James highlights">
                        <option value="Valorant esports news">
                        <option value="Mobile Legends tournament">
                        <option value="PHP tutorial">
                        <option value="JavaScript search bar">
                        <option value="NBA latest news">
                        <option value="Philippines national news">
                        <option value="AI tools 2026">
                        <option value="Web development roadmap">
                        <option value="EziSearch">
                    </datalist>
                    <ul class="suggestions" id="suggestions" role="listbox" aria-label="Search recommendations"></ul>
                </div>

                <button class="icon-button" id="voiceButton" type="button" title="Voice search" aria-label="Voice search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"></path>
                        <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                        <line x1="12" x2="12" y1="19" y2="22"></line>
                    </svg>
                </button>

                <button class="submit-button" type="submit" title="Search" aria-label="Search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="m20 20-4-4"></path>
                    </svg>
                    <span>Search</span>
                </button>
            </form>

            <div class="status-row" id="voiceStatus" aria-live="polite"></div>

            <nav class="quick-links" aria-label="Suggested searches">
                <a href="<?= searchResultUrl('PHP') ?>">PHP</a>
                <a href="<?= searchResultUrl('JavaScript') ?>">JavaScript</a>
                <a href="<?= searchResultUrl('developer') ?>">Developer</a>
                <a href="<?= searchResultUrl('Wikipedia') ?>">Wikipedia</a>
                <a href="<?= feedCategoryUrl('Esports') ?>">Esports News</a>
            </nav>
        </section>

        <section class="topic-feed" id="topic-feed" aria-labelledby="topicFeedTitle">
            <div class="topic-feed-header">
                <div>
                    <h2 id="topicFeedTitle"><?= $selectedCategory ? 'Latest ' . e($selectedCategory) : 'Latest Topics' ?></h2>
                    <p class="topic-feed-note">Fresh stories sorted newest first from the RSS feeds you provided.</p>
                </div>
                <ul class="feed-source-list" aria-label="RSS feed sources">
                    <li>
                        <a class="feed-source-link<?= $selectedCategory === null ? ' is-active' : '' ?>" href="<?= feedCategoryUrl(null) ?>">All</a>
                    </li>
                    <?php foreach ($feedCategories as $category): ?>
                        <li>
                            <a
                                class="feed-source-link<?= $selectedCategory === $category ? ' is-active' : '' ?>"
                                href="<?= feedCategoryUrl($category) ?>"
                            ><?= e($category === 'Technology' ? 'Tech' : $category) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php if ($feedItems): ?>
                <div class="feed-grid">
                    <?php foreach ($feedItems as $item): ?>
                        <a class="feed-card" href="<?= e($item['link']) ?>" target="_blank" rel="noopener noreferrer">
                            <div class="feed-meta">
                                <span><?= e($item['source']) ?></span>
                                <span class="feed-category"><?= e($item['category']) ?></span>
                            </div>
                            <h3 class="feed-title"><?= e($item['title']) ?></h3>
                            <?php if ($item['summary'] !== ''): ?>
                                <p class="feed-summary"><?= e($item['summary']) ?></p>
                            <?php endif; ?>
                            <?php if ($item['timestamp']): ?>
                                <span class="feed-date"><?= e(date('M j, Y g:i A', $item['timestamp'])) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="feed-empty">The topic feed could not load right now. Please check your internet connection or try again later.</p>
            <?php endif; ?>
        </section>
    </main>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        const themeToggleText = document.getElementById('themeToggleText');
        const input = document.getElementById('searchInput');
        const searchForm = document.querySelector('.search-form');
        const voiceButton = document.getElementById('voiceButton');
        const voiceStatus = document.getElementById('voiceStatus');
        const suggestions = document.getElementById('suggestions');
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const suggestionBank = [
            'PHP tutorial',
            'PHP login system',
            'PHP search engine project',
            'JavaScript tutorial',
            'JavaScript DOM events',
            'JavaScript search bar suggestions',
            'HTML form tutorial',
            'CSS responsive design',
            'MySQL database tutorial',
            'XAMPP localhost setup',
            'web development roadmap',
            'Stack Overflow PHP questions',
            'Wikipedia',
            'Google search tips',
            'Laravel tutorial',
            'Bootstrap examples',
            'how to connect PHP to MySQL',
            'voice search JavaScript',
            'autocomplete search bar HTML CSS JavaScript'
        ];
        const intentSuggestions = {
            age: ['birthday', 'date of birth', 'height', 'biography', 'family'],
            height: ['age', 'weight', 'stats', 'biography'],
            stats: ['career stats', 'current team', 'highlights', 'salary'],
            net: ['net worth', 'salary', 'contract', 'career earnings'],
            worth: ['net worth', 'salary', 'contract', 'career earnings'],
            wife: ['family', 'children', 'biography'],
            husband: ['family', 'children', 'biography'],
            biography: ['age', 'birthday', 'career', 'family'],
            tutorial: ['examples', 'for beginners', 'step by step', 'documentation'],
            examples: ['tutorial', 'template', 'source code', 'best practices'],
            near: ['near me', 'open now', 'reviews', 'directions']
        };
        const ignoredSuggestionWords = new Set([
            'a',
            'an',
            'and',
            'are',
            'for',
            'how',
            'i',
            'is',
            'me',
            'my',
            'of',
            'on',
            'or',
            'the',
            'to',
            'what',
            'when',
            'where',
            'who'
        ]);
        let activeSuggestionIndex = -1;

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

        function buildSuggestions(query) {
            const cleanQuery = query.trim().replace(/\s+/g, ' ');
            const searchText = cleanQuery.toLowerCase();

            if (searchText === '') {
                return [];
            }

            const queryWords = cleanQuery.split(/\s+/);
            const searchTokens = queryWords
                .map((word) => word.toLowerCase())
                .filter((word) => word.length > 1 && !ignoredSuggestionWords.has(word));
            const rankedSuggestions = suggestionBank
                .map((suggestion) => {
                    const suggestionText = suggestion.toLowerCase();
                    const score = searchTokens.reduce((total, token) => {
                        return total + (suggestionText.includes(token) ? 1 : 0);
                    }, suggestionText.includes(searchText) ? 3 : 0);

                    return { suggestion, score };
                })
                .filter((item) => item.score > 0)
                .sort((first, second) => second.score - first.score)
                .map((item) => item.suggestion);

            const detectedIntents = searchTokens.filter((token) => intentSuggestions[token]);
            const topicWords = queryWords.filter((word) => {
                const normalizedWord = word.toLowerCase();

                return normalizedWord.length > 1
                    && !ignoredSuggestionWords.has(normalizedWord)
                    && !intentSuggestions[normalizedWord];
            });
            const topic = topicWords.length ? topicWords.join(' ') : cleanQuery;
            const topicSuggestions = detectedIntents.flatMap((intent) => {
                return intentSuggestions[intent].map((relatedIntent) => `${topic} ${relatedIntent}`);
            });

            const dynamicSuggestions = [
                cleanQuery,
                ...topicSuggestions,
                `${cleanQuery} meaning`,
                `${cleanQuery} facts`,
                `${cleanQuery} latest`,
                `${cleanQuery} explained`,
                `who is ${topic}`
            ];

            return [...new Set([...rankedSuggestions, ...dynamicSuggestions])]
                .filter((suggestion) => suggestion.trim() !== '')
                .slice(0, 6);
        }

        function hideSuggestions() {
            suggestions.classList.remove('is-visible');
            suggestions.innerHTML = '';
            input.setAttribute('aria-expanded', 'false');
            activeSuggestionIndex = -1;
        }

        function setActiveSuggestion(index) {
            const buttons = suggestions.querySelectorAll('.suggestion-button');
            activeSuggestionIndex = index;

            buttons.forEach((button, buttonIndex) => {
                button.classList.toggle('is-active', buttonIndex === activeSuggestionIndex);
            });
        }

        function searchWithSuggestion(value) {
            input.value = value;
            hideSuggestions();

            if (input.form.requestSubmit) {
                input.form.requestSubmit();
            } else {
                input.form.submit();
            }
        }

        function escapeHtml(value) {
            return value.replace(/[&<>"']/g, (character) => {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[character];
            });
        }

        function showSuggestions() {
            const items = buildSuggestions(input.value);

            if (!items.length) {
                hideSuggestions();
                return;
            }

            suggestions.innerHTML = items.map((item, index) => `
                <li role="option">
                    <button class="suggestion-button" type="button" data-value="${escapeHtml(item)}" data-index="${index}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m20 20-4-4"></path>
                        </svg>
                        <span>${escapeHtml(item)}</span>
                    </button>
                </li>
            `).join('');

            suggestions.classList.add('is-visible');
            input.setAttribute('aria-expanded', 'true');
            setActiveSuggestion(-1);
        }

        input.addEventListener('input', showSuggestions);

        searchForm.addEventListener('submit', (event) => {
            const query = input.value.trim();

            if (query === '') {
                event.preventDefault();
                alert('Please type something to search first.');
                input.focus();
                return;
            }

        });

        input.addEventListener('keydown', (event) => {
            const buttons = suggestions.querySelectorAll('.suggestion-button');

            if (!buttons.length) {
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActiveSuggestion((activeSuggestionIndex + 1) % buttons.length);
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActiveSuggestion(activeSuggestionIndex <= 0 ? buttons.length - 1 : activeSuggestionIndex - 1);
            }

            if (event.key === 'Enter' && activeSuggestionIndex >= 0) {
                event.preventDefault();
                searchWithSuggestion(buttons[activeSuggestionIndex].dataset.value);
            }

            if (event.key === 'Escape') {
                hideSuggestions();
            }
        });

        suggestions.addEventListener('click', (event) => {
            const button = event.target.closest('.suggestion-button');

            if (button) {
                searchWithSuggestion(button.dataset.value);
            }
        });

        document.addEventListener('click', (event) => {
            if (!event.target.closest('.search-form')) {
                hideSuggestions();
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
                hideSuggestions();
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
