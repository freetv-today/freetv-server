
export function ErrorPage({ type = 'Error', message = 'Something went wrong. Please try again later.' }) {
  return (
    <div className="text-center text-danger fw-bold p-4" style={{ marginTop: '100px' }}>
      <h3 className="display-5">{type}</h3>
      <p>{message}</p>
      <img src="/src/assets/sadface.svg" alt="😢" width="100" /> 
      <p><a href="/" className="btn btn-primary">Reload</a></p>
    </div>
  );
}