const DATABASE_COPY = {
  database_config_missing: 'MariaDB access has not been configured for FreeTV.',
  database_unavailable: 'FreeTV could not connect to MariaDB using the configured credentials.',
  database_permissions_insufficient: 'FreeTV can connect to MariaDB, but the configured account cannot '
    + 'perform the database, table, or data operations required to initialize FreeTV.',
  schema_missing: 'MariaDB is reachable, but the FreeTV schema has not been imported or is incomplete.'
};

function RetryButton({ onRetry }) {
  return (
    <button type="button" className="btn btn-primary" onClick={onRetry}>
      Try Again
    </button>
  );
}

export function DatabaseReadinessPage({ status, missingTables = [], onRetry }) {
  if (status === 'api_unavailable') {
    return (
      <div className="container py-5" style={{ maxWidth: 760 }}>
        <div className="alert alert-danger shadow-sm" role="alert">
          <h2 className="alert-heading h4">Backend Unavailable</h2>
          <p>
            FreeTV could not reach its PHP backend. Make sure the PHP server is running from the
            FreeTV Server <code>public/</code> directory and that the API proxy is configured correctly.
          </p>
          <RetryButton onRetry={onRetry} />
        </div>
      </div>
    );
  }

  if (status === 'dependencies_missing') {
    return (
      <div className="container py-5" style={{ maxWidth: 760 }}>
        <div className="alert alert-warning shadow-sm" role="alert">
          <h2 className="alert-heading h4">PHP Dependencies Missing</h2>
          <p>
            Composer dependencies are not installed. Run <code>composer install</code> from the
            FreeTV Server repository, then try again.
          </p>
          <RetryButton onRetry={onRetry} />
        </div>
      </div>
    );
  }

  const detail = DATABASE_COPY[status] || DATABASE_COPY.database_config_missing;

  return (
    <div className="container py-5" style={{ maxWidth: 760 }}>
      <div className="card shadow-sm">
        <div className="card-body p-4 p-md-5">
          <h2 className="text-danger mb-3">Database Setup Required</h2>
          <p className="lead">{detail}</p>

          {status === 'schema_missing' && missingTables.length > 0 && (
            <p className="small text-muted">
              Missing tables: {missingTables.join(', ')}
            </p>
          )}

          {status === 'database_permissions_insufficient' ? (
            <>
              <p>
                FreeTV does not install or configure MariaDB or database accounts. Verify MariaDB
                independently before troubleshooting FreeTV further.
              </p>
              <p>Your account must support a basic MariaDB “hello world” workflow:</p>
              <ul>
                <li>Create a database if your environment permits it, or use your assigned database</li>
                <li>Create a table</li>
                <li>Insert a row</li>
                <li>Read that row back</li>
              </ul>
            </>
          ) : (
            <>
              <p>
                FreeTV does not install or configure MariaDB, phpMyAdmin, or database accounts.
                Before continuing, you must have:
              </p>
              <ul>
                <li>MariaDB running</li>
                <li>A MariaDB user/account for FreeTV</li>
                <li>FreeTV database credentials configured</li>
              </ul>

              <h3 className="h5 mt-4">Import the FreeTV schema with phpMyAdmin</h3>
              <ol>
                <li>Open phpMyAdmin.</li>
                <li>Choose Import.</li>
                <li>Select the supplied FreeTV SQL schema.</li>
                <li>Run the import.</li>
                <li>Return here and click Try Again.</li>
              </ol>
            </>
          )}
          <p className="small text-muted">
            If MariaDB, phpMyAdmin, or a database account is not available, follow the documentation
            for your operating system, hosting provider, MariaDB, or phpMyAdmin.
          </p>

          <RetryButton onRetry={onRetry} />
        </div>
      </div>
    </div>
  );
}
