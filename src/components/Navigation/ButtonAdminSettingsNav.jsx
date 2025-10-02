import { Link } from '@components/Navigation/Link';
import { createPath } from '@/utils/env';

export function ButtonAdminSettingsNav() {
  return (
    <Link href={createPath('/dashboard/settings')} className="btn btn-sm icon-btn settings-icon me-1 me-lg-2" title="Settings">
      &nbsp;
    </Link>
  );
}