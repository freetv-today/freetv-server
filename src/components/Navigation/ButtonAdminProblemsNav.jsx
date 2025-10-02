import { Link } from '@components/Navigation/Link';
import { createPath } from '@/utils/env';

export function ButtonAdminProblemsNav({ count }) {
  return (
    <Link
      href={createPath('/dashboard/problems')}
      className="btn btn-sm icon-btn problems-icon me-1 me-lg-2 position-relative"
      title="Problems"
    >
      {count > 0 && (
        <span
          className="position-absolute start-100 translate-middle badge rounded-pill bg-danger"
          style={{ top: '17%' }}
          title={`There are ${count} problems`}
        >
          {count}
          <span className="visually-hidden">Problems</span>
        </span>
      )}
      &nbsp;
    </Link>
  );
}