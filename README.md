# Monitomy

**Records website visits and button events, then displays the activity in a private PHP and MySQL dashboard.**

Monitomy is a website activity monitor built with PHP, MySQL, JavaScript, HTML, and CSS. It records page visits and controlled button events, stores them in MySQL, and presents the data through a private dashboard.

The project exists because small websites often need a direct way to review whether pages are being visited, which actions are being clicked, and whether traffic looks human or bot-like.

> Server logs can show requests, but they are not always easy to review. Monitomy turns selected website events into structured records, dashboard metrics, and visible activity evidence.

The repository version uses a neutral event surface with three generic buttons and safe sample data. A sanitised screenshot also shows the same dashboard layout used in a private live deployment, with sensitive values blurred.

---

## Skills Demonstrated

Monitomy shows practical web support, database handling, dashboard design, event logging, and basic operational controls.

• **Website Activity Review**  
Records page visits, button events, timestamps, Internet Protocol (IP) addresses, user agents, and basic location strings.

• **PHP MySQL Workflow**  
Uses PHP to validate browser events, write records to MySQL, and render a private dashboard for review.

• **JavaScript Event Logging**  
Sends visit and click events from the browser using `sendBeacon()` with a `fetch()` fallback.

• **Dashboard Evidence Handling**  
Shows visit totals, unique visitors, real-user estimates, bot-like visitors, recent clicks, recent visits, and top visitor records.

• **Basic Web Control Practice**  
Uses allowlisted actions, prepared database writes, output escaping, HTTP Basic Authentication, and Apache access rules.

---

## Architecture

Monitomy separates the event surface, logger, database schema, private dashboard, configuration files, and documentation evidence.

```text
monitomy/
├── public/
│   ├── .htaccess
│   │   Protects configuration files, disables directory listing,
│   │   blocks Git metadata, and sets basic security headers.
│   │
│   ├── credentials.json
│   │   Provides the repository credential template.
│   │   Real deployment credentials are edited only on the server.
│   │
│   ├── db_config.php
│   │   Loads database and dashboard credentials.
│   │
│   ├── index.html
│   │   Event surface that generates visit and button records.
│   │
│   ├── log.php
│   │   Receives browser events, validates payloads,
│   │   normalises actions, and writes records to MySQL.
│   │
│   ├── monitomy.php
│   │   Private dashboard for reviewing visits, clicks,
│   │   bot-like activity, top visitors, and implemented controls.
│   │
│   ├── monitomy.webp
│   │   Logo used by the event surface and dashboard.
│   │
│   ├── favicon.ico
│   │   Browser favicon.
│   │
│   └── styles.css
│       Styles the event surface.
│
├── database/
│   ├── schema.sql
│   │   Creates the `visits`, `clicks`, and `ip_geo` tables.
│   │
│   └── sample_data.sql
│       Inserts safe demonstration records for screenshots and testing.
│
├── docs/
│   └── screenshots/
│       Stores README screenshots.
│
├── README.md
└── LICENSE
```

The event flow follows this chain:

```text
Event Surface -> Browser Payload -> PHP Logger -> MySQL Tables -> Private Dashboard
```

Button events follow this chain:

```text
Button Click -> Data Attribute -> JSON Payload -> Allowlisted Action -> Clicks Table -> Dashboard Label
```

Visit events follow this chain:

```text
Page Load -> Visit Payload -> IP And User Agent Capture -> Location String -> Visits Table -> Dashboard Metrics
```

---

## Screenshots

The screenshots below show the event surface, dashboard views, implemented controls, and sanitised live deployment evidence.

### Event Surface

![Event Surface](docs/screenshots/event_surface.png)

The event surface generates visit and button records for the monitor.

### Dashboard Overview

![Traffic Overview](docs/screenshots/traffic_overview.png)

The dashboard summarises visits, unique visitors, real-user estimates, bot-like visitors, button events, primary actions, and action rate.

### Click Activity

![Recent Clicks](docs/screenshots/recent_clicks.png)

Recent Clicks shows button events after they have been written to MySQL and formatted for review.

### Bot Activity

![Bot Activity](docs/screenshots/bot_activity.png)

Bot Activity groups crawler-like traffic by IP address, hit count, and user agent.

### Implemented Controls

![Implemented Controls](docs/screenshots/implemented_controls.png)

The controls view shows which protections are present in the logger, dashboard, configuration loader, database writes, and Apache rules.

### Sanitised Live Monitor

![Sanitised Live Monitor](docs/screenshots/live_version.png)

The private live monitor uses the same dashboard layout against real website traffic, with sensitive operational values blurred.

---

## Demo

Monitomy is intended to be reviewed through a deployed PHP and MySQL folder. The repository also includes sample data so the dashboard can be populated without exposing real traffic.

### 1. Check Requirements

| Requirement | Reason |
|---|---|
| PHP Hosting | Runs the logger, dashboard, and configuration loader. |
| MySQL Database | Stores visit records, click events, and cached IP location data. |
| Apache Rules | Uses `.htaccess` for access restrictions and basic headers. |
| Browser Access | Opens the event surface and private dashboard for review. |

### 2. Copy Web Files

Copy the contents of `public/` into a web-accessible folder:

```text
public_html/monitomy-demo/
```

Expected deployed folder:

```text
monitomy-demo/
├── .htaccess
├── credentials.json
├── db_config.php
├── favicon.ico
├── index.html
├── log.php
├── monitomy.php
├── monitomy.webp
└── styles.css
```

### 3. Create Database Tables

Create a MySQL database, then import:

```text
database/schema.sql
```

This creates:

```text
visits
clicks
ip_geo
```

For a populated dashboard, import:

```text
database/sample_data.sql
```

### 4. Configure Credentials

Edit the deployed copy of `public/credentials.json` only:

```json
{
  "db_host": "localhost",
  "db_name": "your_database_name",
  "db_user": "your_database_user",
  "db_pass": "your_database_password",

  "admin_user": "your_dashboard_username",
  "admin_pass": "your_dashboard_password"
}
```

Do not commit real credentials.

### 5. Test Event Logging

Open the event surface:

```text
/monitomy-demo/
```

The page sends a visit event on load. Each button sends a different stored action.

| Button | Stored Action | Dashboard Label |
|---|---|---|
| Button #1 | `button_1` | Button #1 - Commercial Intent |
| Button #2 | `button_2` | Button #2 - Subscription Intent |
| Button #3 | `button_3` | Button #3 - External Engagement |

### 6. Review Dashboard Output

Open the private dashboard:

```text
/monitomy-demo/monitomy.php
```

The dashboard asks for the `admin_user` and `admin_pass` set in the deployed credentials file.

| Output | Content | Location |
|---|---|---|
| Visit Records | Page visit records with timestamp, IP address, user agent, and location string. | `visits` |
| Click Records | Button event records created from allowlisted browser actions. | `clicks` |
| Location Cache | Cached country and city values for repeated IP lookups. | `ip_geo` |
| Sample Data | Safe demonstration records used for screenshots and dashboard testing. | `database/sample_data.sql` |

### 7. Check Protected Files

These files should not be readable directly in the browser:

```text
/monitomy-demo/credentials.json
/monitomy-demo/db_config.php
```

---

## Limitations

Monitomy records selected website activity for review. It is not a full analytics platform, security information and event management system, or consent management system.

• **Basic Bot Detection**  
Bot-like activity is identified through user-agent patterns such as `bot`, `crawl`, and `spider`.

• **Dashboard Login Scope**  
The dashboard uses HTTP Basic Authentication rather than a full user, session, or role system.

• **Small Event Vocabulary**  
The neutral event surface uses three generic button actions for demonstration.

• **Rate Limit Scope**  
Rate limiting uses temporary files and is intended for this controlled deployment model.

• **Geolocation Dependency**  
Location strings depend on an external IP lookup and cached results.

• **Shared Hosting Assumption**  
The deployment assumes standard PHP and MySQL hosting with Apache `.htaccess` support.

• **Privacy Review Required**  
A real public deployment should be reviewed for privacy notices, consent expectations, retention needs, and applicable data protection requirements.

---

## Licence

MIT License. See `LICENSE`.
