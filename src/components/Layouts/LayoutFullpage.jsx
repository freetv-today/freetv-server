import { NavbarMain } from "@components/Navigation/NavbarMain";

export function LayoutFullpage({ children }) {
  return (
    <>
      <NavbarMain />
      {children}
    </>
  );
}