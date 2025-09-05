import { Link } from '@components/UI/Link';

export function ButtonAdminUsersNav() {
  return (
    <Link href="/dashboard/users" className="btn btn-sm icon-btn users-icon me-2" title="User Manager">
      &nbsp;
    </Link>
  );
}