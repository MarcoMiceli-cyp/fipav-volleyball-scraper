# 🏐 FIPAV FVG Volleyball Calendar Scraper

A server-side PHP proxy that scrapes match schedules, results, and standings for **Tiki-Taka Staranzano** from the official [FIPAV Friuli Venezia Giulia portal](http://www.fipavfvg.it/application/agency.asp?show=gare), and displays them in a custom UI — designed to be integrated as a **Joomla module**.

---

## 📑 Table of Contents

- [How It Works](#-how-it-works)
- [Features](#-features)
- [Project Structure](#️-project-structure)
- [Requirements](#️-requirements)
- [Installation](#️-installation)
- [Local Development](#-local-development)
- [Dynamic Parameters](#-dynamic-parameters)
- [Planned Joomla Integration](#️-planned-joomla-integration)

---

## 🔍 How It Works

The official FIPAV portal loads match data inside an **iframe**:

```
http://friulivg.portalefipav.net/risultati-classifiche.aspx
```

Since the data is iframe-embedded, this project acts as a **PHP proxy** that fetches, parses, and restructures the HTML into a clean, custom interface.

---

## 🚀 Features

- ✔ Fetch match data from the FIPAV portal via cURL
- ✔ Parse HTML using `DOMDocument`
- ✔ Extract and expose structured match data (JSON)
- ✔ Identify next match, upcoming matches, played matches, and standings
- ✔ Local JSON caching to reduce repeated external requests
- ✔ Dynamic URL parameter filtering
- ✔ Custom frontend UI

---

## 🗂️ Project Structure

```
fipav_proxy/
├── assets/
│   └── logo.jpg           # Static resources
├── cache/                 # Cached JSON responses
├── src/
│   └── FipavService.php   # Core logic: requests, parsing, caching
├── templates/
│   └── main.php           # HTML rendering template
├── fetch.php              # Scraper endpoint — returns structured JSON
├── index.php              # Main page — loads and displays data
├── style.css              # Frontend styles
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

## 🌱 Local Development

| Step | URL |
|------|-----|
| Test the scraper endpoint | http://localhost/fipav_proxy/fetch.php |
| Open the frontend | http://localhost/fipav_proxy/index.php |

The frontend displays: next match · upcoming matches · played matches · standings.

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

## 🏗️ Planned Joomla Integration

The project is structured for future conversion into a **Joomla custom module**.

**Target structure:**
```
mod_fipavmatches/
├── mod_fipavmatches.php
├── helper.php
├── tmpl/
│   └── default.php
├── media/
│   └── css/
│       └── style.css
└── mod_fipavmatches.xml
```

**Migration mapping:**

| Current file | Joomla module       |
|-------------|---------------------|
| `fetch.php`  | `helper.php`        |
| `index.php`  | `tmpl/default.php`  |
| `style.css`  | `media/css/style.css` |

---

## 👤 Author

**Marco "Mrek" Miceli** — Software Designer 🦧
