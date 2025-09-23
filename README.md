# Free TV Server

<img src="public/assets/freetv.png" width="120" style="margin-bottom: 20px;">

See it online at: https://freetv.today

**Version 2.1.1 - Beta**

Free TV Server contains both a front-end site for end users and a back-end administration tool (the Admin Dashboard) for managing content.

The front-end site allows users to watch free TV and movie content from the Internet Archive.

The administrator can log into the Admin Dashboard and add/remove shows, create playlists, fetch thumbnails, modify playlist data, and change configuration settings.

This is the main server for the Free TV project which provides centralized content management. This server is the main source for playlist data (categories and shows) which are consumed by stand-alone client apps.

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

- Web-based dashboard for managing shows, playlists, thumbnails, and server configuration.
- Supports adding/removing/editing TV shows and movies.
- Allows creation and management of playlists.
- Fetch and manage thumbnails.
- Modify playlist data and configuration settings.

---

## Project Structure

A brief overview of the main directories and files:

- **public/**
  - `api/` – Backend PHP scripts serving as the API for the frontend and admin dashboard.
  - `assets/` – Static assets such as images and icons (including the main logo).
  - `config.json` – Server configuration file.
  - `playlists/` – JSON files containing TV/movie playlists consumed by the frontend.
  - `temp/` – Temporary files and cache.
  - `thumbs/` – Thumbnail images for shows and movies.
  - `tools/` – Utility scripts and tools for admins/devs.

- **src/**
  - `adsense.js` – Handles ad integration for the frontend.
  - `components/` – Reusable UI components for the Preact frontend.
  - `context/` – Context providers for app-wide state management.
  - `hooks/` – Custom hooks for reusable logic.
  - `index.jsx` – Main entry point for the frontend app.
  - `pages/` – Route-level components for different app pages.
  - `signals/` – Preact Signals for state management.
  - `style.css` – Global styles for the frontend.
  - `utils.js` – General utility functions.

- **Other important files:**
  - `.gitignore` – Specifies files and directories ignored by Git.
  - `LICENSE` – Open source license (MIT).
  - `package.json` – Project metadata and dependencies.
  - `phpcs.xml.dist` – Coding standards for PHP code (PSR-12).
  - `vite.config.js` – Vite configuration for development and build.
  - `eslint.config.js` – ESLint configuration for JavaScript/Preact code.
  - `index.html` – The main HTML file served by Vite.

---

## Development

- **Linting:**  
  JavaScript/Preact code can be linted using ESLint (configured in `eslint.config.js`).  
  PHP code can be linted using PHPCS (see [Code Style](#code-style)).

- **Building for Production:**  
  To build the front-end for production, run:
  ```
  npm run build
  ```

- **Preview Production Build:**  
  ```
  npm run preview
  ```

---

## Contributing

Contributions are welcome! Please open issues or submit pull requests for bug fixes, new features, or documentation improvements.

---

## License

This code is released under the [MIT](LICENSE) license.