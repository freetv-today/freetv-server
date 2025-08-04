import { NavbarMain } from "./NavbarMain";

// VidviewLayout.jsx
export function LayoutVidviewer({ children }) {
  return (
    <>
      <NavbarMain />
      {/* Video viewer here */}
      {children}
    </>
  );
}