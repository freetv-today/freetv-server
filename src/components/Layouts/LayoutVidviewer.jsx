// DEV NOTE: this layout uses different navbar with buttons related to video playback
import { NavbarVideoPlayback } from '@components/Navigation/NavbarVideoPlayback';

// VidviewLayout.jsx
export function LayoutVidviewer({ children }) {
  return (
    <>
      <NavbarVideoPlayback />
      {children}
    </>
  );
}