import { Link } from '@components/Navigation/Link';

export function ButtonFavoritesNav() {
  return (
    <Link href="/favorites" className="btn btn-sm icon-btn favorites-icon me-2" title="Favorites">
      &nbsp;
    </Link>
  );
}