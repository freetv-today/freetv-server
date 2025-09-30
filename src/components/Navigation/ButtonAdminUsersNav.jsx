import { Link } from '@components/Navigation/Link';

export function ButtonAdminUsersNav() {
  return (
    <Link href="/dashboard/users" className="btn btn-sm icon-btn users-icon me-1 me-lg-2" title="User Manager">
      &nbsp;
    </Link>
  );
}