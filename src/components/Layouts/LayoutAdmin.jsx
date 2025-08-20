import { NavbarAdmin } from "@components/Navigation/NavbarAdmin.jsx";
import { useProblemCount } from "@/hooks/useProblemCount.js";
import '/public/pages/admin/admin.css';

export function LayoutAdmin ({ children }) {
  const problemCount = useProblemCount();
  return (
    <>
      <NavbarAdmin problemCount={problemCount} />
      {children}
    </>
  );
}