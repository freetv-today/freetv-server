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

<br/>

## FreeTV Admin Dashboard

| I want to...                                | What do I do?                                                                                                                                                                                                                                                                                | What happens?                                                                                                                                                   |
| ------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Run only the Admin Dashboard locally**    | In a terminal, navigate to `freetv-server/public`. Start a PHP development server with `php -S localhost:8081`. Then open a **new terminal or terminal tab**, navigate to `freetv-server/`, and run `npm run dev`. The PHP and Vite development servers must both be running simultaneously. | Starts the PHP API on port `8081` and the Admin Dashboard using the default Vite development server port. Vite displays the local URL when it starts.           |
| **Initialize a new FreeTV installation**    | Start the Admin Dashboard and follow the First Run process in the browser.                                                                                                                                                                                                                   | Checks database readiness and allows the installation to be initialized using Start Fresh, Baseline Sample Data, Current Sample Data, or Current Official Data. |
| **Publish database changes for the Viewer** | Use the **Publish** page in the Admin Dashboard.                                                                                                                                                                                                                                             | Converts the current MariaDB-backed Admin data into Viewer-compatible published artifacts.                                                                      |
| **Undo the last publication**               | Use the **Undo** action on the Publish page.                                                                                                                                                                                                                                                 | Restores the previous published Viewer artifacts when an undo point is available.                                                                               |
| **Create a Data Snapshot**                  | Enable the Data Snapshot feature and use the **Data Snapshot** page in the Admin Dashboard.                                                                                                                                                                                                  | Captures a production-data snapshot for later comparison and reconciliation.                                                                                    |
| **Run only the Admin production build**     | Navigate to `freetv-server/` and run `npm run build`.                                                                                                                                                                                                                                        | Creates and validates the Admin Dashboard production frontend build. This does **not** create the complete deployable FreeTV production assembly.               |
| **Configure a custom public directory**     | Set `FREETV_PUBLIC_PATH` in the Admin runtime environment.                                                                                                                                                                                                                                   | Changes the filesystem directory used for public Viewer artifacts, such as `public` locally or `public_html` on hosting environments that use that layout.      |

## FreeTV Viewer

| I want to...                             | What do I do?                                                | What happens?                                                                                                                                                                                                                                                     |
| ---------------------------------------- | ------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Run only the Viewer locally**          | Navigate to `freetv-viewer/` and run `npm run dev`.          | Starts the Viewer using the default Vite development server port. The Viewer can run from local static data without the Admin PHP development server. Backend-dependent actions such as reporting a problem require the appropriate API endpoint to be available. |
| **Run only the Viewer production build** | Navigate to `freetv-viewer/` and run `npm run build`.        | Creates and validates the Viewer production build. This does **not** create the complete deployable FreeTV production assembly.                                                                                                                                   |
| **Change the Viewer deployment path**    | Configure `VITE_BASE_PATH` for the Viewer build environment. | Changes the browser-visible base path used by the built Viewer application.                                                                                                                                                                                       |

## FreeTV Data

| I want to...                                                       | What do I do?                                                  | What happens?                                                                                                                                                      |
| ------------------------------------------------------------------ | -------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Validate FreeTV data before publishing it**                      | Navigate to `freetv-tooling/` and run `npm run data:validate`. | Validates the current data against the FreeTV publication contracts without publishing it. A successful validation provides the GO/NO-GO check before publication. |
| **Publish updated FreeTV data into `freetv-data`**                 | Navigate to `freetv-tooling/` and run `npm run data:publish`.  | Publishes the managed FreeTV data artifacts into the `freetv-data` repository using the Tooling-owned publication workflow.                                        |
| **Build the Current Sample and Current Official dataset packages** | Navigate to `freetv-tooling/` and run `npm run release:build`. | Generates the distributable Current Sample and Current Official First Run packages in the FreeTV data release output.                                              |
| **Clean up the thumbnail collection**                              | Navigate to `freetv-tooling/` and run `npm run clean:thumbs`.  | Runs the managed thumbnail cleanup workflow against the FreeTV data used by Tooling.                                                                               |

## FreeTV Tooling

| I want to...                                         | What do I do?                                              | What happens?                                                                                                                                          |
| ---------------------------------------------------- | ---------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Run the complete FreeTV development environment**  | Navigate to `freetv-tooling/` and run `npm run dev:all`.   | Starts the coordinated Viewer, Admin Dashboard, and PHP development environment.                                                                       |
| **Check the current FreeTV development environment** | Navigate to `freetv-tooling/` and run `npm run status`.    | Reports the current state of the FreeTV repositories and development environment.                                                                      |
| **Build the complete production assembly**           | Navigate to `freetv-tooling/` and run `npm run build:all`. | Builds the Viewer and Admin Dashboard, stages the current data exports, assembles the complete production output, and verifies the resulting assembly. |
| **Verify an existing production assembly**           | Navigate to `freetv-tooling/` and run `npm run verify`.    | Runs the production verification checks against the assembled FreeTV output without rebuilding it.                                                     |

## Production / Deployment

| I want to...                         | What do I do?                                                                               | What happens?                                                                                                                                                               |
| ------------------------------------ | ------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Build the deployable FreeTV site** | Navigate to `freetv-tooling/` and run `npm run build:all`.                                  | Produces and verifies the complete production assembly used for deployment.                                                                                                 |
| **Deploy FreeTV to production**      | Build the production assembly, then follow the production deployment procedure.             | Deploys the verified Admin Dashboard, Viewer, runtime files, and published data to the production environment. Deployment itself is not performed automatically by Tooling. |
| **Configure production paths**       | Configure the production runtime and Vite environment values before building and deploying. | Controls filesystem paths such as `FREETV_PUBLIC_PATH` and browser deployment paths such as `VITE_BASE_PATH`.                                                               |

For detailed Tooling commands and workflows, see the `freetv-tooling` documentation. For information about generated datasets and published data artifacts, see the `freetv-data` documentation.


## Project Structure

```text
Put a tree here
```

## Development


## License

This code is released under the [GPL v3](LICENSE) license.