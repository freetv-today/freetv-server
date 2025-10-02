import { Link } from '@components/Navigation/Link';
import { createPath } from '@/utils/env';

export function ButtonAdminUsersNav() {
  return (
    <Link href={createPath('/dashboard/users')} className="btn btn-sm icon-btn users-icon me-1 me-lg-2" title="User Manager">
      &nbsp;
    </Link>
  );
}