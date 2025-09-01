import PropTypes from 'prop-types';

export function BootstrapAccordion({ items, idPrefix }) {
  return (
    <div class="accordion" id={idPrefix}>
      {items.map((item, idx) => (
        <div class="accordion-item" key={idx}>
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button"
              data-bs-toggle="collapse"
              data-bs-target={`#${idPrefix}-collapse${idx}`}
              aria-expanded="false"
              aria-controls={`${idPrefix}-collapse${idx}`}>
              {item.title}
            </button>
          </h2>
          <div id={`${idPrefix}-collapse${idx}`} class="accordion-collapse collapse" data-bs-parent={`#${idPrefix}`}>
            <div class="accordion-body">
              {item.content}
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}

BootstrapAccordion.propTypes = {
  items: PropTypes.array.isRequired,
  idPrefix: PropTypes.string.isRequired,
};
