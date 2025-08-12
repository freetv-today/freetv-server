import { NavbarMain } from "@components/Navigation/NavbarMain";

export function LayoutSearch({ children }) {
  return (
    <>
      <NavbarMain />
      {children}
    </>
  );
}