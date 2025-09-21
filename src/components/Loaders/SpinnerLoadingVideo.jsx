
export function SpinnerLoadingVideo({ title }) {
  return (
    <div
      style={{
        background: '#FFF',
        display: 'flex',
        height: '70vh',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: 2000,
      }}
    >
      <div id="vidspin" className="text-center">
        <h2 className="text-dark mb-4 fs-3">Loading...</h2>
        <h3 className="text-success fw-bold mb-2">{title}</h3>
        <h4 className="mb-2 fw-bold text-danger">Please be patient!</h4>
        <p className="text-dark mb-3">Large archives take awhile to load.</p>
        <div
          className="spinner-border text-dark"
          role="status"
          style={{ width: '8rem', height: '8rem' }}
        >
          <span className="visually-hidden">Loading Video</span>
        </div>
      </div>
    </div>
  );
}