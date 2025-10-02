import { Link } from '@components/Navigation/Link';
import { createPath } from '@/utils/env';

export function ButtonAdminHomeNav() {
  return (
    <Link href={createPath('/dashboard')} className="btn btn-sm icon-btn castle-icon me-1 me-lg-2" title="Admin Dashboard">
      &nbsp;
    </Link>
  );
}