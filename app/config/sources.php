<?php
/**
 * JOB SOURCES. Add or remove sites here; no other code changes needed.
 *
 *  section  : 'northeast' | 'india'
 *  type     : 'news'   -> tries each RSS feed in `feeds`, then scans `pages` for job links
 *             'adzuna' -> official Adzuna API (needs ADZUNA_APP_ID / ADZUNA_APP_KEY in .env)
 *  jobsOnly : true  -> every item is a job post (e.g. a "career" category)
 *             false -> general news; only items matching job keywords are kept
 *  limit    : how many job posts to collect per run (default MAX_NEW_PER_SOURCE in .env)
 *  maxPages : how many feed/listing pages to read to reach `limit` (default 5)
 */
return [
    [
        'id' => 'nenow', 'name' => 'Northeast Now', 'section' => 'northeast', 'type' => 'news', 'jobsOnly' => true,
        'limit' => 25, 'maxPages' => 5,
        'feeds' => ['https://nenow.in/category/assam-career/feed'],
        'pages' => ['https://nenow.in/category/assam-career'],
    ],
    [
        'id' => 'assamtribune', 'name' => 'The Assam Tribune', 'section' => 'northeast', 'type' => 'news', 'jobsOnly' => false,
        'feeds' => ['https://assamtribune.com/feed', 'https://assamtribune.com/rss'],
        'pages' => ['https://assamtribune.com/assam', 'https://assamtribune.com/'],
    ],
    [
        'id' => 'sentinel', 'name' => 'The Sentinel Assam', 'section' => 'northeast', 'type' => 'news', 'jobsOnly' => false,
        'feeds' => ['https://www.sentinelassam.com/feed', 'https://www.sentinelassam.com/rss'],
        'pages' => ['https://www.sentinelassam.com/north-east-india-news/assam-news'],
    ],
    [
        'id' => 'adzuna', 'name' => 'Adzuna India', 'section' => 'india', 'type' => 'adzuna',
        'queries' => ['software', 'engineer', 'accountant', 'sales', 'teacher', 'nurse', 'government'],
        'pagesPerQuery' => 1,
    ],
    // Naukri.com: add here once you have an official/partner feed from them (see README).
];
