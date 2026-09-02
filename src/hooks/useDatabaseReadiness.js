import { useCallback, useEffect, useState } from 'preact/hooks';

const BACKEND_STATUSES = new Set([
  'dependencies_missing',
  'database_config_missing',
  'database_unavailable',
  'database_missing',
  'database_permissions_insufficient',
  'schema_missing',
  'initialization_required',
  'ready'
]);

export function useDatabaseReadiness() {
  const [readiness, setReadiness] = useState({
    status: 'checking',
    missingTables: [],
    databaseMode: null
  });

  const checkReadiness = useCallback(async () => {
    setReadiness({ status: 'checking', missingTables: [], databaseMode: null });

    try {
      const response = await fetch('/api/admin/readiness.php', {
        method: 'GET',
        cache: 'no-store'
      });
      const data = await response.json();

      if (!data || !BACKEND_STATUSES.has(data.status)) {
        throw new Error('Unexpected readiness response');
      }

      setReadiness({
        status: data.status,
        missingTables: data.status === 'schema_missing' && Array.isArray(data.missing_tables)
          ? data.missing_tables
          : [],
        databaseMode: data.database_mode === 'create_database'
          || data.database_mode === 'existing_database'
          ? data.database_mode
          : null
      });
    } catch {
      setReadiness({ status: 'api_unavailable', missingTables: [], databaseMode: null });
    }
  }, []);

  useEffect(() => {
    checkReadiness();
  }, [checkReadiness]);

  return {
    ...readiness,
    retry: checkReadiness
  };
}
