// src/components/Navigation/NavbarBlank.jsx
import { ImageSmallLogo } from '@components/UI/ImageSmallLogo';

// this navbar is displayed on admin login page and has no navigation icons
export function NavbarBlank() {
  return (
    <nav id="navbar" className="navbar navbar-dark bg-dark fixed-top">
      <div className="container-fluid p-0 m-0 d-flex justify-content-center">

        <ImageSmallLogo />

      </div>
    </nav>
  )
}