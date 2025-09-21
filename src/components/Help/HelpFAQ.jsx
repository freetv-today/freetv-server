import { BootstrapAccordion } from '@components/UI/BootstrapAccordion';
import { faqItems } from '@components/Help/faqData';

export function HelpFAQ() {
  return (
    <section id="faq" className="mb-5">
      <h2 className="fs-3 my-5 fw-bold">Frequently Asked Questions</h2>
      <BootstrapAccordion items={faqItems} idPrefix="accordionFAQ" />
    </section>
  );
}
