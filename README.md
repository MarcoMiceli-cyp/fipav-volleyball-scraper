# 🏐 FIPAV FVG Volleyball Calendar Scraper

A server-side PHP proxy that scrapes match schedules, results, and standings for **Tiki-Taka Staranzano** from the official [FIPAV Friuli Venezia Giulia portal](http://www.fipavfvg.it/application/agency.asp?show=gare), and displays them in a custom UI — integrated into **Joomla** via iframe embed.

---

## 📑 Table of Contents

- [How It Works](#how-it-works)
- [Features](#features)
- [Project Structure](#project-structure)
- [Requirements](#requirements)
- [Installation](#installation)
- [Local Development](#local-development)
- [Dynamic Parameters](#dynamic-parameters)
- [Joomla Integration](#joomla-integration)

---

## 🔍 How It Works

The official FIPAV portal loads match data inside an **iframe**:

```
http://friulivg.portalefipav.net/risultati-classifiche.aspx
```

Since the data is iframe-embedded, this project acts as a **PHP proxy** that fetches, parses, and restructures the HTML into a clean, custom interface.

---

## Features

- Fetch match data from the FIPAV portal via cURL
- Parse HTML using `DOMDocument`
- Extract and expose structured match data (JSON)
- Identify next match, upcoming matches, played matches, and standings
- Local JSON caching to reduce repeated external requests
- Dynamic URL parameter filtering
- Custom frontend UI with tab navigation (overview, standings, calendar, results)
- Dark/light theme toggle with `localStorage` persistence
- `embed.php` — iframe-ready version for Joomla embedding, with auto-resize via `postMessage`

---

## 🗂️ Project Structure

```
fipav_proxy/
├── assets/
│   └── tiki_taka_logo.jpg   # Team logo
├── cache/                   # Cached JSON responses (auto-generated)
├── embed.php                # Iframe-ready UI — used for Joomla embedding
├── fetch.php                # Scraper endpoint — returns structured JSON
├── index.php                # Standalone page — loads and displays data
├── style.css                # Frontend styles (shared by index.php and embed.php)
└── README.md
```

---

## ⚙️ Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.0+ |
| cURL extension | enabled |
| Local server | XAMPP (or equivalent) |

---

## 🖥️ Installation

### 1. Download and install XAMPP

Get it from [apachefriends.org](https://www.apachefriends.org).
During installation, include: **Apache**, **PHP**, and **MySQL** (optional).

### 2. Start the server

Open the **XAMPP Control Panel** and start **Apache** (and MySQL if needed).
Verify it's running at: [http://localhost/](http://localhost/)

### 3. Verify cURL support

Create `C:\xampp\htdocs\curlcheck.php` with:

```php
<?php
echo "curl loaded? ";
var_dump(extension_loaded('curl'));
echo "<br>";
echo "curl_init exists? ";
var_dump(function_exists('curl_init'));
```

Open [http://localhost/curlcheck.php](http://localhost/curlcheck.php) — expected output:

```
curl loaded? bool(true)
curl_init exists? bool(true)
```

### 4. Clone the project

Place the project in:

```
C:\xampp\htdocs\fipav_proxy\
```

---

## 🌱  Local Development

| Step | URL |
|------|-----|
| Test the scraper endpoint | http://localhost/fipav_proxy/fetch.php |
| Open the standalone frontend | http://localhost/fipav_proxy/index.php |
| Preview the embed (iframe) | http://localhost/fipav_proxy/embed.php |

---

## 🔧 Dynamic Parameters

The scraper accepts URL parameters mirroring those of the FIPAV portal.

**Example:**
```
http://localhost/fipav_proxy/fetch.php?StId=2290&CId=85051&SId=2452&PId=7274
```

| Parameter    | Description                    |
|--------------|--------------------------------|
| `ComitatoId` | Regional federation identifier |
| `StId`       | Season identifier              |
| `CId`        | Competition identifier         |
| `SId`        | Club identifier                |
| `PId`        | Team participation identifier  |
| `DataDa`     | Filter by starting date        |
| `StatoGara`  | Match status filter            |

---

## 🏗️ Joomla Integration

The project is integrated into the Joomla site via an **`<iframe>`** pointing to `embed.php`.

`embed.php` is a self-contained, iframe-optimized version of the UI that:
- strips page margins and sets a transparent background
- communicates its real height to the parent page via `window.parent.postMessage({ iframeHeight })` for auto-resize
- shares the same `style.css` and `fetch.php` backend as the standalone version

**Joomla-side setup:** add a Custom HTML module with an `<iframe>` tag and a small JS listener that reads the `iframeHeight` message to resize the iframe dynamically.

---

## 👤 Author

**Marco "Mrek" Miceli** — Software Designer 🦧