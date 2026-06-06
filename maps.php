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
    <title><?= $query === '' ? 'Maps' : e($query) . ' maps - EziSearch' ?></title>
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

        .results-layout {
            width: min(1120px, calc(100% - 32px));
            margin-left: 178px;
            padding: 14px 0 48px;
        }

        .voice-status {
            min-height: 20px;
            margin: 10px 0 8px;
            color: var(--muted);
            font-size: 13px;
        }

        .stay-tuned {
            display: grid;
            min-height: calc(100vh - 190px);
            place-items: center;
            text-align: center;
        }

        .stay-tuned h1 {
            margin: 0;
            font-size: clamp(36px, 7vw, 72px);
            font-weight: 700;
            letter-spacing: 0;
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

            .results-layout {
                margin-left: auto;
                margin-right: auto;
            }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="index.php" aria-label="Back to EziSearch home">EziSearch</a>
        <form class="search-form" action="maps.php" method="get" role="search">
            <input class="search-input" id="searchInput" type="search" name="q" value="<?= e($query) ?>" autocomplete="off" list="searchRecommendations" aria-label="Search maps">
            <datalist id="searchRecommendations">
                <option value="Restaurants near me">
                <option value="Hotels near me">
                <option value="Philippines tourist spots">
                <option value="Manila map">
                <option value="Cebu landmarks">
                <option value="Baguio tourist spots">
                <option value="Directions to mall">
                <option value="Coffee shop near me">
                <option value="Basketball courts near me">
                <option value="Computer shops near me">
            </datalist>
            <button class="voice-button" id="voiceButton" type="button" title="Voice search" aria-label="Voice search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"></path>
                    <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                    <line x1="12" x2="12" y1="19" y2="22"></line>
                </svg>
            </button>
            <button class="search-button" type="submit" aria-label="Search maps">
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
        <a class="tab" href="<?= buildVideoUrl($query) ?>">Videos</a>
        <a class="tab is-active" href="<?= buildMapUrl($query) ?>">Maps</a>
    </nav>

    <main class="results-layout">
        <div class="voice-status" id="voiceStatus" aria-live="polite"></div>
        <section class="stay-tuned">
            <h1>Stay Tuned!</h1>
        </section>
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
