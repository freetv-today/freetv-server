# Free TV

<img src="/src/assets/freetv.png" width="120" style="margin-bottom: 20px;">

**Version 2.1.0 - Beta**

An application for viewing free TV and movie content from the Internet Archive. All data is stored in JSON format and streams directly from https://archive.org so no files are downloaded to your local machine.

See it online at: https://freetv.today

---

## Table of Contents

- [Getting Started](#getting-started)
- [Features](#features)
- [Modules & Extensibility](#modules--extensibility)
- [Project Structure](#project-structure)
- [Development](#development)
- [Contributing](#contributing)
- [License](#license)

---

## Getting Started

- Click on a Category button.
- A list of shows for that category will appear.
- Click a show title button to load the video.
- Use the Drop-Down list to select a different playlist.

---

## Features

- Browse and watch curated TV and movie content from the Internet Archive.
- All data and playlists are managed via JSON files.
- Modular design: easily add or remove features (e.g., reporting, favorites).
- Admin Dashboard (PHP) for managing content and modules (coming soon).

---

## Modules & Extensibility

Modules are self-contained features (e.g., reporting, favorites) that can be enabled or disabled via configuration.

---

## Development

1. Clone the repo.
2. Install dependencies: `npm install`
3. Start the dev server: `npm run dev`
4. For admin features, set up a PHP server in /public/

---

## Contributing

Contributions are welcome! Please open issues or submit pull requests for bug fixes, new features, or documentation improvements.

---

## License

[MIT](LICENSE)