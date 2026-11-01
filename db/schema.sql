-- Job listings schema. Safe to run many times (used by Docker on first start and by bin/fetch.php / scheduler).
CREATE TABLE IF NOT EXISTS listings (
  id            SERIAL PRIMARY KEY,
  section       VARCHAR(20)  NOT NULL CHECK (section IN ('northeast','india')),
  source_id     VARCHAR(40)  NOT NULL,
  source_name   VARCHAR(120) NOT NULL,
  title         TEXT         NOT NULL,
  title_key     TEXT         NOT NULL,          -- normalised title, removes the same job reported by two sites
  summary       TEXT,
  source_url    TEXT         NOT NULL UNIQUE,
  image_url     TEXT,
  organisation  VARCHAR(200),
  location      VARCHAR(200),
  category      VARCHAR(120),
  salary_min    INT,
  salary_max    INT,
  last_date     DATE,
  published_at  TIMESTAMPTZ,
  fetched_at    TIMESTAMPTZ  NOT NULL DEFAULT now(),
  is_active     BOOLEAN      NOT NULL DEFAULT TRUE
);
CREATE UNIQUE INDEX IF NOT EXISTS ux_listings_section_titlekey ON listings(section, title_key);
CREATE INDEX IF NOT EXISTS ix_listings_section_date ON listings(section, published_at DESC);

CREATE TABLE IF NOT EXISTS fetch_runs (
  id          SERIAL PRIMARY KEY,
  source_id   VARCHAR(40) NOT NULL,
  started_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
  finished_at TIMESTAMPTZ,
  found       INT DEFAULT 0,
  inserted    INT DEFAULT 0,
  error       TEXT
);
