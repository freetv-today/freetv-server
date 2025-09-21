import { useConfig } from '@/context/ConfigContext';
import { formatDateTime, getAppInfo } from '@/utils';

export function HelpVersionInfo() {

  const { lastupdated } = useConfig();
  const appInfo = getAppInfo();
  
  return (
    <section id="version" className="mb-5">
      <h2 className="fs-3 my-5 fw-bold">Version Information</h2>
      <p>
        App Name: {appInfo.name}<br/>
        Version: {appInfo.version}<br/>
        Last updated: {formatDateTime(lastupdated)}
      </p>
    </section>
  );
}
