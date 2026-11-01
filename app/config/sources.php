<?php
/**
 * JOB SOURCES. Add or remove sites here; no other code changes needed.
 *
 *  section  : 'northeast' | 'india'
 *  type     : 'news'    -> tries each RSS feed in `feeds`, then scans `pages` for job links
 *             'notices' -> official notice board / recruitment page: every recruitment link (often a PDF)
 *                          becomes a job; `org` is shown as the organisation, `location` is the default place
 *             'adzuna' -> official Adzuna API (needs ADZUNA_APP_ID / ADZUNA_APP_KEY in .env)
 *  jobsOnly : true  -> every item is a job post (e.g. a "career" category)
 *             false -> general news; only items matching job keywords are kept
 *  limit    : how many job posts to collect per run (default MAX_NEW_PER_SOURCE in .env)
 *  maxPages : how many feed/listing pages to read to reach `limit` (default 5)
 *  enabled  : false -> skipped by the scheduler (still runs with: php bin/fetch.php <id>)
 */
return [
    [
        'id' => 'nenow', 'name' => 'Northeast Now', 'section' => 'northeast', 'type' => 'news', 'jobsOnly' => true,
        'limit' => 50, 'maxPages' => 8,
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
    // ---------------- Official recruitment notice boards (Assam) ----------------
    // Check each once with:  docker compose exec web php bin/fetch.php <id>
    // If a site shows "+0 recruitment links", open it in a browser, find its Recruitment / Careers page,
    // and put that page's address in 'pages'.
    [
        'id' => 'slprb', 'name' => 'SLPRB Assam', 'org' => 'SLPRB Assam (Police)', 'section' => 'northeast', 'type' => 'notices',
        'location' => 'Assam', 'pages' => ['https://slprbassam.in/'],
    ],
    [
        'id' => 'apsc', 'name' => 'Assam Public Service Commission', 'org' => 'APSC', 'section' => 'northeast', 'type' => 'notices',
        'location' => 'Assam', 'pages' => ['https://apsc.nic.in/'],
    ],
    [
        'id' => 'nhm', 'name' => 'NHM Assam', 'org' => 'NHM Assam', 'section' => 'northeast', 'type' => 'notices',
        'location' => 'Assam', 'pages' => ['https://nhm.assam.gov.in/'],
    ],
    [
        'id' => 'dee', 'name' => 'Directorate of Elementary Education, Assam', 'org' => 'DEE Assam', 'section' => 'northeast', 'type' => 'notices',
        'location' => 'Assam', 'pages' => ['https://dee.assam.gov.in/'],
    ],
    [
        'id' => 'dhe', 'name' => 'Directorate of Higher Education, Assam', 'org' => 'DHE Assam', 'section' => 'northeast', 'type' => 'notices',
        'location' => 'Assam', 'pages' => ['https://dhe.assam.gov.in/'],
    ],
    [
        'id' => 'kamrupmetro', 'name' => 'Kamrup Metro District', 'org' => 'Kamrup Metro District', 'section' => 'northeast', 'type' => 'notices',
        'location' => 'Assam', 'pages' => ['https://kamrupmetro.assam.gov.in/'],
    ],
    [
        'id' => 'ghc', 'name' => 'Gauhati High Court', 'org' => 'Gauhati High Court', 'section' => 'northeast', 'type' => 'notices',
        'location' => 'Assam', 'pages' => ['https://ghconline.gov.in/'],
    ],
    [
        'id' => 'oilindia', 'name' => 'Oil India Limited', 'org' => 'Oil India Limited', 'section' => 'northeast', 'type' => 'notices',
        'location' => 'Assam', 'pages' => ['https://www.oil-india.com/'],
    ],
    [
        'id' => 'rrcnfr', 'name' => 'RRC NF Railway', 'org' => 'NF Railway (RRC)', 'section' => 'northeast', 'type' => 'notices',
        'location' => 'Assam', 'pages' => ['https://www.rrcnfr.co.in/'],
    ],
    [
        'id' => 'gauhatiuniv', 'name' => 'Gauhati University', 'org' => 'Gauhati University', 'section' => 'northeast', 'type' => 'notices',
        'location' => 'Assam', 'pages' => ['https://gauhati.ac.in/'],
    ],

    // ---------------- All India ----------------
    [
        'id' => 'adzuna', 'name' => 'Adzuna India', 'section' => 'india', 'type' => 'adzuna',
        'queries' => ['software', 'engineer', 'accountant', 'sales', 'teacher', 'nurse', 'government'],
        'pagesPerQuery' => 1,
    ],
    // Naukri.com: add here once you have an official/partner feed from them (see README).
];
