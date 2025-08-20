import { Link } from '@components/UI/Link.jsx';

export function ButtonAdminProblemsNav({ count }) {
  return (
    <Link
      href="/dashboard/problems"
      class="btn btn-sm icon-btn problems-icon me-2 position-relative"
      title="Admin Dashboard Problems"
    >
      {count > 0 && (
        <span
          className="position-absolute start-100 translate-middle badge rounded-pill bg-danger"
          style={{ top: '18%' }}
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