import { ImageSmallLogo } from '@components/UI/ImageSmallLogo';

// blank navbar - no navigation icons just logo
export function NavbarBlank() {
  return (
    <nav id="navbar" className="navbar navbar-dark bg-dark fixed-top">
      <div className="container-fluid p-0 m-0 d-flex justify-content-center">
        <ImageSmallLogo />
      </div>
    </nav>
  )
}