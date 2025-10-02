import { createPath } from "@/utils/env";

export function SpinnerLoadingAppData() {
  return (
    <div id="spinner" className="text-center mt-5">
        <h2 className="text-secondary mb-4 fs-1 font-monospace">Loading App Data...</h2>
        <img src={createPath('/assets/square-loader.gif')} width="160" height="24" />
    </div> 
  );
}