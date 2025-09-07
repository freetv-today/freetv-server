import { Link } from '@components/Navigation/Link';

export function ButtonAdminSearchNav() {
  return (
    <Link href="/dashboard/search" className="btn btn-sm icon-btn search-icon me-2" title="Search">
      &nbsp;
    </Link>
  );
}