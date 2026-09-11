# FreeTV Admin Dashboard

<img src="public/assets/freetv.png" align="left" width="100" style="margin: 10px;"> FreeTV Admin Dashboard is a backend interface for managing hand-picked video content which is hosted on the Internet Archive. It works in conjunction with the FreeTV Viewer which displays this content for the end user. The show data is stored in a MariaDB database and exported via a publishing process to be consumed by the FreeTV Viewer. 

The FreeTV Admin Dashboard allows administrators to add new shows and playlists, edit existing shows and playlists, add/edit thumbnail images, and publish Viewer-compatible JSON artifacts. 

<div style="text-align: center; margin-top: 30px;">
<a href="public/assets/freetv-admin-screenshot.jpg" target="_blank" title="Screenshot of FreeTV Admin Dashboard"><img src="public/assets/freetv-admin-screenshot.jpg" width="600"></a>
</div>

## Features

- Foo
- Bar
- Baz
- Bat
- Quux

## Requirements

- NodeJS
- PHP
- MariaDB/MySQL
- Vite
- Preact

<br/>

# How do I ...  ?

The FreeTV project consists of several repositories that can be run independently or together. The tables below provide a quick reference for common development, data, build, and deployment tasks.

## FreeTV Admin Dashboard

| I want to... | What do I do? | What happens? |
| --- | --- | --- |
| **Run only the Admin Dashboard locally** | In a terminal, navigate to `freetv-server/public`. Start a PHP development server with `php -S localhost:8081`. Then open a **new terminal or terminal tab**, navigate to `freetv-server/`, and run `npm run dev`. The PHP and Vite development servers must both be running simultaneously. | Starts the PHP API on port `8081` and the Admin Dashboard using the default Vite development server port. Vite displays the local URL when it starts. |
| **Initialize a new FreeTV installation** | Start the Admin Dashboard and follow the First Run process in the browser. | Checks database readiness and allows the installation to be initialized using Start Fresh, Baseline Sample Data, Current Sample Data, or Current Official Data. Successful initialization returns you to the login screen. |
| **Publish database changes for the Viewer** | Use the **Publish** page in the Admin Dashboard. | Converts the current MariaDB-backed Admin data into Viewer-compatible static artifacts in the configured public directory. |
| **Undo the last publication** | Use the **Undo** action on the Publish page. | Restores the previous published Viewer artifacts when an undo point is available. |
| **Build only the Admin frontend** | Navigate to `freetv-server/` and run `npm run build`. | Builds and validates the Admin Dashboard production frontend. It does not create a complete FreeTV production assembly. |
| **Configure a custom public directory** | Set `FREETV_PUBLIC_PATH` in the Admin runtime environment. | Changes the filesystem directory used for public Viewer artifacts. |

## FreeTV Viewer

| I want to... | What do I do? | What happens? |
| --- | --- | --- |
| **Run, build, or modify the Viewer** | See the [FreeTV Viewer documentation](https://github.com/freetv-today/freetv-viewer). | Explains Viewer development, data sources, builds, and deployment. |

## FreeTV Data

| I want to... | What do I do? | What happens? |
| --- | --- | --- |
| **Understand the distributed FreeTV data** | See the [FreeTV Data documentation](https://github.com/freetv-today/freetv-data). | Explains published Viewer artifacts, thumbnails, SQL packages, datasets, releases, and integrity manifests. |

## FreeTV Tooling

| I want to... | What do I do? | What happens? |
| --- | --- | --- |
| **Run all repositories together** | See the [FreeTV Tooling documentation](https://github.com/freetv-today/freetv-tooling). | Explains the coordinated development environment and repository configuration. |
| **Build or verify a complete production assembly** | See the [FreeTV Tooling documentation](https://github.com/freetv-today/freetv-tooling). | Explains cross-repository builds, assembly, verification, and production output. |

## Production / Deployment

| I want to... | What do I do? | What happens? |
| --- | --- | --- |
| **Build or deploy a complete FreeTV installation** | See the production documentation in [FreeTV Tooling](https://github.com/freetv-today/freetv-tooling). | Explains how the repositories are assembled and prepared for deployment. Tooling does not automatically upload or deploy the result. |

# Architecture

## Data Flow

```mermaid
flowchart TD
    DATA["First Run dataset package"] -->|"Imports SQL"| DB[("MariaDB")]
    DATA -->|"Installs matching artifacts"| ARTIFACTS["Published Viewer artifacts"]
    ADMIN["FreeTV Admin Dashboard"] -->|"Reads and writes"| DB
    DB -->|"Publish"| ARTIFACTS
    ARTIFACTS -->|"Static JSON and thumbnails"| VIEWER["FreeTV Viewer"]
```
MariaDB is the authoritative source for data managed through the FreeTV Admin Dashboard. The Viewer does not read directly from MariaDB. Instead, the Admin publication process generates static JSON and thumbnail artifacts that the Viewer consumes.

First Run establishes both sides of this relationship. It initializes MariaDB and installs the corresponding Viewer artifacts from the selected dataset package. Because the database and Viewer artifacts represent the same initial dataset, a successful First Run leaves the Publish page in a clean state.

After initialization, changes made through the Admin affect MariaDB first. They become visible to the Viewer only after they are published.

## Repository Relationships

The FreeTV repositories have separate responsibilities:

| Repository | Responsibility |
| --- | --- |
| [`freetv-server`](https://github.com/freetv-today/freetv-server) | Provides the FreeTV Admin Dashboard, PHP API, MariaDB-backed management system, First Run process, and publication system. |
| [`freetv-viewer`](https://github.com/freetv-today/freetv-viewer) | Provides the end-user application that consumes published static Viewer artifacts. |
| [`freetv-data`](https://github.com/freetv-today/freetv-data) | Stores the official distributable datasets, Viewer artifacts, SQL packages, release packages, and integrity metadata. |
| [`freetv-tooling`](https://github.com/freetv-today/freetv-tooling) | Coordinates cross-repository development, validation, builds, data workflows, and production assembly. |

The repositories can be developed independently when appropriate. Tasks that cross repository boundaries—such as running the complete development environment or building a production assembly—are owned by `freetv-tooling`.

## Project Structure

```html
put a tree here
🌳
```

# Development


# License

This code is released under the [GPL v3](LICENSE) license.