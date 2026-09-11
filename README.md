# FreeTV Admin Dashboard

<img src="public/assets/freetv.png" align="left" width="100" style="margin: 10px;"> FreeTV Admin Dashboard is a backend interface for managing hand-picked video content which is hosted on the the Internet Archive. It works in conjunction with the FreeTV Viewer which displays this content for the end user. The show data is stored in a MySQL/Maria database and exported via a publishing process to be consumed by the FreeTV Viewer. 

The FreeTV Admin Dashboard allows administrators to add new shows and playlists, edit existing shows and playlists, add/edit thumbnail images, and publish the content in JSON format. 

<img src="public/assets/freetv-admin-screenshot.jpg" width="600">

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

## How do I ...  ???

The FreeTV project consists of several repositories that can be run independently or together at the same time. The table below provides a quick reference for common development, data, build, and deployment tasks.

| I want to...                                                       | What do I do?                                                                                                                                                                                                                                                                                | What happens?                                                                                                                                                                                                                                                         |
| ------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Run only the Admin Dashboard locally**                           | In a terminal, navigate to `freetv-server/public`. Start a PHP development server with `php -S localhost:8081`. Then open a **new terminal or terminal tab**, navigate to `freetv-server/`, and run `npm run dev`. The PHP and Vite development servers must both be running simultaneously. | Starts the PHP API on port `8081` and the Admin Dashboard using the default Vite development server port. Vite displays the local URL when it starts.                                                                                                                 |
| **Run only the Viewer locally**                                    | Navigate to `freetv-viewer/` and run `npm run dev`.                                                                                                                                                                                                                                          | Starts the Viewer using the default Vite development server port. The Viewer can run from its local static data without the Admin PHP development server. Backend-dependent actions such as reporting a problem require the appropriate API endpoint to be available. |
| **Run the complete FreeTV development environment**                | Navigate to `freetv-tooling/` and run `npm run dev:all`.                                                                                                                                                                                                                                     | Starts the coordinated Viewer, Admin Dashboard, and PHP development environment. Tooling manages the development configuration needed to run the applications together.                                                                                               |
| **Check the current FreeTV development environment**               | Navigate to `freetv-tooling/` and run `npm run status`.                                                                                                                                                                                                                                      | Reports the current state of the FreeTV repositories and development environment.                                                                                                                                                                                     |
| **Validate FreeTV data before publishing it**                      | Navigate to `freetv-tooling/` and run `npm run data:validate`.                                                                                                                                                                                                                               | Validates the current data against the FreeTV publication contracts without publishing it. A successful validation provides the GO/NO-GO check before publication.                                                                                                    |
| **Publish updated FreeTV data**                                    | Navigate to `freetv-tooling/` and run `npm run data:publish`.                                                                                                                                                                                                                                | Publishes the validated Admin data into the managed Viewer/data artifacts using the FreeTV publication workflow.                                                                                                                                                      |
| **Build the Current Sample and Current Official dataset packages** | Navigate to `freetv-tooling/` and run `npm run release:build`.                                                                                                                                                                                                                               | Generates the distributable Current Sample and Current Official First Run packages in the FreeTV data release output.                                                                                                                                                 |
| **Clean up the thumbnail collection**                              | Navigate to `freetv-tooling/` and run `npm run clean:thumbs`.                                                                                                                                                                                                                                | Runs the managed thumbnail cleanup workflow against the FreeTV data used by Tooling.                                                                                                                                                                                  |
| **Build the complete production assembly**                         | Navigate to `freetv-tooling/` and run `npm run build:all`.                                                                                                                                                                                                                                   | Builds the Viewer and Admin Dashboard, stages the current data exports, assembles the complete production output, and verifies the resulting assembly.                                                                                                                |
| **Verify an existing production assembly**                         | Navigate to `freetv-tooling/` and run `npm run verify`.                                                                                                                                                                                                                                      | Runs the production verification checks against the assembled FreeTV output without rebuilding it.                                                                                                                                                                    |
| **Run only the Admin production build**                            | Navigate to `freetv-server/` and run `npm run build`.                                                                                                                                                                                                                                        | Creates and validates the Admin Dashboard production frontend build. This does **not** create the complete deployable FreeTV production assembly.                                                                                                                     |
| **Run only the Viewer production build**                           | Navigate to `freetv-viewer/` and run `npm run build`.                                                                                                                                                                                                                                        | Creates and validates the Viewer production build. This does **not** create the complete deployable FreeTV production assembly.                                                                                                                                       |

For detailed Tooling commands and workflows, see the `freetv-tooling` documentation. For information about generated datasets and published data artifacts, see the `freetv-data` documentation.


## Project Structure

```text
Put a tree here
```

## Development


## License

This code is released under the [GPL v3](LICENSE) license.