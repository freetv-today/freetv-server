import { NavbarBlank } from '@components/Navigation/NavbarBlank';

export function LayoutFullpageBlank({ children }) {
  return (
    <>
      <NavbarBlank />
      {children}
    </>
  );
}