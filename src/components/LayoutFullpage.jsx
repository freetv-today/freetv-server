import { NavbarMain } from "./NavbarMain";

export function LayoutFullpage({ children }) {
  return (
    <>
      <NavbarMain />
      {children}
    </>
  );
}