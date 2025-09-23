# Free TV Server

<img src="public/assets/freetv.png" width="120" style="margin-bottom: 20px;">

See it online at: https://freetv.today

**Version 2.1.1 - Beta**

Free TV Server contains both a front-end site for end users and a back-end administration tool (the Admin Dashboard) for managing content. 

The front-end site allows users to watch free TV and movie content from the Internet Archive.

The administrator can log into the Admin Dashboard and add/remove shows, create playlists, fetch thumbnails, modify playlist data, and change configuration settings. 

This is the main server for the Free TV project which provides centralized content management. This server is the main source for playlist data (categories and shows) which are consumed by stand-alone Free TV viewer apps. The Free TV server also provides an API which is used by both the front-end application and also the back-end Admin Dashboard tool. 

---

## Table of Contents

- [Requirements](#requirements)
- [Getting Started](#getting-started)
- [Code Style](#code-style)
- [Features](#features)
- [Project Structure](#project-structure)
- [Development](#development)
- [Contributing](#contributing)
- [License](#license)

---

## Requirements

- Node.js v18 or higher
- PHP 7.4 or higher
- Git (for cloning the repository)

---

## Getting Started

1. **Clone the repository:**
   ```
   git clone https://github.com/freetv-today/freetv-server
   cd freetv-server
   ```

2. **Install dependencies:**
   ```
   npm install
   ```

3. **Start the PHP development server (serves the backend API):**
   ```
   php -S localhost:8000
   ```

4. **Run the Preact/Vite development server (in a new console window or tab):**
   ```
   npm run dev
   ```

5. **Open your browser and visit:**  
   [http://localhost:5173](http://localhost:5173) 

---

## Code Style

PHP code in `public/api/` follows the [PSR-12](https://www.php-fig.org/psr/psr-12/) standard, as configured in [`phpcs.xml.dist`](phpcs.xml.dist).

If you want to lint PHP code locally, install [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer):

```bash
composer global require "squizlabs/php_codesniffer=*"
```

Then run:
```bash
phpcs
```

> **Note:** PHPCS is optional for contributors and not required to run or build the app.

---

## Features

**Front-End:** Free TV Viewer

- Browse and watch curated TV and movie content from the Internet Archive.
- All data and playlists are managed via JSON files.

**Back-End:** Admin Dashboard

- Admin Dashboard for managing content.

---

## Project Structure

---

## Contributing

Contributions are welcome! Please open issues or submit pull requests for bug fixes, new features, or documentation improvements.

---

## License

This code is released under the [MIT](LICENSE) license.