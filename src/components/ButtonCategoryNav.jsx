// src/components/ButtonCategoryNav.jsx
export function ButtonCategoryNav({ name, category }) {
  return (
    <>
      <a
        id={`navbtn_${category}`}
        class="btn btn-lg btn-outline-dark me-2 p-2 fw-bold"
        href={`#${category}`}
      >
        {name}
      </a>
    </>
  );
}