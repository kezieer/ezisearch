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

function titleCaseTopic(string $topic): string
{
    return ucwords(strtolower($topic));
}

function formatTopic(string $topic): string
{
    return preg_match('/[A-Z]/', $topic) ? $topic : titleCaseTopic($topic);
}

function queryParts(string $query): array
{
    $intentWords = [
        'age',
        'birthday',
        'biography',
        'children',
        'career',
        'contract',
        'examples',
        'family',
        'facts',
        'height',
        'history',
        'meaning',
        'net',
        'salary',
        'stats',
        'tutorial',
        'wife',
        'worth',
    ];
    $stopWords = ['a', 'an', 'and', 'for', 'how', 'is', 'me', 'my', 'of', 'on', 'or', 'the', 'to', 'what', 'when', 'where', 'who'];
    $words = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);
    $intent = [];
    $topic = [];

    foreach ($words as $word) {
        $normalizedWord = strtolower($word);

        if (in_array($normalizedWord, $stopWords, true)) {
            continue;
        }

        if (in_array($normalizedWord, $intentWords, true)) {
            $intent[] = $normalizedWord;
            continue;
        }

        $topic[] = $word;
    }

    return [
        'topic' => formatTopic($topic ? implode(' ', $topic) : $query),
        'intent' => $intent ? implode(' ', $intent) : 'general',
    ];
}

function answerText(string $topic, string $intent, string $query): string
{
    $templates = [
        'age' => "$topic age is the main fact people are searching for. Check recent and trusted profiles for the most current age, birthday, and biography details.",
        'birthday' => "$topic birthday and date of birth are usually listed in biography profiles, official pages, and reference websites.",
        'height' => "$topic height is best checked from official profiles, sports pages, cast pages, or verified biographies.",
        'stats' => "$topic stats can include career numbers, recent performance, records, awards, and year-by-year results.",
        'wife' => "$topic wife and family details are personal information, so the best results usually come from interviews, official bios, and reputable entertainment or sports sources.",
        'net worth' => "$topic net worth is usually an estimate. Compare salary, contracts, business income, and recent financial reports before trusting one number.",
        'net' => "$topic net worth is usually an estimate. Compare salary, contracts, business income, and recent financial reports before trusting one number.",
        'worth' => "$topic net worth is usually an estimate. Compare salary, contracts, business income, and recent financial reports before trusting one number.",
        'tutorial' => "$topic tutorial results should start with basics, examples, setup steps, and common mistakes to avoid.",
        'examples' => "$topic examples help you understand the idea faster by showing real usage, patterns, and sample projects.",
        'meaning' => "$topic meaning explains what the searched term refers to, why it matters, and how it is commonly used.",
        'general' => "Browse trusted websites, search pages, references, and media results related to \"$query\".",
    ];

    return $templates[$intent] ?? "$topic $intent results focus on the most relevant facts, background, and related information for \"$query\".";
}

function queryMatches(string $query, array $keywords): bool
{
    $normalizedQuery = strtolower($query);

    foreach ($keywords as $keyword) {
        if (strpos($normalizedQuery, strtolower($keyword)) !== false) {
            return true;
        }
    }

    return false;
}

function resultItem(string $site, string $title, string $baseUrl, string $searchQuery, string $snippet): array
{
    return [
        'site' => $site,
        'title' => $title,
        'url' => externalSearchUrl($baseUrl, $searchQuery),
        'snippet' => $snippet,
    ];
}

function buildResults(string $query): array
{
    $parts = queryParts($query);
    $topic = $parts['topic'];
    $intent = $parts['intent'];
    $topTitle = $intent === 'general'
        ? "Search results for $topic"
        : "$topic - " . titleCaseTopic($intent);

    $results = [
        [
            'site' => 'Wikipedia',
            'title' => $topTitle,
            'url' => externalSearchUrl('https://en.wikipedia.org/w/index.php?search=', $query),
            'snippet' => answerText($topic, $intent, $query),
        ],
    ];

    if (queryMatches($query, ['php', 'javascript', 'typescript', 'html', 'css', 'python', 'java', 'coding', 'code', 'programming', 'developer', 'web development', 'laravel', 'react', 'vue', 'node', 'mysql', 'database', 'api', 'bootstrap'])) {
        $results[] = resultItem('MDN Web Docs', "$topic documentation", 'https://developer.mozilla.org/en-US/search?q=', $topic, "Reference docs and examples for web development topics related to $topic.");
        $results[] = resultItem('Stack Overflow', "$topic coding answers", 'https://stackoverflow.com/search?q=', $topic, "Developer questions and answers that may help fix errors or understand code examples.");
        $results[] = resultItem('GitHub', "$topic projects", 'https://github.com/search?q=', $topic, "Open-source repositories, examples, and code projects connected to $topic.");
        $results[] = resultItem('W3Schools', "$topic examples", 'https://www.w3schools.com/search/search_result.asp?q=', $topic, "Beginner-friendly examples, syntax notes, and quick lessons for $topic.");
        $results[] = resultItem('Dev.to', "$topic developer articles", 'https://dev.to/search?q=', $topic, "Developer tutorials, examples, project writeups, and practical notes about $topic.");
        $results[] = resultItem('freeCodeCamp', "$topic coding tutorials", 'https://www.freecodecamp.org/news/search/?query=', $topic, "Step-by-step programming lessons and beginner-friendly explanations for $topic.");
    }

    if (queryMatches($query, ['esport', 'valorant', 'mobile legends', 'league of legends', 'dota', 'counter strike', 'cs2', 'csgo', 'mlbb', 'vct', 'tournament', 'match'])) {
        $results[] = resultItem('Liquipedia', "$topic esports wiki", 'https://liquipedia.net/commons/index.php?search=', $topic, "Tournament, team, player, and match information for esports topics.");
        $results[] = resultItem('VLR.gg', "$topic Valorant coverage", 'https://www.vlr.gg/search/?q=', $topic, "Valorant match pages, player news, team updates, and community coverage.");
        $results[] = resultItem('Dot Esports', "$topic esports news", 'https://dotesports.com/search?q=', $topic, "News and guides for esports titles, teams, players, patches, and tournaments.");
        $results[] = resultItem('ONE Esports', "$topic esports updates", 'https://www.oneesports.gg/?s=', $topic, "Esports news, tournament stories, player guides, and game updates for $topic.");
        $results[] = resultItem('Dexerto', "$topic gaming news", 'https://www.dexerto.com/search/', $topic, "Gaming and esports headlines, guides, updates, and community stories related to $topic.");
    }

    if (queryMatches($query, ['nba', 'basketball', 'lebron', 'jordan', 'football', 'soccer', 'tennis', 'baseball', 'volleyball', 'player', 'team', 'stats', 'score', 'standings'])) {
        $results[] = resultItem('ESPN', "$topic sports coverage", 'https://www.espn.com/search/_/q/', $topic, "Sports news, schedules, player pages, team pages, and analysis for $topic.");
        $results[] = resultItem('StatMuse', "$topic stats", 'https://www.statmuse.com/search?q=', $topic, "Fast sports stats and comparisons for players, teams, and historical records.");
        $results[] = resultItem('CBS Sports', "$topic sports news", 'https://www.cbssports.com/search/?q=', $topic, "Sports headlines, team updates, rankings, and game coverage related to $topic.");
        $results[] = resultItem('Bleacher Report', "$topic sports stories", 'https://bleacherreport.com/search?query=', $topic, "Sports stories, rankings, highlights, and fan-focused coverage for $topic.");
        $results[] = resultItem('Sofascore', "$topic live scores", 'https://www.sofascore.com/search?q=', $topic, "Live scores, fixtures, standings, and match statistics connected to $topic.");
    }

    if (queryMatches($query, ['movie', 'film', 'actor', 'actress', 'series', 'show', 'anime', 'manga', 'music', 'song', 'artist', 'band', 'album', 'lyrics'])) {
        $results[] = resultItem('IMDb', "$topic movies and cast", 'https://www.imdb.com/find/?q=', $topic, "Movie, show, actor, cast, and entertainment information connected to $topic.");
        $results[] = resultItem('Rotten Tomatoes', "$topic reviews", 'https://www.rottentomatoes.com/search?search=', $topic, "Review pages, ratings, and entertainment discovery results for $topic.");
        $results[] = resultItem('MyAnimeList', "$topic anime database", 'https://myanimelist.net/search/all?q=', $topic, "Anime, manga, character, creator, and review pages related to $topic.");
        $results[] = resultItem('Spotify', "$topic music", 'https://open.spotify.com/search/', $topic, "Songs, albums, artists, playlists, and podcast results for $topic.");
        $results[] = resultItem('Genius', "$topic lyrics and songs", 'https://genius.com/search?q=', $topic, "Lyrics, song meanings, artist pages, and music annotations for $topic.");
        $results[] = resultItem('Letterboxd', "$topic film lists", 'https://letterboxd.com/search/', $topic, "Movie pages, reviews, ratings, watchlists, and film discussions about $topic.");
    }

    if (queryMatches($query, ['buy', 'price', 'shop', 'laptop', 'phone', 'shoes', 'product', 'camera', 'keyboard', 'mouse', 'iphone', 'samsung', 'pc', 'parts', 'sale', 'deal', 'review'])) {
        $results[] = resultItem('Amazon', "$topic product listings", 'https://www.amazon.com/s?k=', $topic, "Product listings, prices, reviews, and buying options for $topic.");
        $results[] = resultItem('Lazada', "$topic shopping in the Philippines", 'https://www.lazada.com.ph/catalog/?q=', $topic, "Local shopping results, product prices, seller pages, and deals for $topic.");
        $results[] = resultItem('Shopee Philippines', "$topic Shopee listings", 'https://shopee.ph/search?keyword=', $topic, "Philippine marketplace listings, local sellers, reviews, and vouchers for $topic.");
        $results[] = resultItem('PCPartPicker', "$topic PC parts", 'https://pcpartpicker.com/search/?q=', $topic, "Compatibility, part lists, prices, and hardware comparisons for PC-related searches.");
        $results[] = resultItem('Tom\'s Guide', "$topic buying advice", 'https://www.tomsguide.com/search?searchTerm=', $topic, "Buying guides, product reviews, comparisons, and recommendations for $topic.");
        $results[] = resultItem('GSMArena', "$topic phone specs", 'https://www.gsmarena.com/results.php3?sQuickSearch=yes&sName=', $topic, "Phone specifications, comparisons, reviews, and release details for $topic.");
    }

    if (queryMatches($query, ['near me', 'map', 'restaurant', 'hotel', 'place', 'location', 'direction', 'travel', 'tourist'])) {
        $results[] = resultItem('Google Maps', "$topic on maps", 'https://www.google.com/maps/search/', $topic, "Map listings, directions, reviews, and location details for $topic.");
        $results[] = resultItem('Tripadvisor', "$topic travel reviews", 'https://www.tripadvisor.com/Search?q=', $topic, "Travel reviews, hotels, restaurants, attractions, and visitor tips related to $topic.");
        $results[] = resultItem('Booking.com', "$topic stays", 'https://www.booking.com/searchresults.html?ss=', $topic, "Hotel, resort, apartment, and accommodation results for $topic.");
        $results[] = resultItem('Agoda', "$topic hotels", 'https://www.agoda.com/search?textToSearch=', $topic, "Hotel and stay options, local travel deals, and guest reviews for $topic.");
        $results[] = resultItem('Waze', "$topic directions", 'https://www.waze.com/live-map/search?query=', $topic, "Driving directions, traffic-aware routes, and local map help for $topic.");
    }

    if (queryMatches($query, ['recipe', 'food', 'cook', 'meal', 'cake', 'chicken', 'pasta', 'dessert', 'ulam', 'breakfast', 'dinner'])) {
        $results[] = resultItem('Allrecipes', "$topic recipes", 'https://www.allrecipes.com/search?q=', $topic, "Recipe ideas, cooking steps, ratings, and ingredient suggestions for $topic.");
        $results[] = resultItem('Food Network', "$topic cooking guides", 'https://www.foodnetwork.com/search/', $topic, "Cooking videos, chef recipes, meal ideas, and food guides related to $topic.");
        $results[] = resultItem('Panlasang Pinoy', "$topic Filipino recipes", 'https://panlasangpinoy.com/?s=', $topic, "Filipino cooking guides, local recipes, ingredients, and home-style meal ideas for $topic.");
        $results[] = resultItem('Yummy.ph', "$topic food ideas", 'https://www.yummy.ph/search?q=', $topic, "Recipe ideas, cooking tips, meal planning, and Filipino food inspiration for $topic.");
    }

    if (queryMatches($query, ['health', 'medicine', 'symptom', 'disease', 'doctor', 'fitness', 'workout', 'diet', 'nutrition', 'clinic', 'hospital'])) {
        $results[] = resultItem('Mayo Clinic', "$topic health information", 'https://www.mayoclinic.org/search/search-results?q=', $topic, "Health information, symptoms, causes, treatments, and prevention details for $topic.");
        $results[] = resultItem('WebMD', "$topic medical guide", 'https://www.webmd.com/search/search_results/default.aspx?query=', $topic, "Medical explainers, symptom guides, wellness advice, and condition information for $topic.");
        $results[] = resultItem('Healthline', "$topic wellness guide", 'https://www.healthline.com/search?q1=', $topic, "Wellness articles, condition explainers, nutrition guides, and fitness information for $topic.");
        $results[] = resultItem('Cleveland Clinic', "$topic health guide", 'https://my.clevelandclinic.org/search?q=', $topic, "Medical condition guides, treatment information, and patient education about $topic.");
    }

    if (queryMatches($query, ['school', 'study', 'math', 'science', 'history', 'english', 'lesson', 'education', 'course', 'exam', 'homework'])) {
        $results[] = resultItem('Khan Academy', "$topic lessons", 'https://www.khanacademy.org/search?page_search_query=', $topic, "Lessons, practice exercises, and study explanations related to $topic.");
        $results[] = resultItem('Coursera', "$topic courses", 'https://www.coursera.org/search?query=', $topic, "Online courses, learning paths, and certificates connected to $topic.");
        $results[] = resultItem('edX', "$topic online courses", 'https://www.edx.org/search?q=', $topic, "University-style online courses, programs, and learning materials about $topic.");
        $results[] = resultItem('Quizlet', "$topic study sets", 'https://quizlet.com/search?query=', $topic, "Flashcards, study sets, practice questions, and class notes related to $topic.");
    }

    if (queryMatches($query, ['job', 'career', 'hiring', 'salary', 'resume', 'work', 'company', 'internship'])) {
        $results[] = resultItem('LinkedIn', "$topic jobs and profiles", 'https://www.linkedin.com/search/results/all/?keywords=', $topic, "Jobs, company pages, professional profiles, and career posts related to $topic.");
        $results[] = resultItem('Indeed', "$topic job listings", 'https://www.indeed.com/jobs?q=', $topic, "Job openings, company reviews, salaries, and hiring information for $topic.");
        $results[] = resultItem('Glassdoor', "$topic salary and reviews", 'https://www.glassdoor.com/Search/results.htm?keyword=', $topic, "Company reviews, salary ranges, interview details, and career insights for $topic.");
        $results[] = resultItem('JobStreet Philippines', "$topic PH jobs", 'https://www.jobstreet.com.ph/jobs?keywords=', $topic, "Philippine job openings, company pages, and career opportunities for $topic.");
    }

    if (queryMatches($query, ['business', 'stock', 'crypto', 'bitcoin', 'finance', 'market', 'bank', 'invest', 'price today'])) {
        $results[] = resultItem('Yahoo Finance', "$topic market data", 'https://finance.yahoo.com/lookup?s=', $topic, "Stock quotes, company pages, market news, charts, and financial data for $topic.");
        $results[] = resultItem('Investopedia', "$topic finance guide", 'https://www.investopedia.com/search?q=', $topic, "Finance definitions, investing guides, business explainers, and market education for $topic.");
        $results[] = resultItem('CoinMarketCap', "$topic crypto prices", 'https://coinmarketcap.com/search/?q=', $topic, "Crypto prices, charts, rankings, and market data related to $topic.");
        $results[] = resultItem('BusinessWorld', "$topic business news", 'https://www.bworldonline.com/?s=', $topic, "Philippine business news, finance updates, and company coverage for $topic.");
    }

    if (queryMatches($query, ['philippines', 'philippine', 'pinoy', 'manila', 'cebu', 'davao', 'government', 'law', 'visa', 'passport'])) {
        $results[] = resultItem('Official Gazette PH', "$topic government reference", 'https://www.officialgazette.gov.ph/?s=', $topic, "Official Philippine government announcements, references, laws, and public information about $topic.");
        $results[] = resultItem('Philippine News Agency', "$topic PH news", 'https://www.pna.gov.ph/search?q=', $topic, "Official Philippine news reports, public updates, and government-related stories about $topic.");
        $results[] = resultItem('GMA News', "$topic GMA coverage", 'https://www.gmanetwork.com/news/search/?q=', $topic, "Philippine news, public affairs, entertainment, and local coverage related to $topic.");
        $results[] = resultItem('Inquirer.net', "$topic Inquirer articles", 'https://www.inquirer.net/search?q=', $topic, "Philippine articles, opinion, news, lifestyle, and local reporting about $topic.");
    }

    if (queryMatches($query, ['game', 'gaming', 'minecraft', 'roblox', 'steam', 'playstation', 'xbox', 'nintendo', 'patch', 'mod'])) {
        $results[] = resultItem('Steam', "$topic games", 'https://store.steampowered.com/search/?term=', $topic, "PC game listings, reviews, prices, and community pages for $topic.");
        $results[] = resultItem('IGN', "$topic game news", 'https://www.ign.com/search?q=', $topic, "Game reviews, news, trailers, walkthroughs, and entertainment coverage for $topic.");
        $results[] = resultItem('GameSpot', "$topic gaming coverage", 'https://www.gamespot.com/search/?q=', $topic, "Gaming news, reviews, guides, videos, and release information for $topic.");
        $results[] = resultItem('Nexus Mods', "$topic mods", 'https://www.nexusmods.com/search/', $topic, "Game mods, tools, community files, and customization pages related to $topic.");
    }

    if (queryMatches($query, ['ai', 'chatgpt', 'openai', 'machine learning', 'artificial intelligence', 'prompt', 'model'])) {
        $results[] = resultItem('OpenAI', "$topic OpenAI resources", 'https://openai.com/search/?q=', $topic, "OpenAI product, research, and documentation pages related to $topic.");
        $results[] = resultItem('Hugging Face', "$topic AI models", 'https://huggingface.co/search/full-text?q=', $topic, "AI models, datasets, spaces, and machine learning resources for $topic.");
        $results[] = resultItem('Papers with Code', "$topic research papers", 'https://paperswithcode.com/search?q=', $topic, "Machine learning papers, benchmarks, methods, and code related to $topic.");
        $results[] = resultItem('Towards Data Science', "$topic AI articles", 'https://towardsdatascience.com/search?q=', $topic, "Data science and AI explainers, tutorials, and project articles about $topic.");
    }

    $results[] = resultItem('Google Search', "More results for $topic", 'https://www.google.com/search?q=', $query, "Search across the web for official sites, guides, profiles, and recent information about $topic.");
    $results[] = resultItem('Google News', "$topic latest news", 'https://news.google.com/search?q=', $topic, "Recent articles and updates about $topic from different news publishers.");
    $results[] = resultItem('Britannica', "$topic facts and quick profile", 'https://www.britannica.com/search?query=', "$topic facts", "A quick profile for $topic with important facts, common questions, background, and related details.");
    $results[] = resultItem('YouTube', "$topic videos", 'https://www.youtube.com/results?search_query=', $topic, "Videos, explainers, highlights, tutorials, reviews, and visual guides related to $topic.");
    $results[] = resultItem('Reddit', "$topic discussions", 'https://www.reddit.com/search/?q=', $topic, "Community discussions, opinions, questions, and real user experiences related to $topic.");
    $results[] = resultItem('Google Images', "$topic images", 'https://www.google.com/search?tbm=isch&q=', $topic, "Image results that can help identify people, places, products, teams, logos, or visual references for $topic.");

    return array_slice($results, 0, 14);
}

$query = normalizeQuery($_GET['q'] ?? '');
$results = $query === '' ? [] : buildResults($query);
$parts = $query === '' ? ['topic' => '', 'intent' => 'general'] : queryParts($query);
$relatedSearches = $query === ''
    ? ['PHP tutorial', 'JavaScript search bar', 'web development roadmap', 'Wikipedia']
    : [
        $parts['topic'] . ' age',
        $parts['topic'] . ' biography',
        $parts['topic'] . ' facts',
        $parts['topic'] . ' latest',
        $parts['topic'] . ' meaning',
        'who is ' . $parts['topic'],
    ];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $query === '' ? 'Search results' : e($query) . ' - EziSearch' ?></title>
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
            display: grid;
            grid-template-columns: minmax(0, 720px) minmax(260px, 340px);
            gap: 42px;
            align-items: start;
            width: min(1120px, calc(100% - 32px));
            margin-left: 178px;
            padding: 14px 0 48px;
        }

        .results-shell {
            min-width: 0;
        }

        .meta {
            margin-bottom: 18px;
            color: var(--muted);
            font-size: 14px;
        }

        .result {
            margin-bottom: 28px;
        }

        .site {
            margin-bottom: 4px;
            color: var(--text);
            font-size: 14px;
        }

        .url {
            color: var(--green);
            font-size: 13px;
        }

        .result-title {
            display: inline-block;
            margin: 4px 0 5px;
            color: var(--blue);
            font-size: 20px;
            line-height: 1.3;
        }

        .result-title:hover {
            text-decoration: underline;
        }

        .snippet {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.58;
        }

        .knowledge-panel {
            display: none;
            position: sticky;
            top: 18px;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel);
        }

        .knowledge-panel.is-visible {
            display: block;
        }

        .knowledge-image {
            display: block;
            width: 100%;
            aspect-ratio: 4 / 3;
            object-fit: cover;
            background: var(--chip);
        }

        .knowledge-body {
            padding: 16px;
        }

        .knowledge-title {
            margin: 0 0 4px;
            font-size: 24px;
            font-weight: 400;
            line-height: 1.2;
        }

        .knowledge-description {
            margin: 0 0 12px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.4;
        }

        .knowledge-summary {
            margin: 0 0 14px;
            color: var(--text);
            font-size: 14px;
            line-height: 1.58;
        }

        .knowledge-link {
            color: var(--blue);
            font-size: 14px;
        }

        .knowledge-link:hover {
            text-decoration: underline;
        }

        .voice-status {
            min-height: 20px;
            margin: 10px 0 8px;
            color: var(--muted);
            font-size: 13px;
        }

        .related-title {
            margin: 34px 0 14px;
            font-size: 20px;
            font-weight: 400;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .related-link {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 44px;
            padding: 0 14px;
            border-radius: 22px;
            background: var(--chip);
            font-weight: 700;
        }

        .related-link:hover {
            background: var(--chip-hover);
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

            .results-layout {
                grid-template-columns: 1fr;
                margin-left: auto;
                margin-right: auto;
            }

            .knowledge-panel {
                position: static;
            }

            .related-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="index.php" aria-label="Back to EziSearch home">EziSearch</a>
        <form class="search-form" action="result.php" method="get" role="search">
            <input class="search-input" id="searchInput" type="search" name="q" value="<?= e($query) ?>" autocomplete="off" list="searchRecommendations" aria-label="Search query">
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
            <button class="voice-button" id="voiceButton" type="button" title="Voice search" aria-label="Voice search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"></path>
                    <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                    <line x1="12" x2="12" y1="19" y2="22"></line>
                </svg>
            </button>
            <button class="search-button" type="submit" aria-label="Search">
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
        <a class="tab is-active" href="<?= buildSearchUrl($query) ?>">All</a>
        <a class="tab" href="<?= buildImageUrl($query) ?>">Images</a>
        <a class="tab" href="<?= buildNewsUrl($query) ?>">News</a>
        <a class="tab" href="<?= buildVideoUrl($query) ?>">Videos</a>
        <a class="tab" href="<?= buildMapUrl($query) ?>">Maps</a>
    </nav>

    <main class="results-layout">
        <div class="results-shell">
            <div class="voice-status" id="voiceStatus" aria-live="polite"></div>

            <?php if ($query === ''): ?>
                <section class="empty-state">
                    <h1>Search EziSearch</h1>
                    <p>Type a topic above to see result-style ideas, summaries, and related searches.</p>
                </section>
            <?php else: ?>
                <p class="meta">About <?= number_format(strlen($query) * 1840 + 9020) ?> results (0.<?= strlen($query) % 8 + 2 ?> seconds)</p>

                <?php foreach ($results as $result): ?>
                    <article class="result">
                        <a class="site" href="<?= e($result['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($result['site']) ?></a>
                        <a class="url" href="<?= e($result['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($result['url']) ?></a>
                        <a class="result-title" href="<?= e($result['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($result['title']) ?></a>
                        <p class="snippet"><?= e($result['snippet']) ?></p>
                    </article>
                <?php endforeach; ?>

                <h2 class="related-title">Related searches</h2>
                <div class="related-grid">
                    <?php foreach (array_unique($relatedSearches) as $relatedSearch): ?>
                        <a class="related-link" href="<?= buildSearchUrl($relatedSearch) ?>">
                            <span><?= e($relatedSearch) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($query !== ''): ?>
            <aside class="knowledge-panel" id="knowledgePanel" data-query="<?= e($query) ?>" aria-label="Search topic details">
                <img class="knowledge-image" id="knowledgeImage" alt="">
                <div class="knowledge-body">
                    <h2 class="knowledge-title" id="knowledgeTitle"></h2>
                    <p class="knowledge-description" id="knowledgeDescription"></p>
                    <p class="knowledge-summary" id="knowledgeSummary"></p>
                    <a class="knowledge-link" id="knowledgeLink" href="#" target="_blank" rel="noopener noreferrer">View source</a>
                </div>
            </aside>
        <?php endif; ?>
    </main>
    <script>
        const themeToggle = document.getElementById('themeToggle');
        const themeToggleText = document.getElementById('themeToggleText');
        const input = document.getElementById('searchInput');
        const searchForm = document.querySelector('.search-form');
        const voiceButton = document.getElementById('voiceButton');
        const voiceStatus = document.getElementById('voiceStatus');
        const knowledgePanel = document.getElementById('knowledgePanel');
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

        function setKnowledgeText(elementId, text) {
            const element = document.getElementById(elementId);

            if (element) {
                element.textContent = text || '';
            }
        }

        async function loadKnowledgePanel() {
            if (!knowledgePanel) {
                return;
            }

            const query = knowledgePanel.dataset.query.trim();

            if (query === '') {
                return;
            }

            try {
                const searchUrl = `https://en.wikipedia.org/w/api.php?action=opensearch&search=${encodeURIComponent(query)}&limit=1&namespace=0&format=json&origin=*`;
                const searchResponse = await fetch(searchUrl);
                const searchData = await searchResponse.json();
                const title = searchData[1] && searchData[1][0] ? searchData[1][0] : query;
                const summaryUrl = `https://en.wikipedia.org/api/rest_v1/page/summary/${encodeURIComponent(title)}`;
                const summaryResponse = await fetch(summaryUrl);

                if (!summaryResponse.ok) {
                    return;
                }

                const summary = await summaryResponse.json();

                if (!summary.title || !summary.extract) {
                    return;
                }

                const image = document.getElementById('knowledgeImage');
                const sourceUrl = summary.content_urls && summary.content_urls.desktop
                    ? summary.content_urls.desktop.page
                    : `https://en.wikipedia.org/wiki/${encodeURIComponent(summary.title)}`;

                setKnowledgeText('knowledgeTitle', summary.title);
                setKnowledgeText('knowledgeDescription', summary.description || 'Search topic');
                setKnowledgeText('knowledgeSummary', summary.extract);

                if (image && summary.thumbnail && summary.thumbnail.source) {
                    image.src = summary.thumbnail.source;
                    image.alt = summary.title;
                } else if (image) {
                    image.remove();
                }

                const link = document.getElementById('knowledgeLink');

                if (link) {
                    link.href = sourceUrl;
                    link.textContent = `View ${summary.title} on Wikipedia`;
                }

                knowledgePanel.classList.add('is-visible');
            } catch (error) {
                knowledgePanel.classList.remove('is-visible');
            }
        }

        function applyTheme(theme) {
            const isDark = theme === 'dark';

            document.body.classList.toggle('dark-mode', isDark);
            themeToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            themeToggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
            themeToggleText.textContent = isDark ? 'Dark' : 'Light';
            localStorage.setItem('theme', theme);
        }

        applyTheme(localStorage.getItem('theme') || 'light');
        loadKnowledgePanel();

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
