import { BootstrapAccordion } from '@components/Help/BootstrapAccordion';
import { faqItems } from '@components/Help/faqData';

export function HelpFAQ() {
  return (
    <section id="faq">
      <h2 className="mb-5 fs-3 fw-bold">Frequently Asked Questions:</h2>
      <BootstrapAccordion items={faqItems} idPrefix="accordionFAQ" />
    </section>
  );
}
