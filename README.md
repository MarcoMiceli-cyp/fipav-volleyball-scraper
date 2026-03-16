# 🏐 FIPAV FVG Volleyball Calendar Scraper

This project retrieves volleyball match calendars, upcoming matches, played matches, and standings from the official **FIPAV Friuli Venezia Giulia portal**.

The goal is to extract match information for a specific team and display it in a custom webpage, which will later be integrated into a **Joomla website** as a custom module.

---

# 🎯 Project Purpose

The official website:

http://www.fipavfvg.it/application/agency.asp?show=gare

loads match data inside an iframe pointing to:

http://friulivg.portalefipav.net/risultati-classifiche.aspx

Because the data is loaded through an iframe, this project creates a **server-side PHP proxy** that retrieves the data, parses the HTML content, and builds a custom UI.

---

# 🚀 Current Features

✔ Fetch match data from the FIPAV portal  
✔ Parse HTML content using `DOMDocument`  
✔ Extract structured match data  
✔ Identify:

- next match
- future matches
- played matches
- standings

✔ Local caching to reduce external requests  
✔ Dynamic URL filtering parameters  
✔ Custom frontend UI displaying match information  
✔ Project structured to be later converted into a Joomla module

---

# 🗂️ Project Structure

```text
fipav_proxy/
│
├── assets/
│ └── logo.jpg
│
├── cache/
│
├── src/
│ └── FipavService.php
│
├── templates/
│ └── main.php
│
├── fetch.php
├── index.php
├── style.css
└── README.md
```

### Folder explanation

**assets/**  
Static resources such as images or logos.

**cache/**  
Stores cached JSON responses from the scraper to avoid repeated requests.

**src/**  
Contains the application logic (URL construction, cURL requests, DOM parsing, caching, and data extraction).

**templates/**  
Contains the HTML template used to render the page.

**fetch.php**  
Handles the request to the FIPAV portal and returns structured JSON.

**index.php**  
Main page that loads the parsed data and displays it in the UI.

**style.css**  
Frontend styling.

---

# ⚙️ Requirements

The project requires:

- PHP **8.0 or higher**
- **cURL extension enabled**
- **XAMPP** or another local PHP development environment
- Optional:
  - Git
  - GitHub

---

# 🖥️ Installing XAMPP

### 1 Download XAMPP

Download from:

https://www.apachefriends.org

---

### 2 Install XAMPP

Run the installer and install the following components:

- Apache
- PHP
- MySQL (optional but included)

---

### 3 Start the server

Open the **XAMPP Control Panel** and start:

- Apache
- MySQL

---

### 4 Verify the server works

Open in your browser:

- http://localhost/

You should see the XAMPP dashboard.

---

# 🧪 Verify cURL Support

Create a file:

- curlcheck.php

inside:

- C:\xampp\htdocs\

Add this code:

```php
<?php
echo "curl loaded? ";
var_dump(extension_loaded('curl'));

echo "<br>";

echo "curl_init exists? ";
var_dump(function_exists('curl_init'));
```

Then open:

- http://localhost/curlcheck.php

Expected output should be:

- curl loaded? bool(true)
- curl_init exists? bool(true)

## 🌱 Local Development

Project should be into:

- C:\xampp\htdocs\fipav_proxy\

1. Test the scraper endpoint

Open:

- http://localhost/fipav_proxy/fetch.php

2. Open the frontend page

Open:

- http://localhost/fipav_proxy/index.php

The page displays:

- Next match
- Future matches
- League standings
- Played matches

## 🔧 Dynamic Parameters

The scraper supports URL parameters that match those used by the FIPAV portal.

Example:

- http://localhost/fipav_proxy/fetch.php?StId=2290&CId=85051&SId=2452&PId=7274

Supported parameters:

| Parameter  | Description                    |
| ---------- | ------------------------------ |
| ComitatoId | Regional federation identifier |
| StId       | Season identifier              |
| CId        | Competition identifier         |
| SId        | Club identifier                |
| PId        | Team participation identifier  |
| DataDa     | Filter by starting date        |
| StatoGara  | Match status filter            |

These allow dynamic filtering of the data.

## 🏗️ Planned Joomla Integration

This project will later become a Joomla custom module.

Target structure:

```text
mod_fipavmatches/
│
├── mod_fipavmatches.php
├── helper.php
│
├── tmpl/
│ └── default.php
│
├── media/
│ └── css/
│ └── style.css
│
└── mod_fipavmatches.xml
```

Mapping between current project and Joomla module:

| Current Project | Joomla Module       |
| --------------- | ------------------- |
| fetch.php       | helper.php          |
| index.php       | tmpl/default.php    |
| style.css       | media/css/style.css |

## Author

Marco [Mrek] Miceli
Software 🦧 Desinger