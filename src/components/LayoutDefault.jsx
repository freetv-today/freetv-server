import { NavbarMain } from "./NavbarMain";
import { CategoriesMenuNav } from "./CategoriesMenuNav";

export function LayoutDefault({ children }) {
  return (
    <>
      <NavbarMain />
      <CategoriesMenuNav />
      {children}
    </>
  );
}