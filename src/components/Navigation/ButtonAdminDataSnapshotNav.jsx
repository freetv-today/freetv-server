import { Link } from '@components/Navigation/Link';
import { createPath } from '@/utils/env';

export function ButtonAdminDataSnapshotNav() {
  return (
    <Link
      href={createPath('/dashboard/data-snapshot')}
      className="btn btn-sm icon-btn snapshot-icon me-1 me-lg-2"
      title="Data Snapshot"
    >
      &nbsp;
    </Link>
  );
}
