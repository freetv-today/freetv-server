import { NavbarMain } from "./NavbarMain";
import { CategoriesMenuNav } from "./CategoriesMenuNav";

export function LayoutSubnav({ children }) {
  return (
    <>
      <NavbarMain />
      <CategoriesMenuNav />
      {children}
    </>
  );
}