import { NavbarMain } from "@components/Navigation/NavbarMain";
import { SearchQueryComponent } from '@components/UI/SearchQueryComponent';

export function LayoutSearch({ children }) {
  return (
    <>
      <NavbarMain />
      <SearchQueryComponent />
      {children}
    </>
  );
}