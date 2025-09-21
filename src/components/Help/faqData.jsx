import { Link } from '@components/Navigation/Link';

export const faqItems = [
  {
    title: 'Is this some sort of pirate TV station or IPTV channel?',
    content: (
      <>
        <p><strong>No.</strong> All of the content for this app comes from The Internet Archive: a digital library of Internet sites and other cultural artifacts. Any content on this site can be found by going directly to the Internet Archive and <Link className="fw-bold" href="https://archive.org/details/movies" target="_blank">searching</Link> their archive of shows. This site is simply a hand-curated list of shows organized by category.</p>
        <figure class="figure p-2 border border-2 border-primary text-end float-md-end me-md-3 w-md-50 m-3">
          <a href="/assets/help/archive-org.png" target="_blank"><img src="/assets/help/archive-org.png" width="350" class="figure-img rounded" /></a>
          <figcaption class="text-center figure-caption">Screenshot of the Internet Archive website</figcaption>
        </figure>
        <p>Television is an ephemeral medium. The Internet Archive began archiving television programs in late 2000, and their first public TV project was an archive of TV news surrounding the events of September 11, 2001. In 2009 they began to make selected U.S. television news broadcasts searchable by captions in their TV News Archive. This service allows researchers and the public to use television as a citable and sharable reference.</p>
        <p>The Internet Archive serves millions of people each day and is one of the top 300 web sites in the world. A single copy of the Internet Archive library collection occupies 145+ Petabytes of server space (and they store at least 2 copies of everything). The Internet Archive is funded through <a href="https://archive.org/donate" target="_blank">donations</a>, grants, and by providing web archiving and book digitization services for their partners. As with most libraries they value the privacy of their patrons, so they avoid keeping the IP (Internet Protocol) addresses of readers or viewers. The Internet Archive is a California non-profit charity that is tax-exempt under section 501c3 of the Internal Revenue Code.</p>
        <p class="text-center my-5">
            <a href="https://archive.org" title="Link opens in a new tab or window" target="_blank" class="btn btn-large btn-outline-dark fw-bold p-4 shadow">Visit the Internet Archive<img src="/assets/external-link.svg" width="15" class="ms-2" /></a>
        </p>
      </>
    )
  },
  {
    title: 'I\'m having problems streaming a show. Help!',
    content: (
      <>
        <p>The first thing to do is to find out if the problem is on your end, or if the problem is coming from the server. You can also check <a href="https://downdetector.com/status/internetarchive/" target="_blank">Downdetector</a> to see if other people have reported issues with the Internet Archive. All of the shows here are streamed directly from the <a href="https://help.archive.org/" target="_blank">Internet Archive</a> web servers. It they having issues with their server (or their internet connection) then you may experience errors while trying to stream video. You can test your Internet connection by going to a different video web site (e.g. youtube.com) and playing some video. If the video from YouTube is working on your device, then the problem is most likely coming from the Internet Archive server.</p>
        <p class="py-4"><a href="https://help.archive.org/help/problems-or-errors/" class="btn btn-large btn-outline-dark fw-bold shadow" target="_blank">Troubleshooting Video Problems and Errors</a></p>
        <p>If you are getting an error from The Internet Archive which says "<b>this item is no longer available</b>" this means that the show was removed from the archive. Please report these to us by using the dropdown menu next to each show title button:</p>
        <p><img src="/assets/help/help_report_problem.png" class="img-fluid" title="How to report problems to us" /></p>
      </>
    )
  },
  {
    title: 'How can I find out more information about a TV show?',
    content: (
      <>
        <p>Next to each show title there is a button with a down arrow. Just click this button to reveal a dropdown menu. From here, you can click on "About this show" to open a pop-up window with a brief summary of the show.</p> 
        <p><img src="/assets/help/help_more_info.png" class="img-fluid" alt="About this show" title="About this show" /></p>
        <p className="my-4">If you click the "IMDB Page" link, it will open up a new tab or window with the <a href="https://www.imdb.com" target="_blank">IMDB</a> page for that TV show where you can look up actors, directors, producers, dates, plot summaries, filming locations, etc. If you click the "Archive.org" or "Download Files" links it will open the Internet Archive site in a new tab or window where you can see more details about the video and download the video files if you want them.</p>
        <p><img src="/assets/help/help_display_info.png" class="img-fluid border border-1 border-dark" alt="More Info" title="More Info" /></p>
      </>
    )
  },
  {
    title: 'Can I download these shows to my local device?',
    content: (
      <>
        <p>Yes, you can download the TV shows from the Internet Archive.</p>                      
        <p>Just click the down arrow on the right side of a show title button. This will show the Additional Actions Menu. If you click on "Download files", it will open the Internet Archive site in a new tab or window which displays the available file formats for download.</p>
        <p class="my-4"><img src="/assets/help/help_download_show.png" class="img-fluid" title="Download option in the Actions Menu" /></p>
        <p>
          While a video is playing, you'll seen an icon on the video player menu bar which looks like a Roman-style building (the Internet Archive logo). Click this icon to go to the details page on the Internet Archive site. If you scroll down on that page, you'll see a section which says "Download Options". Select the video file formats you want and download them directly from the Internet Archive.
        </p>
        <p>
          <img src="/assets/help/more-formats.png" class="img-fluid" title="Click this icon to go to the Internet Archive site" />
        </p>
      </>
    )
  },
  {
    title: 'Some episodes appear out of order. How can I fix this?',
    content: (
      <>
        <p>The list of episodes is coming from a playlist hosted on the Internet Archive. The playlist was created by the original uploader and only they can modify the list. We can't fix it on our end or rearrange the items in the Archive.org playlist.</p>              
        <p>However, you can look at the list of episodes and click the ones you want to watch in any order. The trick is to learn how to read the names of the items in the playlist. For example, you might see a video track listed like this: </p>
        <p><code>S01E26 - Dash to Delaware</code></p>
        <p>This stands for: <b>Season 1</b> (S01), <b>Episode 26</b> (E26)</p>
        <p>It might also be written like this:
            <ol className="my-3" style={{ listStyleType: 'none' }}>
              <li><code>01x03</code> &ndash; <b>Season 1</b>, <b>Episode 03</b></li>
              <li><code>1x10</code> &ndash; <b>Season 1</b>, <b>Episode 10</b></li>
              <li><code>101</code> &ndash; <b>No Season</b>, <b>Episode 101</b></li>
              <li><code>07.</code> &ndash; <b>No Season</b>, <b>Episode 07</b></li>
              <li><code>EP01</code> &ndash; <b>No Season</b>, <b>Episode 01</b></li>
            </ol>
        </p>
        <p>Each uploader to the Internet Archive names their own files in whatever method they want. There is no "standard format". But, you can see the basic pattern of how Seasons and Episodes work in the video file names?</p>              
        <p>The most common issue that people encounter is that Season 2 or Season 3 episodes might appear in the playlist before Season 1 episodes. So, if you encounter this problem, scroll down and find the Season 1 episode you want to watch and click to play. Take a look at this sample video playlist below:</p>
        <p>
          <img src="/assets/help/help_garfield-problem.jpg" class="img-fluid" title="Playlist tracks are out of order" />
        </p>
        <p>As you can see, <code>Season 03, Episode 49</code> is listed first in the playlist, followed by <code>Season 01, Episode 25</code> and <code>Season 01, Episode 26</code>.</p> 
        <p>But, if you scroll down in the same playlist, you will see the first episode: <code>Season 1, Episode 1 &ndash; Peace and Quiet</code>:</p>
        <p>
          <img src="/assets/help/help_garfield-solution.jpg" class="img-fluid" title="Playlist tracks are out of order" />
        </p>
        <p>You can click on this file in the playlist to watch the first episode. Using this method, you can jump around to different tracks in the playlist and watch the show episodes in the correct order. Some of the shows on the Internet Archive are DVD rips which contain extras. Often times, these extras appear in the playlist above the episode tracks. Try scrolling down, finding the season and episode you want, and clicking to play in the order you want.</p>
      </>
    )
  },
  {
    title: 'How can I contact you about Free TV?',
    content: (
      <>
        <p>If you are trying to contact me about removing shows from the list, please note that I do not host any of this content. I can prevent a show from appearing in the Free TV list but, since I don't host the files, I cannot remove them from the server. If you're trying to make a DMCA or copyright claim about a TV show, please direct it to the <a href="https://archive.org/about/terms.php" target="_blank">Internet Archive</a>:</p>
        <p class="my-4 font-monospace">
          Internet Archive<br/>
          300 Funston Ave.<br/>
          San Francisco, CA 94118<br/>
          Phone: 415-561-6767<br/>
          Email: info@archive.org<br/>
        </p>
        <p>Otherwise, if you want to request new features or shows, or tell us about typos, or bugs, or ask questions, you can send us an email:</p>
        <p><a href="mailto:support@freetv.today" className="font-monospace fw-bold">support@freetv.today</a></p>
      </>
    )
  }        
];
