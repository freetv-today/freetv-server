import { NavbarMain } from '@components/Navigation/NavbarMain';

export function LayoutNotFound({ children }) {
  return (
    <>
      <NavbarMain />
      {children}
    </>
  );
}