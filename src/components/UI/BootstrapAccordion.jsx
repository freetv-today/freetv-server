import PropTypes from 'prop-types';

export function BootstrapAccordion({ items, idPrefix }) {
  return (
    <div className="accordion" id={idPrefix}>
      {items.map((item, idx) => (
        <div className="accordion-item" key={idx}>
          <h2 className="accordion-header">
            <button className="accordion-button collapsed" type="button"
              data-bs-toggle="collapse"
              data-bs-target={`#${idPrefix}-collapse${idx}`}
              aria-expanded="false"
              aria-controls={`${idPrefix}-collapse${idx}`}>
              {item.title}
            </button>
          </h2>
          <div id={`${idPrefix}-collapse${idx}`} className="accordion-collapse collapse" data-bs-parent={`#${idPrefix}`}>
            <div className="accordion-body">
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
