import { useConfig } from '@/context/ConfigContext';
import { formatDateTime } from '@/utils';

export function HelpVersionInfo() {
  const { name, lastupdated, version } = useConfig();
  
  return (
    <section id="version" class="mb-5">
      <h2 class="fs-3 mb-5 fw-bold">Version</h2>
      <p>
        App Name: {name}<br/>
        Version: {version}<br/>
        Last updated: {formatDateTime(lastupdated)}
      </p>
    </section>
  );
}
