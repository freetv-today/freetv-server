import { BootstrapAccordion } from '@components/UI/BootstrapAccordion';
import { AnnouncementItems } from '@components/Help/AnnouncementData';

export function HelpAnnouncement() {
  return (
    <>
      <section id="announcements" className="mb-4">
        <BootstrapAccordion items={AnnouncementItems} idPrefix="accordionAnnouncement" />
      </section>
    </>
  );
}
