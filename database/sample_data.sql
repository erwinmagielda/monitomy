-- ------------------------------------------------------------
-- Monitomy | Sample Dashboard Data
-- ------------------------------------------------------------
--
-- Purpose:
-- Populates the Monitomy proof-of-concept with fake visit, click,
-- bot, engagement, and geolocation records.
--
-- Safety:
-- - Uses documentation/example IP ranges only.
-- - Contains no real visitor data.
-- - Intended for screenshots, local demos, and README evidence.
--
-- Example IP ranges used:
-- - 192.0.2.0/24
-- - 198.51.100.0/24
-- - 203.0.113.0/24
-- ------------------------------------------------------------


-- ------------------------------------------------------------
-- OPTIONAL CLEANUP
-- ------------------------------------------------------------
--
-- Uncomment these lines if you want to reset demo data before import.
--
-- DELETE FROM clicks;
-- DELETE FROM visits;
-- DELETE FROM ip_geo;


-- ------------------------------------------------------------
-- SAMPLE VISITS
-- ------------------------------------------------------------

INSERT INTO visits (ip, user_agent, geo, ts) VALUES
  ('203.0.113.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/122.0 DemoBrowser/1.0', 'United Kingdom, London', NOW() - INTERVAL 13 DAY),
  ('203.0.113.11', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_2) AppleWebKit/605.1.15 Safari/605.1.15', 'United Kingdom, Manchester', NOW() - INTERVAL 12 DAY),
  ('203.0.113.12', 'Mozilla/5.0 (X11; Linux x86_64) Firefox/123.0', 'Germany, Berlin', NOW() - INTERVAL 11 DAY),
  ('198.51.100.21', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) Mobile Safari/604.1', 'United States, New York', NOW() - INTERVAL 10 DAY),
  ('198.51.100.22', 'Mozilla/5.0 (Android 14; Mobile) Chrome/122.0', 'Canada, Toronto', NOW() - INTERVAL 9 DAY),

  ('203.0.113.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/122.0 DemoBrowser/1.0', 'United Kingdom, London', NOW() - INTERVAL 8 DAY),
  ('203.0.113.13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Edge/122.0', 'Netherlands, Amsterdam', NOW() - INTERVAL 7 DAY),
  ('198.51.100.23', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_4) Chrome/121.0', 'France, Paris', NOW() - INTERVAL 6 DAY),
  ('192.0.2.30', 'Mozilla/5.0 (X11; Linux x86_64) Firefox/122.0', 'Spain, Madrid', NOW() - INTERVAL 5 DAY),
  ('192.0.2.31', 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) Safari/604.1', 'Ireland, Dublin', NOW() - INTERVAL 4 DAY),

  ('203.0.113.14', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/123.0', 'United Kingdom, Birmingham', NOW() - INTERVAL 3 DAY),
  ('203.0.113.15', 'Mozilla/5.0 (Android 13; Mobile) Chrome/122.0', 'Poland, Warsaw', NOW() - INTERVAL 2 DAY),
  ('203.0.113.16', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_1) Safari/605.1.15', 'Sweden, Stockholm', NOW() - INTERVAL 1 DAY),
  ('203.0.113.17', 'Mozilla/5.0 (Windows NT 11.0; Win64; x64) Chrome/123.0', 'United Kingdom, London', NOW()),

  -- Bot-like traffic for Bot Activity and bot/real split.
  ('198.51.100.200', 'Googlebot/2.1 (+http://www.google.com/bot.html)', 'United States, Mountain View', NOW() - INTERVAL 6 DAY),
  ('198.51.100.200', 'Googlebot/2.1 (+http://www.google.com/bot.html)', 'United States, Mountain View', NOW() - INTERVAL 5 DAY),
  ('198.51.100.201', 'Bingbot/2.0 (+http://www.bing.com/bingbot.htm)', 'United States, Redmond', NOW() - INTERVAL 4 DAY),
  ('198.51.100.202', 'ExampleCrawler/1.0', 'Unknown', NOW() - INTERVAL 3 DAY),
  ('198.51.100.202', 'ExampleCrawler/1.0', 'Unknown', NOW() - INTERVAL 2 DAY),
  ('198.51.100.203', 'DemoSpider/0.9', 'Unknown', NOW() - INTERVAL 1 DAY);


-- ------------------------------------------------------------
-- SAMPLE CLICKS
-- ------------------------------------------------------------

INSERT INTO clicks (ip, button, ts) VALUES
  ('203.0.113.10', 'shop', NOW() - INTERVAL 13 DAY),
  ('203.0.113.11', 'subscribe', NOW() - INTERVAL 12 DAY),
  ('203.0.113.12', 'spotify', NOW() - INTERVAL 11 DAY),
  ('198.51.100.21', 'bandcamp', NOW() - INTERVAL 10 DAY),
  ('198.51.100.22', 'youtube', NOW() - INTERVAL 9 DAY),

  ('203.0.113.10', 'album:Signal Alpha', NOW() - INTERVAL 8 DAY),
  ('203.0.113.13', 'shop', NOW() - INTERVAL 7 DAY),
  ('198.51.100.23', 'album:Signal Beta', NOW() - INTERVAL 6 DAY),
  ('192.0.2.30', 'subscribe', NOW() - INTERVAL 5 DAY),
  ('192.0.2.31', 'contact', NOW() - INTERVAL 4 DAY),

  ('203.0.113.14', 'album:Signal Gamma', NOW() - INTERVAL 3 DAY),
  ('203.0.113.15', 'shop', NOW() - INTERVAL 2 DAY),
  ('203.0.113.16', 'spotify', NOW() - INTERVAL 1 DAY),
  ('203.0.113.17', 'album:Signal Alpha', NOW()),

  -- Additional recent interactions for dashboard density.
  ('203.0.113.17', 'subscribe', NOW() - INTERVAL 3 HOUR),
  ('203.0.113.17', 'shop', NOW() - INTERVAL 2 HOUR),
  ('203.0.113.16', 'contact', NOW() - INTERVAL 90 MINUTE),
  ('203.0.113.15', 'album:Signal Beta', NOW() - INTERVAL 40 MINUTE),
  ('203.0.113.14', 'bandcamp', NOW() - INTERVAL 20 MINUTE);


-- ------------------------------------------------------------
-- SAMPLE GEOLOCATION CACHE
-- ------------------------------------------------------------

INSERT INTO ip_geo (ip, country, city, last_updated) VALUES
  ('203.0.113.10', 'United Kingdom', 'London', NOW()),
  ('203.0.113.11', 'United Kingdom', 'Manchester', NOW()),
  ('203.0.113.12', 'Germany', 'Berlin', NOW()),
  ('203.0.113.13', 'Netherlands', 'Amsterdam', NOW()),
  ('203.0.113.14', 'United Kingdom', 'Birmingham', NOW()),
  ('203.0.113.15', 'Poland', 'Warsaw', NOW()),
  ('203.0.113.16', 'Sweden', 'Stockholm', NOW()),
  ('203.0.113.17', 'United Kingdom', 'London', NOW()),
  ('198.51.100.21', 'United States', 'New York', NOW()),
  ('198.51.100.22', 'Canada', 'Toronto', NOW()),
  ('198.51.100.23', 'France', 'Paris', NOW()),
  ('192.0.2.30', 'Spain', 'Madrid', NOW()),
  ('192.0.2.31', 'Ireland', 'Dublin', NOW()),
  ('198.51.100.200', 'United States', 'Mountain View', NOW()),
  ('198.51.100.201', 'United States', 'Redmond', NOW()),
  ('198.51.100.202', 'Unknown', '-', NOW()),
  ('198.51.100.203', 'Unknown', '-', NOW())
ON DUPLICATE KEY UPDATE
  country = VALUES(country),
  city = VALUES(city),
  last_updated = CURRENT_TIMESTAMP;