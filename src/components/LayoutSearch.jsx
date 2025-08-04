import { NavbarMain } from "./NavbarMain";
import { SearchQueryComponent } from './SearchQueryComponent';

export function LayoutSearch({ children }) {
  return (
    <>
      <NavbarMain />
      <SearchQueryComponent />
      {children}
    </>
  );
}