import { ButtonHomeNav } from '@components/Navigation/ButtonHomeNav';
import { ButtonRecentNav } from '@components/Navigation/ButtonRecentNav';
import { ButtonSearchNav } from '@components/Navigation/ButtonSearchNav';
import { ButtonFavoritesNav } from '@components/Navigation/ButtonFavoritesNav';
import { ButtonHelpNav } from '@components/Navigation/ButtonHelpNav';

// blank navbar - no navigation icons just logo
export function MainNavIcons() {
  return (
    <div id="iconmenu" className="d-none d-md-flex flex-row align-items-center order-md-1">
      <ButtonHomeNav />
      <ButtonRecentNav />
      <ButtonSearchNav />
      <ButtonFavoritesNav />
      <ButtonHelpNav />
    </div>
  );
}