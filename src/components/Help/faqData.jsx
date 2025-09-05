import { Link } from '@components/UI/Link';

export const faqItems = [
  {
    title: 'Is this some sort of pirate TV station or IPTV channel?',
    content: (
      <>
        <p><strong>No.</strong> All of the content for this app comes from The Internet Archive: a digital library of Internet sites and other cultural artifacts. Any content on this site can be found by going directly to the Internet Archive and <Link className="fw-bold" href="https://archive.org/details/movies" target="_blank">searching</Link> their archive of shows. This site is simply a hand-curated list of shows organized by category.</p>
        {/* ...rest of content... */}
      </>
    )
  },
  // Add more FAQ items here, or use Lorem Ipsum as placeholder
];
