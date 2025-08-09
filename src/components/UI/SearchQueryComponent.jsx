
export function SearchQueryComponent() {
  return (
    <div class="w-100 border-bottom border-2 border-dark p-3">
        <div class="w-75 input-group mx-auto">
            <input id="searchquery" type="text" class="form-control rounded fw-bold fs-5 ps-2" title="Type your keywords here" placeholder="Search..." style="min-width: 200px;" />
            <button id="searchbutton" type="button" title="Run the search" class="ms-2 mt-1 p-0 py-1 px-2 rounded fw-bold btn btn-sm btn-success gobtn">GO</button>
            <button class="ms-2 mt-1 p-0 py-1 px-2 rounded fw-bold btn btn-sm btn-danger" title="Clear previous search queries">CLEAR</button>
        </div>
    </div>
  );
}