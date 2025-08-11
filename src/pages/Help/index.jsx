import { useEffect } from 'preact/hooks';
import { Link } from '@components/UI/Link';
import { useConfig } from '@/context/ConfigContext.jsx';

export function Help() {
    const { debugmode } = useConfig();

	useEffect(() => {
		document.title = "Free TV: Help";
		if (debugmode) {
			console.log('Rendered Help page (pages/Help/index.jsx)');
		}
	}, [debugmode]);

	return (
        <div class="mt-5">

            <div class="container mt-0 mb-5">

            <h1 class="fs-1 fw-bold mb-4">Free TV Help</h1>

            <p class="fst-italic text-danger" style="padding: 20px; border: 2px dashed red; border-radius: 24px;"><b>If you're having problems</b> streaming videos, it could indicate a problem with the Internet Archive server or their internet connection. The best thing to do is to verify that your device is working by playing a <Link class="fw-bold" href="https://youtube.com" target="_blank">YouTube</Link> video. If the YouTube video plays correctly, <b>it could mean that the Internet Archive servers are having temporary problems</b>. The best thing to do is wait awhile and try again later. Free TV is dependent on the Internet Archive servers to stream videos. <b>If they're having problems, so are we!</b></p>

            <p class="text-center my-5">
                <Link href="https://downdetector.com/status/internetarchive/" target="_blank" class="btn btn-lg btn-danger shadow border border-1 border-dark" title="Open new tab or window showing Internet Archive Network Status">
                    <img src="/src/assets/health-white.svg" height="40" class="me-2" />
                    Internet Archive Network Status
                </Link>
            </p>

            <h1 class="fs-1 fw-bold mt-5 mb-4">About Free TV:</h1>

            <p class="lead">Free TV is an app to watch videos featuring a hand-picked list of TV shows and movies from the <Link class="fw-bold" href="https://archive.org" target="_blank">Internet Archive</Link>.</p> 
            
            <p>All shows are grouped by category. A viewer can select a category, see a list of shows, and click to watch all available episodes for free. Since Free TV is a video streaming app, you'll have to be connected to the internet to stream (or play) videos. Shows listed on Free TV are never downloaded to your device. There's no sign up, or log in process and, we don't store any of your personal information. Videos are streamed over a secure connection directly from the Internet Archive servers to your device.</p>

            <ul class="mt-4 mb-0">
                <li><Link href="/help" class="fw-bold fs-5">Frequently Asked Questions</Link></li>
                <li><Link href="/help" class="fw-bold fs-5">Version Information</Link></li>
            </ul>

            <br/>
            <hr/>

            <h2 class="fs-3 my-5 fw-bold">The Free TV Interface:</h2>

            <div class="d-flex flex-row w-100 justify-content-center align-middle gap-3 mb-4">
                <div class="text-center text-secondary">
                    Small Screen<br/>
                    <Link href="/src/assets/help_freetv_ui_sm.png" target="_blank" class="text-decoration-none" title="Click to see full-sized version of Small Screen interface"><img src="/src/assets/help_freetv_ui_sm.png" class="img-fluid border border-2 border-dark" /></Link>
                </div>
                <div class="text-center text-secondary">
                    Large Screen<br/>
                    <Link href="/src/assets/help_freetv_ui_lg.png" target="_blank" class="text-decoration-none" title="Click to see full-sized version of Large Screen interface"><img src="/src/assets/help_freetv_ui_lg.png" class="img-fluid border border-2 border-dark" /></Link>     
                </div>
            </div>

            <ol class="mb-5 fs-5">
                <li><p>The Playlist starts with "Default TV Shows".</p></li>
                <li><p>To get started, click a Category from the Category Menu at the top of the page.</p></li>
                <li><p>A list of show title buttons will appear in the Category Titles menu.</p></li>
                <li><p>Click a Category Title button to watch the video.</p></li>
                <li><p>Click the down arrow on each Category Title button to reveal Additional Actions menu.</p></li>
            </ol>

            <h4 class="mt-2 mb-4 fs-5 fw-bold">About Playlists:</h4>

            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Quisque auctor nulla et condimentum vulputate. Aenean dignissim massa eu dolor semper pellentesque. Aliquam auctor turpis sit amet sapien finibus, et blandit magna scelerisque. Vivamus mi tortor, condimentum sit amet gravida a, egestas id quam. In eu arcu augue. Donec at bibendum massa. In malesuada dui eget metus porta, nec mollis justo hendrerit. Nunc ornare tristique dolor eget consectetur. Fusce pharetra vitae nisi et scelerisque.</p>
            
            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Quisque auctor nulla et condimentum vulputate. Aenean dignissim massa eu dolor semper pellentesque. Aliquam auctor turpis sit amet sapien finibus, et blandit magna scelerisque. Vivamus mi tortor, condimentum sit amet gravida a, egestas id quam. In eu arcu augue. Donec at bibendum massa. In malesuada dui eget metus porta, nec mollis justo hendrerit. Nunc ornare tristique dolor eget consectetur. Fusce pharetra vitae nisi et scelerisque.</p>

            <br/>

            <hr/>

            <h3 class="fs-3 my-5 fw-bold">Video Player Interface:</h3>


            <div class="d-flex flex-row w-100 justify-content-center align-middle gap-3">
                <div class="text-center text-secondary">
                    Small Screen<br/>
                    <Link href="/src/assets/help_video_ui_sm.png" target="_blank" class="text-decoration-none" title="Click to see full-sized version of Small Screen interface"><img src="/src/assets/help_video_ui_sm.png" class="img-fluid border border-2 border-dark"/></Link>
                </div>
                <div class="text-center text-secondary">
                    Large Screen<br/>
                    <Link href="/src/assets/help_video_ui_lg.png" target="_blank" class="text-decoration-none" title="Click to see full-sized version of Large Screen interface"><img src="/src/assets/help_video_ui_lg.png" class="img-fluid border border-2 border-dark" /></Link>     
                </div>
            </div>

            <ol class="my-5 fs-5">
                <li><p>Use the Page Navigation Menu to exit video and visit another page</p></li>
                <li><p>Use the Back button to return to the previous page</p></li>
                <li><p>Use the Playlist button to show/hide the Video Playlist</p></li>
                <li><p>Click the icon or double-click a playing video to expand to full screen</p></li>
            </ol>

            <hr/>

            <h4 class="mt-5 mb-3 fs-4 fw-bold">Additional Video Player Options:</h4>

            <ul class="mb-5 fs-5">
                <li>
                    <p class="fs-5 fw-bold py-3">Navigating between videos in playlist:</p>
                    <p><img src="/src/assets/help_playlisthidden-nav.png" class="img-fluid" /></p>
                </li>
                <li>
                    <p class="fs-5 fw-bold py-3">Full Screen mode:</p>
                    <p><img src="/src/assets/help_fullscreen.png" class="img-fluid" /></p>
                    <p>You can also double-click (or tap) anywhere in the video <em>while it is playing</em> to expand to full screen. To exit from full screen mode you can double-click (or tap) again or, if you have a keyboard, press the <kbd>ESC</kbd> key.</p>
                </li>
                <li>
                    <p class="fs-5 fw-bold py-3">Picture In Picture (PiP) mode:</p>
                    <p><img src="/src/assets/help_pip.png" class="img-fluid" /></p>
                    <p>This mode shrinks the video to a small square that you can move around on your screen. This allows you to watch Free TV while you continue to work on other things! Just minimize the browser window while Picture in Picture (PiP) mode is enabled. Whether this icon appears, or works, depends on your device. The PiP feature is typically used for large screen viewing on a desktop or laptop computer. It may not function, or be available on small screen mobile devices. </p>
                </li>
                <li>
                    <p class="fs-5 fw-bold py-3">Screen casting:</p>
                    <p><img src="/src/assets/help_casting.png" class="img-fluid" /></p>
                    <p>Screen casting allows you to wirelessly stream video from your local device (e.g. a phone or tablet) to a supported remote device (usually a TV). This typically involves a smart TV or device connected to the TV which supports screen casting. You can learn more about how to <Link class="fw-bold" href="https://support.google.com/chromecast/answer/3228332?hl=en" target="_blank">Cast from Chrome to your TV</Link> from Google. There are other instructions available explaining how to <Link class="fw-bold" href="https://www.airdroid.com/screen-mirror/chromecast-from-firefox/" target="_blank">set it up using the Firefox</Link> web browser or, by using <Link class="fw-bold" href="https://support.apple.com/en-us/102661" target="_blank">Apple AirPlay</Link> with iOS devices.</p>
                </li>
                <li>
                    <p class="fs-5 fw-bold py-3">Rewind 10 Seconds:</p>
                    <p><img src="/src/assets/help_rewind.png" class="img-fluid" /></p>
                </li>
            </ul>

        </div>

        <br/>
        <br/>

        <div class="container my-5">

            <h2 class="mb-5 fs-3 fw-bold">Frequently Asked Questions:</h2>

            <div class="accordion" id="accordionFAQ">

                    <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                        Is this some sort of pirate TV station or IPTV channel?
                        </button>
                    </h2>
                    <div id="collapseOne" class="accordion-collapse collapse" data-bs-parent="#accordionFAQ">
                        <div class="accordion-body">

                        <p><strong>No.</strong> All of the content for this app comes from The Internet Archive: a digital library of Internet sites and other cultural artifacts. Any content on this site can be found by going dirctly to the Internet Archive and <Link class="fw-bold" href="https://archive.org/details/movies" target="_blank">searching</Link> their archive of shows. This site is simply a hand-curated list of shows organized by category.</p>

                        <figure class="figure p-2 border border-2 border-primary text-end float-md-end me-md-3 w-md-50 m-3">
                            <Link class="fw-bold" href="/src/assets/archive-org.png" target="_blank"><img src="/src/assets/archive-org.png" width="350" class="figure-img rounded" /></Link>
                            <figcaption class="text-center figure-caption">Screenshot of the Internet Archive website</figcaption>
                        </figure>

                        <p>Television is an ephemeral medium. The Internet Archive began archiving television programs in late 2000, and their first public TV project was an archive of TV news surrounding the events of September 11, 2001. In 2009 they began to make selected U.S. television news broadcasts searchable by captions in their TV News Archive. This service allows researchers and the public to use television as a citable and sharable reference.</p>

                        <p>The Internet Archive serves millions of people each day and is one of the top 300 web sites in the world. A single copy of the Internet Archive library collection occupies 145+ Petabytes of server space (and they store at least 2 copies of everything). The Internet Archive is funded through <Link class="fw-bold" href="https://archive.org/donate" target="_blank">donations</Link>, grants, and by providing web archiving and book digitization services for their partners. As with most libraries they value the privacy of their patrons, so they avoid keeping the IP (Internet Protocol) addresses of readers or viewers. The Internet Archive is a California non-profit charity that is tax-exempt under section 501c3 of the Internal Revenue Code.</p>

                        <p class="text-center my-5">
                            <Link href="https://archive.org" title="Link opens in a new tab or window" target="_blank" class="btn btn-large btn-outline-dark fw-bold p-4 shadow">Visit the Internet Archive<img src="/src/assets/external-link.svg" width="15" class="ms-2" /></Link>
                        </p>

                        </div>
                    </div>
                    </div>
                    <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                        I'm having problems streaming a show. Help!
                        </button>
                    </h2>
                    <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionFAQ">
                        <div class="accordion-body">
                        <p>The first thing to do is to find out if the problem is on your end, or if the problem is coming from the server. You can also check <Link class="fw-bold" href="https://downdetector.com/status/internetarchive/" target="_blank">Downdetector</Link> to see if other people have reported issues with the Internet Archive. All of the shows here are streamed directly from the <Link class="fw-bold" href="https://help.archive.org/" target="_blank">Internet Archive</Link> web servers. It they having issues with their server (or their internet connection) then you may experience errors while trying to stream video. You can test your Internet connection by going to a different video web site (e.g. youtube.com) and playing some video. If the video from YouTube is working on your device, then the problem is most likely coming from the Internet Archive server.</p>
                        <p class="py-4"><Link href="https://help.archive.org/help/problems-or-errors/" class="btn btn-large btn-outline-dark fw-bold shadow" target="_blank">Troubleshooting Video Problems and Errors</Link></p>
                        <p>If you are getting an error from The Internet Archive which says "<b>this item is no longer available</b>" this means that the show was removed from the archive. Please report these to us by using the dropdown menu next to each show title button:</p>
                        <p><img src="/src/assets/help_report_problem.png" class="img-fluid" title="How to report problems to us" /></p>
                        </div>
                    </div>
                    </div>
                    <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                        How can I find out more information about a TV show?
                        </button>
                    </h2>
                    <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#accordionFAQ">
                        <div class="accordion-body">
                        <p>Next to each show title there is a button with a down arrow. Just click this button to reveal a dropdown menu. From here, you can click on "About this show" to see a brief summary of the show.</p> 
                        <p>If you click the "IMDB Page" button, it will open up a new tab or window with the <Link class="fw-bold" href="https://www.imdb.com" target="_blank">IMDB</Link> page for that TV show where you can look up actors, directors, dates, plot summaries, etc.</p>
                        <p><img src="/src/assets/help_more_info.png" class="img-fluid" alt="More Info" title="More Info" /></p>
                        <p>If you click the Archive.org or Download buttons it will take you to the Internet Archive where you can see details about the currently-playing video and download files. If you are having problems playing a video, you can click the "Report a problem" button to let us know about it.</p>
                        </div>
                    </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                            Can I download these shows to my local device?
                            </button>
                        </h2>
                        <div id="collapseFour" class="accordion-collapse collapse" data-bs-parent="#accordionFAQ">
                            <div class="accordion-body">

                                <p>Yes, you can download the TV shows from the Internet Archive.</p> 
                                    
                                <p><b>While you're using Free TV</b>, you can click the down arrow on the right side of every show title button. This will show the Actions Menu. If you click on "Download", it will open a page on the Internet Archive site which displays available formats and options.</p>

                                <p class="my-4"><img src="/src/assets/help_download_show.png" class="img-fluid" title="Download option in the Actions Menu" /></p>
                                <p>
                                    <b>While a video is playing</b>, you should seen an icon on the video player menu bar and in the upper-right corner which looks like a Roman-style building (the Internet Archive logo). Click this icon to go to the details page on the Internet Archive site. If you scroll down on that page, you'll see a section which says "Download Options". Select your file format and download the TV shows you want.
                                </p>
                                <p>
                                    <img src="/src/assets/more-formats.png" class="img-fluid" title="Click this icon to go to the Internet Archive site" />
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                            Some episodes appear out of order. How can I fix this?
                            </button>
                        </h2>
                        <div id="collapseFive" class="accordion-collapse collapse" data-bs-parent="#accordionFAQ">
                            <div class="accordion-body">
                                <p>The list of episodes is coming from a playlist hosted on the Internet Archive. The playlist was created by the original uploader and only they can modify the list. We can't fix it on our end or rearrange the list items in the Archive.org playlist.</p>
                                
                                <p>However, you can look at the list of episodes and click the ones you want to watch in any order. The trick is to learn how to read the names of the items in the playlist. For example, you might see a video track listed like this: </p>

                                <p><code>S01E26 - Dash to Delaware</code></p>
                                <p>This stands for: <b>Season 1</b> (S01), <b>Episode 26</b> (E26)</p>
                                <p>It might also be written like this:</p>
                                
                                <ol>
                                    <li>01x03 &ndash; <b>Season 1</b>, <b>Episode 03</b></li>
                                    <li>1x10 &ndash; <b>Season 1</b>, <b>Episode 10</b></li>
                                    <li>101 &ndash; <b>No Season</b>, <b>Episode 101</b></li>
                                    <li>07. &ndash; <b>No Season</b>, <b>Episode 07</b></li>
                                    <li>EP01 &ndash; <b>No Season</b>, <b>Episode 01</b></li>
                                </ol>
                                

                                <p>Each uploader to the Internet Archive names their own files in whatever method they want. There is no "standard format". But, you can see the basic pattern of how Seasons and Episodes works?</p>
                                
                                <p>The most common issue that people encounter is that Season 2 or Season 3 episodes might appear in the playlist before Season 1 episodes. So, if you encounter this problem, scroll down and find the Season 1 episode you want to watch and click to play. Take a look at this sample video playlist below:</p>
                                <p>
                                    <img src="/src/assets/help_garfield-problem.jpg" class="img-fluid" title="Playlist tracks are out of order" />
                                </p>
                                <p>As you can see, <code>Season 03, Episode 49</code> is listed first in the playlist, followed by <code>Season 01, Episode 25</code> and <code>Season 01, Episode 26</code>.</p> 
                                <p>But, if you scroll down in the same playlist, you will see the first episode: <code>Season 1, Episode 1 &ndash; Peace and Quiet</code>:</p>
                                <p>
                                    <img src="/src/assets/help_garfield-solution.jpg" class="img-fluid" title="Playlist tracks are out of order" />
                                </p>
                                <p>You can click on this file in the playlist to watch the first episode. Using this method, you can jump around to different tracks in the playlist and watch the show episodes in the correct order. Some of the shows on the Internet Archive are DVD rips which contain extras. Often times, these extras appear in the playlist above the episode tracks. Try scrolling down, finding the season and episode you want, and clicking to play in the order you want.</p>
                            </div>
                        </div>
                    </div>               
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSix" aria-expanded="false" aria-controls="collapseSix">
                            How can I contact you about Free TV?
                            </button>
                        </h2>
                        <div id="collapseSix" class="accordion-collapse collapse" data-bs-parent="#accordionFAQ">
                            <div class="accordion-body">
                                <p>If you are trying to contact me about removing shows from the list, please note that I do not host any of this content. I can prevent a show from appearing in the Free TV list but, since I don't host the files, I cannot remove them from the server. If you're trying to make a DMCA or copyright claim about a TV show, please direct it to the <Link class="fw-bold" href="https://archive.org/about/terms.php" target="_blank">Internet Archive</Link>:</p>
                                <p class="my-4 font-monospace">
                                    Internet Archive<br/>
                                    300 Funston Ave.<br/>
                                    San Francisco, CA 94118<br/>
                                    Phone: 415-561-6767<br/>
                                    Email: info@archive.org<br/>
                                </p>
                                <p>Otherwise, if you want to request new features or shows, or tell  me about typos, or bugs, or ask questions, you can reach me by email.<br/><br/>admin@freetv.today
                                </p>
                            </div>
                        </div>
                    </div>                

            </div>

            <p class="text-center my-5">
                <Link href="/help" class="fw-bold btn btn-sm btn-primary">Back to Top &#9650;</Link>
            </p>

            <br/><br/><br/><br/><br/>

            <div class="my-5 text-center">
                <h1 class="display-4 bruno-ace">Free TV</h1>
                <Link class="fw-bold" href="/"><img src="/src/assets/freetv.png" width="185" title="Free TV" alt="Free TV Logo" /></Link>
            </div>

            <div class="my-5 fs-4 fw-bold text-center">
                <Link href="https://archive.org" class="fw-bold link-dark text-decoration-none" target="_blank" title="Powered by the Internet Archive">
                    Powered by the Internet Archive 
                    <img src="/src/assets/internet_archive-black.svg" width="65" title="The Internet Archive" alt="The Internet Archive" class="ms-2" />
                </Link>
            </div>

            <div id="versionInfo" class="text-secondary opacity-75 text-center" style="font-size: small;"></div>
            <div id="dbInfo" class="text-secondary opacity-75 text-center" style="font-size: small;"></div>

            <p class="text-center my-4">
                <Link href="/help" class="fw-bold btn btn-sm btn-primary">Back to Top &#9650;</Link>
            </p>

        </div>

        </div>
	);
}