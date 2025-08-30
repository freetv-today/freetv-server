import { NavbarMain } from "@components/Navigation/NavbarMain";
import { CategoriesMenuNav } from '@components/Navigation/CategoriesMenuNav';

export function LayoutDefault({ children }) {
  return (
    <>
      <NavbarMain />
      <CategoriesMenuNav />
      {children}
    </>
  );
}