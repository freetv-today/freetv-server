import { NavbarMain } from "@components/Navigation/NavbarMain";
import { CategoriesMenuNav } from "@components/Navigation/CategoriesMenuNav";

export function LayoutSubnav({ children }) {
  return (
    <>
      <NavbarMain />
      <CategoriesMenuNav />
      {children}
    </>
  );
}