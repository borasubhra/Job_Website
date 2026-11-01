# JobPortal – North East & All India Jobs (PHP 8.5)

An open job website with two sections, both updated automatically:

| Section | Sources (edit `app/config/sources.php`) |
|---|---|
| **North East Jobs** | Northeast Now (assam-career, 50 posts per run), The Assam Tribune, The Sentinel Assam, plus official recruitment pages: SLPRB, APSC, NHM Assam, DEE, DHE, Kamrup Metro, Gauhati High Court, Oil India, RRC NF Railway, Gauhati University |
| **Added by you** | Jobs you type in on the admin page (http://localhost:3030/admin) |
| **All India Jobs** | Adzuna India API (free key). Naukri.com can be added once you have an official feed (see below) |

**Stack:** PHP 8.5 + Apache · PostgreSQL 16 · pgAdmin · Docker. No framework and no Composer packages; it uses only PHP's built-in extensions (PDO, cURL, DOM, SimpleXML).

## Folder structure
```
Job_Website/
├── .env                     # settings (passwords, ports, schedule, API keys)
├── docker-compose.yml       # db + web + scheduler + pgadmin
├── docker/php/Dockerfile    # PHP 8.5 + Apache + PostgreSQL driver
├── db/schema.sql            # tables: listings, fetch_runs
└── app/
    ├── public/              # web root (Apache serves this folder)
    │   ├── index.php        # front controller / routes
    │   ├── .htaccess
    │   └── assets/style.css
    ├── templates/           # layout.php, list.php, job.php, _tags.php
    ├── config/sources.php   # LIST OF JOB SOURCES (add new sites here)
    ├── src/
    │   ├── bootstrap.php    # autoload, .env, helpers
    │   ├── Db.php           # PDO connection
    │   ├── Http.php         # cURL + robots.txt
    │   ├── Ingest.php       # fetch → clean → remove duplicates → save
    │   ├── Listings.php     # search queries for the site
    │   ├── Cron.php         # schedule matcher
    │   ├── Auth.php, Admin.php  # admin login and job editor
    │   └── Fetchers/        # NewsFetcher.php (RSS + page scan), NoticeFetcher.php (official notice boards), AdzunaFetcher.php
    └── bin/
        ├── fetch.php        # run an update by hand
        └── scheduler.php    # runs forever, updates on schedule
```

## 1. Install (Windows)
Install only **Docker Desktop**: https://www.docker.com/products/docker-desktop (enable WSL 2 when asked, then restart).
PHP runs inside Docker, so you don't need to install it on Windows. VS Code is recommended for editing.

## 2. Start everything
```powershell
cd C:\Users\Samsung\Desktop\Projects\Job_Website
docker compose up -d --build
docker compose ps            # db, web, scheduler, pgadmin should be running
```
- Website: **http://localhost:3030**
- pgAdmin: **http://localhost:5050** (admin@jobportal.local / admin123). Add a server with Host `db`, Port `5432`, User `jobadmin`, Password `ChangeMe_Strong123`.
- Health check: http://localhost:3030/api/health

The `app` folder is shared with the containers, so when you edit a PHP file, refresh the browser to see the change.

## 3. Admin page: add jobs yourself
1. In `.env`, set a password of at least 10 characters:
   ```
   ADMIN_USER=admin
   ADMIN_PASSWORD=YourStrongPassword123
   ```
2. Run `docker compose up -d` to apply it.
3. Open **http://localhost:3030/admin** and log in.

| Page | What you can do |
|---|---|
| **+ Add job** | Section, title, organisation, location, last date, category, official link, short description |
| **Added by you** | Edit, hide or delete your jobs |
| **Fetched automatically** | Hide a wrong or unwanted fetched job (it won't come back on the next update) |
| **Hidden / expired** | Show a hidden job again |

Jobs disappear from the site automatically after their last date. When you see a job in a newspaper or e-paper, write the facts in your own words and link to the official notification. Don't copy the paper's text or images.

## 4. Automatic job updates
The **scheduler** container runs `app/bin/scheduler.php` all the time. Settings are in `.env`:

| Setting | Default | Meaning |
|---|---|---|
| `FETCH_CRON` | `0 6,18 * * *` | when to update (6 AM and 6 PM). Daily at 7 AM only = `0 7 * * *` |
| `FETCH_TIMEZONE` | `Asia/Kolkata` | |
| `FETCH_ON_START` | `true` | also update when the scheduler starts |
| `MAX_NEW_PER_SOURCE` | `40` | new posts saved per source per run |
| `ADZUNA_APP_ID` / `ADZUNA_APP_KEY` | empty | free keys from https://developer.adzuna.com (needed for All India Jobs) |

After changing `.env`, run `docker compose up -d` to apply it.

```powershell
docker compose exec web php bin/fetch.php            # update all sources now
docker compose exec web php bin/fetch.php nenow      # one source
docker compose exec web php bin/fetch.php india      # one section
docker compose logs -f scheduler                     # watch automatic runs
```
Last run per source: http://localhost:3030/api/status

**How each run works**
1. **News sites:** read the RSS feed (e.g. `/feed`). If there is none, scan the listing page for article links and read each new article's title, description, date and image.
2. **General news papers** (Assam Tribune, Sentinel): keep only job items (recruitment, vacancy, walk-in, posts, apply…).
3. **Save** the title, a short excerpt (400 characters max), organisation, NE state (it knows Guwahati, Shillong and other cities), last date and a link to the original. The full article is **not** copied.
4. **Remove duplicates:** the same job reported by two papers is saved once.
5. **Hide old posts:** posts whose last date has passed, or that are older than 60 days, are hidden.
6. **Respect robots.txt:** pages a site blocks for bots are skipped.

**Display order:** newest day first. Within each day, posts from all sources are mixed in a stable order of our own, so the list never copies the order of any source site.

**Official notice boards** (`'type' => 'notices'`): the fetcher reads the page, keeps links about recruitment (advertisement, vacancy, walk-in, engagement…), and skips results, admit cards, answer keys and tenders. It also skips notices dated more than 60 days ago, and takes at most the 15 newest per run. Titles start with the organisation name, e.g. "APSC: Advertisement for …".
Check each source once:
```powershell
docker compose exec web php bin/fetch.php apsc
```
If a source shows `+0 recruitment links`, open that website in your browser, go to its **Recruitment / Careers / Notices** page, and put that page's address in `'pages'` in `app/config/sources.php`. If a site doesn't work at all, set `'enabled' => false`.

**Add another newspaper:** add a block in `app/config/sources.php`:
```php
[
    'id' => 'pratidin', 'name' => 'Pratidin Time', 'section' => 'northeast', 'type' => 'news', 'jobsOnly' => false,
    'feeds' => ['https://www.pratidintime.com/feed'],
    'pages' => ['https://www.pratidintime.com/'],
],
```

### About Naukri.com
Naukri's terms don't allow automated copying, and the site blocks scrapers, so this project doesn't scrape it. Options:
- **Adzuna** (built in): an official API with a large share of Indian job ads. Get a free key and add it to `.env`.
- **Naukri official:** ask Naukri / InfoEdge about a partner or affiliate feed. It plugs in as a new fetcher plus an entry in `sources.php`.
- Other official APIs that can be added the same way: Jooble, Careerjet, National Career Service (ncs.gov.in).

### Content and permissions
Show only headlines, short excerpts and links back to the source (as this project does). Check each site's terms. For long-term use, ask the papers for permission or a partnership.

## Website URLs
| URL | Page |
|---|---|
| `/` | North East Jobs (`?q=`, `?location=`, `?page=`) |
| `/india-jobs` | All India Jobs |
| `/job/{id}-{title}` | job details |
| `/api/listings?section=northeast\|india&q=&location=&page=` | JSON |
| `/api/status`, `/api/health` | JSON |
| `/admin` | admin page (login needed) |

## Useful Docker commands
```powershell
docker compose stop              # stop (data is kept)
docker compose start             # start again
docker compose down              # remove containers (data is kept)
docker compose down -v           # DELETE all data
docker compose logs -f web       # website / PHP errors
docker exec -it jobportal_db psql -U jobadmin -d jobportal
```

## Hosting (VPS, e.g. Ubuntu)
Install Docker on the server, copy this folder over, set real passwords in `.env`, then run `docker compose up -d --build`.
Put Nginx or Caddy in front of port 3030 with HTTPS and point your domain to it.

**Before going live:**
- Change `POSTGRES_PASSWORD`, `PGADMIN_PASSWORD` and `ADMIN_PASSWORD`, and set your email in `CRAWLER_USER_AGENT`.
- Serve the site over HTTPS so the admin password is never sent unencrypted.
- Don't expose ports 5432 or 5050 to the internet: remove those `ports:` lines or firewall them.
- Remove the `./app:/var/www/app` volume lines so the server uses the code baked into the image.
