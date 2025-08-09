import { NavbarMain } from "@components/Navigation/NavbarMain";

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