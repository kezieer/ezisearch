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

$query = normalizeQuery($_GET['q'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $query === '' ? 'Image results' : e($query) . ' images - EziSearch' ?></title>
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

        .theme-toggle:focus-visible,
        .search-button:focus-visible,
        .voice-button:focus-visible {
            outline: 4px solid var(--focus);
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

        .images-shell {
            width: min(1160px, calc(100% - 32px));
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

        .image-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 0 0 18px;
        }

        .image-action {
            display: inline-flex;
            align-items: center;
            min-height: 36px;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 0 14px;
            color: var(--blue);
            background: var(--panel);
            font-size: 14px;
            font-weight: 700;
        }

        .image-action:hover {
            background: var(--chip);
        }

        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 18px;
            align-items: start;
        }

        .image-card {
            display: block;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel);
        }

        .image-card.is-hidden {
            display: none;
        }

        .image-card:hover .image-title {
            text-decoration: underline;
        }

        .image-thumb {
            display: block;
            width: 100%;
            aspect-ratio: 4 / 3;
            object-fit: cover;
            background: var(--chip);
        }

        .image-body {
            padding: 10px 12px 12px;
        }

        .image-title {
            display: block;
            overflow: hidden;
            color: var(--text);
            font-size: 14px;
            line-height: 1.35;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .image-source {
            display: block;
            margin-top: 4px;
            color: var(--green);
            font-size: 12px;
        }

        .empty-state {
            padding: 32px 0;
        }

        .empty-state h1 {
            margin: 0 0 10px;
            font-size: 24px;
            font-weight: 400;
        }

        .empty-state p,
        .image-status {
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

            .images-shell {
                margin-left: auto;
                margin-right: auto;
            }

            .image-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 12px;
            }

        }
    </style>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="index.php" aria-label="Back to EziSearch home">EziSearch</a>
        <form class="search-form" action="images.php" method="get" role="search">
            <input class="search-input" id="searchInput" type="search" name="q" value="<?= e($query) ?>" autocomplete="off" list="searchRecommendations" aria-label="Search images">
            <datalist id="searchRecommendations">
                <option value="LeBron James photos">
                <option value="Valorant wallpapers">
                <option value="Mobile Legends heroes">
                <option value="PHP logo">
                <option value="JavaScript icons">
                <option value="NBA images">
                <option value="Philippines landmarks">
                <option value="AI generated art">
                <option value="Web design inspiration">
                <option value="EziSearch logo">
            </datalist>
            <button class="voice-button" id="voiceButton" type="button" title="Voice search" aria-label="Voice search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"></path>
                    <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                    <line x1="12" x2="12" y1="19" y2="22"></line>
                </svg>
            </button>
            <button class="search-button" type="submit" aria-label="Search images">
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
        <a class="tab is-active" href="<?= buildImageUrl($query) ?>">Images</a>
        <a class="tab" href="<?= buildNewsUrl($query) ?>">News</a>
        <a class="tab" href="<?= buildVideoUrl($query) ?>">Videos</a>
        <a class="tab" href="<?= buildMapUrl($query) ?>">Maps</a>
    </nav>

    <main class="images-shell">
        <div class="voice-status" id="voiceStatus" aria-live="polite"></div>

        <?php if ($query === ''): ?>
            <section class="empty-state">
                <h1>Search images</h1>
                <p>Type or speak a topic above to see image results from the web.</p>
            </section>
        <?php else: ?>
            <p class="meta">Image results for <?= e($query) ?></p>
            <div class="image-actions">
                <a class="image-action" href="https://www.google.com/search?tbm=isch&q=<?= urlencode($query) ?>" target="_blank" rel="noopener noreferrer">Open Google Images</a>
                <a class="image-action" href="https://www.bing.com/images/search?q=<?= urlencode($query) ?>" target="_blank" rel="noopener noreferrer">Open Bing Images</a>
            </div>
            <p class="image-status" id="imageStatus">Loading images...</p>
            <div class="image-grid" id="imageGrid" data-query="<?= e($query) ?>" aria-live="polite"></div>
        <?php endif; ?>
    </main>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        const themeToggleText = document.getElementById('themeToggleText');
        const input = document.getElementById('searchInput');
        const searchForm = document.querySelector('.search-form');
        const voiceButton = document.getElementById('voiceButton');
        const voiceStatus = document.getElementById('voiceStatus');
        const imageGrid = document.getElementById('imageGrid');
        const imageStatus = document.getElementById('imageStatus');
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

        function applyTheme(theme) {
            const isDark = theme === 'dark';

            document.body.classList.toggle('dark-mode', isDark);
            themeToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            themeToggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
            themeToggleText.textContent = isDark ? 'Dark' : 'Light';
            localStorage.setItem('theme', theme);
        }

        function cleanTitle(title = '') {
            return title.replace(/^File:/, '').replace(/\.[a-z0-9]{2,5}$/i, '').replace(/[_-]+/g, ' ');
        }

        function escapeHtml(value) {
            return value.replace(/[&<>"']/g, (character) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[character]));
        }

        function validHttpUrl(value) {
            return typeof value === 'string' && /^https?:\/\//i.test(value);
        }

        function updateVisibleImageCount() {
            if (!imageGrid || !imageStatus) {
                return;
            }

            const visibleCards = imageGrid.querySelectorAll('.image-card:not(.is-hidden)').length;

            if (visibleCards === 0 && imageGrid.children.length > 0) {
                imageStatus.textContent = 'Some previews were blocked. Open Google Images above for more image results.';
            }
        }

        function renderImageCard(item) {
            if (!validHttpUrl(item.thumb) || !validHttpUrl(item.url)) {
                return '';
            }

            const safeTitle = escapeHtml(cleanTitle(item.title || 'Image result'));
            const safeThumb = escapeHtml(item.thumb);
            const safeUrl = escapeHtml(item.url);
            const safeSource = escapeHtml(item.source || 'Image source');

            return `
                <a class="image-card" href="${safeUrl}" target="_blank" rel="noopener noreferrer">
                    <img class="image-thumb" src="${safeThumb}" alt="${safeTitle}" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.closest('.image-card').classList.add('is-hidden'); updateVisibleImageCount();">
                    <span class="image-body">
                        <span class="image-title">${safeTitle}</span>
                        <span class="image-source">${safeSource}</span>
                    </span>
                </a>
            `;
        }

        function mapWikimediaImage(item) {
            const image = item.imageinfo && item.imageinfo[0] ? item.imageinfo[0] : null;

            if (!image || !image.thumburl || !image.descriptionurl) {
                return null;
            }

            return {
                title: item.title,
                thumb: image.thumburl,
                url: image.descriptionurl,
                source: 'Wikimedia Commons',
            };
        }

        function mapOpenverseImage(item) {
            const thumb = item.thumbnail || item.url;
            const url = item.foreign_landing_url || item.url;

            if (!thumb || !url) {
                return null;
            }

            return {
                title: item.title || 'Openverse image',
                thumb,
                url,
                source: item.creator ? `Openverse - ${item.creator}` : 'Openverse',
            };
        }

        function imageSearchUrl(baseUrl, query) {
            return `${baseUrl}${encodeURIComponent(query)}`;
        }

        async function loadWikimediaImages(query) {
            const params = new URLSearchParams({
                action: 'query',
                generator: 'search',
                gsrsearch: query,
                gsrnamespace: '6',
                gsrlimit: '30',
                prop: 'imageinfo',
                iiprop: 'url|mime',
                iiurlwidth: '640',
                format: 'json',
                origin: '*'
            });
            const response = await fetch(`https://commons.wikimedia.org/w/api.php?${params.toString()}`);

            if (!response.ok) {
                return [];
            }

            const data = await response.json();
            const pages = data.query && data.query.pages ? Object.values(data.query.pages) : [];

            return pages
                .filter((page) => page.imageinfo && page.imageinfo[0] && page.imageinfo[0].mime && page.imageinfo[0].mime.startsWith('image/'))
                .map(mapWikimediaImage)
                .filter(Boolean);
        }

        async function loadOpenverseImages(query) {
            const params = new URLSearchParams({
                q: query,
                page_size: '30',
                mature: 'false'
            });
            const response = await fetch(`https://api.openverse.engineering/v1/images/?${params.toString()}`);

            if (!response.ok) {
                return [];
            }

            const data = await response.json();
            const results = Array.isArray(data.results) ? data.results : [];

            return results
                .map(mapOpenverseImage)
                .filter(Boolean);
        }

        async function loadImages() {
            if (!imageGrid) {
                return;
            }

            const query = imageGrid.dataset.query.trim();

            if (query === '') {
                return;
            }

            imageStatus.textContent = 'Loading public web images...';

            const responses = await Promise.allSettled([
                loadWikimediaImages(query),
                loadOpenverseImages(query)
            ]);
            const images = responses
                .filter((response) => response.status === 'fulfilled')
                .flatMap((response) => response.value);
            const seen = new Set();
            const uniqueImages = images.filter((image) => {
                const key = image.thumb || image.url;

                if (!key || seen.has(key)) {
                    return false;
                }

                seen.add(key);
                return true;
            }).slice(0, 48);
            const cards = uniqueImages
                .map(renderImageCard)
                .filter(Boolean)
                .join('');

            if (cards === '') {
                imageStatus.textContent = 'No image previews loaded here. Open Google Images above for broader results.';
                return;
            }

            imageGrid.innerHTML = cards;
            imageStatus.textContent = `${uniqueImages.length} public web image results. Open Google Images above for more.`;
        }

        applyTheme(localStorage.getItem('theme') || 'light');
        loadImages();

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
