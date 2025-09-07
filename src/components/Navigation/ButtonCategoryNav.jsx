
import { Link } from '@/components/Navigation/Link';

export function ButtonCategoryNav({ name, category, isActive }) {
  return (
    <Link
      id={`navbtn_${category}`}
      className={`btn btn-lg btn-outline-dark me-2 p-2 fw-bold ${isActive ? 'active' : ''}`}
      href={`/category/${category}`}
    >
      {name}
    </Link>
  );
}