// src/components/UI/OfflinePage.jsx
export function OfflinePage() {
  return (
    <div className="container-fluid text-center">
      <h1 className="display-5">Free TV is Temporarily Offline</h1>
      <p>
        <a href="/">
          <img src="/src/assets/offline-cloud.svg" width="175" alt="Offline" title="Offline" />
        </a>
      </p>
      <h4 className="fs-5">We'll be back soon!</h4>
    </div>
  );
}