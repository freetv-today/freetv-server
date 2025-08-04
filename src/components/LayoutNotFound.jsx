import { NavbarMain } from "./NavbarMain";

export function LayoutNotFound({ children }) {
  return (
    <>
      <NavbarMain />
      {children}
    </>
  );
}