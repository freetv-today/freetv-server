import { Link } from '@components/Navigation/Link';
import { createPath } from '@/utils/env';

export function ButtonAdminSearchNav() {
  return (
    <Link href={createPath('/dashboard/search')} className="btn btn-sm icon-btn search-icon me-1 me-lg-2" title="Search">
      &nbsp;
    </Link>
  );
}