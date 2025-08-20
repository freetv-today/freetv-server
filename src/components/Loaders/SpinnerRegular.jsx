// This is a simple Bootstrap spinner with no graphics

// DEV NOTE: NOT CURRENTLY USED!

export function SpinnerRegular() {
  return (
    <div id="spinner" class="text-center">
        <h2 class="text-secondary mb-3 font-monospace">Loading...</h2>
      <div class="spinner-border" style="width: 5rem; height: 5rem;" role="status"></div>
    </div> 
  );
}