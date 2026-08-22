import { useCallback, useEffect, useState } from 'preact/hooks';

const BACKEND_STATUSES = new Set([
  'dependencies_missing',
  'database_config_missing',
  'database_unavailable',
  'schema_missing',
  'ready'
]);

export function useDatabaseReadiness() {
  const [readiness, setReadiness] = useState({
    status: 'checking',
    missingTables: []
  });

  const checkReadiness = useCallback(async () => {
    setReadiness({ status: 'checking', missingTables: [] });

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
          : []
      });
    } catch {
      setReadiness({ status: 'api_unavailable', missingTables: [] });
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
