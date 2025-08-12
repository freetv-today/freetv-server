import { capitalizeFirstLetter } from "@/utils";

export function SearchResults({ results }) {
  if (!results) return null;
  if (results.length === 0) {
    return <p class="fs-4 text-center text-danger fw-bold my-4">No search results found</p>;
  }
  return (
    <div class="container-fluid my-4">
      <h2 class="fs-2 fw-bold mb-4 text-center">Search Results:</h2>
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Category</th>
            <th>Title</th>
            <th>Year</th>
            <th class="d-none d-md-table-cell">Description</th>
          </tr>
        </thead>
        <tbody>
          {results.map(show => (
            <tr key={show.identifier}>
              <td>{capitalizeFirstLetter(show.category)}</td>
              <td>{show.title}</td>
              <td>{show.start}</td>
              <td class="d-none d-md-table-cell">{show.desc}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}