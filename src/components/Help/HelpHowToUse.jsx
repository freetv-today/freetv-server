export function HelpHowToUse() {
  return (
    <section id="using" className="mb-5">
      <h2 className="fs-3 my-5 fw-bold">How to Use Free TV</h2>
      <p>Here is an overview of the Free TV interface and instructions for how to use the app.</p>
      <h4 className="fs-5 mt-5 mb-3 fw-bold">The Free TV Interface</h4>
      <p>(Click an image to see a larger view in a new tab or window)</p>
      <div class="d-flex flex-row w-100 justify-content-start align-middle gap-3 mb-4">
        <div class="text-center text-secondary">
          <a href="/assets/help/help_freetv_ui_lg.png" target="_blank" class="text-decoration-none" title="Click to see full-sized version of Large Screen interface"><img src="/assets/help/help_freetv_ui_lg.png" class="img-fluid border border-2 border-dark" alt="Large Screen User Interface" /></a><br/>Large Screen     
        </div>
        <div class="text-center text-secondary">
          <a href="/assets/help/help_freetv_ui_sm.png" target="_blank" class="text-decoration-none" title="Click to see full-sized version of Small Screen interface"><img src="/assets/help/help_freetv_ui_sm.png" class="img-fluid border border-2 border-dark" alt="Small Screen User Interface" /></a><br/>Small Screen
        </div>
      </div> 
      <h4 className="fs-5 my-4 fw-bold">Watching A Video</h4>
      <ol class="mb-5 fs-5">
        <li><p>The default Playlist starts with "Default TV Shows"</p></li>
        <li><p>Click on a Category from the Category Menu</p></li>
        <li><p>A list of Show Title Buttons will appear after you select a category</p></li>
        <li><p>Click a Show Title Button to watch a video</p></li>
        <li><p>Click the down arrow on each Show Title Button to reveal the Additional Actions Menu</p></li>
      </ol>   
      <h4 className="fs-5 my-4 fw-bold">Changing Playlists</h4>   
      <p>When Free TV starts, it loads the default playlist ("Default TV Shows"). Only one playlist can be loaded at a time. To switch to a different playlist, use the Playlist Selector in the upper right corner. When you select a different playlist, the app will load data and display the categories from the newly-selected playlist.</p>   
      <p><img src="/assets/help/help_change_playlist.png" width="232" title="" /></p> 
      <p>All Favorites and Recently Watched show titles are specific to the playlist that they belong to. So, if the "Default TV Shows" playlist is selected, and you add a show title to your Favorites, you will only see that title when the "Default TV Shows" playlist is selected. If you switch to a different playlist, you will only see Favorites and Recently Watched titles for the selected playlist. As you switch playlists you will see different Favorites and Recently Watched show titles.</p>
      <h4 className="fs-5 my-5 fw-bold">Recently Watched Shows</h4> 
      <p><img src="/assets/recent-black.svg" width="50" className="float-end m-3" title="Recently Watched Icon" alt="Clock Icon" />As you watch videos, they are automatically added to your Recently Watched list. This is a history feature so you can easily find the past show titles you've recently watched. The Recently Watched list is specific to the currently-selected playlist. As you switch playlists you will see different items saved to your Recently Watched history. This list saves up to 25 of your most recently-watched items. You don't have to do anything to add items to this history list. As you watch videos they are added automatically. There is currently no way to remove items from this list but, as you watch new videos they will replace the older items that you watched.</p>
      <h4 className="fs-5 my-5 fw-bold">Favorite Shows</h4> 
      <p className="mb-5"><img src="/assets/heart-black.svg" width="50" className="float-end m-3" title="Favorites Icon" alt="Heart Icon" />The Favorite Shows list is a collection of show titles that you create by manually adding shows to it. By default, Favorite Shows is empty and nothing is added to the list unless you click to add a show title. Do do this, click on the Additional Actions Menu button on a show title button and a menu will appear. From this menu, select "Add to Favorites". You should see a confirmation message letting you know that the show title was added. From this point foward, when you return to the Favorites page, you should see the show title you added.</p>
      <div class="d-flex flex-row w-100 justify-content-start align-middle gap-3 mb-4">
        <div class="text-center text-secondary">
          <a href="/assets/help/help_add_favorites.png" target="_blank" class="text-decoration-none" title="Click to see full-sized version of this image"><img src="/assets/help/help_add_favorites.png" class="img-fluid border border-2 border-dark" alt="Add to Favorites" /></a><br/>Add to Favorites Button    
        </div>
        <div class="text-center text-secondary">
          <a href="/assets/help/help_favorites_list.png" target="_blank" class="text-decoration-none" title="Click to see full-sized version of this image"><img src="/assets/help/help_favorites_list.png" class="img-fluid border border-2 border-dark" alt="Favorites List" /></a><br/>Favorites List
        </div>
      </div> 
      <p>To remove an item from your Favorites, go to the Favorites page and find the item you want to remove. Click the Additional Actions Menu button on the show title and a menu will appear. Click on "Remove from favorites". You should see a confirmation message letting you know that the show title was removed and the item will disappear from your Favorites.</p>
      <p><img src="/assets/help/help_remove_favorite.png" width="451" className="m-3" title="Remove from favorites" alt="Remove from favorites" /></p>
      <h4 className="fs-5 my-5 fw-bold">Searching for Shows</h4> 
      <p><img src="/assets/search-black.svg" width="50" className="float-end m-3" title="Search Icon" alt="Search Icon" />You can search for shows in the currently-selected playlist by going to the Search page. Type a query that is at least 3 characters long and click the Go button. Your search query should not start with generic articles like "The", "A", "But", or "And" as these common words appear in hundreds of titles. Type a relevant keyword related to the show that you are trying to find and click the Go button. So, for example, if you wanted to search for "The Lone Ranger", skip the word "The" and just type "Lone Ranger", or "Lone", or "Ranger" and click Go.</p>
      <p>After you search for a show title, the search function will remember the keyword(s) you searched for. When you return to the Search page in the future, it will remember this query and re-run it for you again. You can navigate to other pages in the application and your query will be saved so that when you come back to Search you can easily find what you are looking for. If you want the Search page to forget your query, click the Clear button. This will erase your search query and stop remembering the value on future visits.</p>
      <h4 className="fs-5 mt-5 mb-3 fw-bold">The Video Player Interface</h4> 
      <p>(Click an image to see a larger view in a new tab or window)</p>
      <div class="d-flex flex-row w-100 justify-content-center align-middle gap-3">
          <div class="text-center text-secondary">
            <a href="/assets/help/help_video_ui_lg.png" target="_blank" class="text-decoration-none" title="Click to see full-sized version of large screen Video Player interface"><img src="/assets/help/help_video_ui_lg.png" class="img-fluid border border-2 border-dark" /></a><br/>Large Screen     
          </div>
          <div class="text-center text-secondary">
            <a href="/assets/help/help_video_ui_sm.png" target="_blank" class="text-decoration-none" title="Click to see full-sized version of small screen Video Player interface"><img src="/assets/help/help_video_ui_sm.png" class="img-fluid border border-2 border-dark" /></a><br/>Small Screen
          </div>
      </div>
      <ol class="my-5 fs-5">
        <li><p>Use the Page Navigation Menu to exit video and visit another page</p></li>
        <li><p>Use the Back button to return to the previous page</p></li>
        <li><p>Use the Episode Playlist button to show/hide the Video Playlist</p></li>
        <li><p>Click the icon or double-click on a playing video to expand to full screen</p></li>
      </ol>    
      <h4 className="fs-5 mt-5 mb-3 fw-bold">Episode Playlist Navigator</h4> 
      <p className="mb-4">On larger screens, the Internet Archive breaks Episode Playlists into smaller "chunks" (or "cards") and only displays a few episodes at a time. This makes it easier to navigate a large Episode Playlist which contains a lot of videos. Typically, only 4 videos at a time will be displayed in the Episode Playlist (see the image below). There are bunch of dots (or circles) which represent all of the groups of episodes in the playlist. The current group is highlighted with a white dot. You can use the left and right arrows on either side of the dots to navigate forward and backward through the groups. You can also click on the dots to jump to the group of episodes that you want to see.</p>
      <p><a href="/assets/help/help_playlist_card_nav-lg.png" target="_blank" class="text-decoration-none" title="Click to see full-sized version of the Episode Playlist navigator on large screens"><img src="/assets/help/help_playlist_card_nav-lg.png" class="img-fluid border border-2 border-dark" alt="Episode Playlist navigator on large screens"/></a></p>
      <p className="my-4">On smaller screens, this Episode Playlist navigator does not appear. All of the episodes available will appear in a (sometimes lengthy) scroll list. You can scroll up and down to select the video episode that you want to watch on small screen displays.</p>
      <p><a href="/assets/help/help_playlist_card_nav-lg.png" target="_blank" class="text-decoration-none" title="Click to see full-sized version of the Episode Playlist scroll list on small screens"><img src="/assets/help/help_playlist_card_nav-sm.png" class="img-fluid border border-2 border-dark" alt="Episode Playlist scroll list on small screens" /></a></p>
      <h4 className="fs-5 mt-5 mb-3 fw-bold">Additional Video Player Options</h4>   
      <ul class="mb-5 fs-5">
        <li>
          <p class="fs-5 fw-bold py-3">Navigating between videos in the Episode Playlist:</p>
          <p><img src="/assets/help/help_episode-playlist-nav.png" class="img-fluid"/></p>
        </li>
        <li>
          <p class="fs-5 fw-bold py-3">Full Screen mode:</p>
          <p><img src="/assets/help/help_fullscreen.png" class="img-fluid"/></p>
          <p>You can also double-click (or tap) anywhere in the video <em>while it is playing</em> to expand to full screen. To exit from full screen mode you can double-click (or tap) again or, if you have a keyboard, press the <kbd>ESC</kbd> key.</p>
        </li>
        <li>
          <p class="fs-5 fw-bold py-3">Picture In Picture (PiP) mode:</p>
          <p><img src="/assets/help/help_pip.png" class="img-fluid"/></p>
          <p>This mode shrinks the video to a small square that you can move around on your screen. This allows you to watch Free TV while you continue to work on other things! Just minimize the browser window while Picture in Picture (PiP) mode is enabled. Whether this icon appears, or works, depends on your device. The PiP feature is typically used for large screen viewing on a desktop or laptop computer. It may not function, or be available on smaller screen mobile devices. </p>
        </li>
        <li>
          <p class="fs-5 fw-bold py-3">Screen casting:</p>
          <p><img src="/assets/help/help_casting.png" class="img-fluid"/></p>
          <p>Screen casting allows you to wirelessly stream video from your local device (e.g. a phone or tablet) to a supported remote device (usually a TV). This typically involves a smart TV or device connected to the TV which supports screen casting. You can learn more about how to <a href="https://support.google.com/chromecast/answer/3228332?hl=en" target="_blank">Cast from Chrome to your TV</a> from Google. There are other instructions available explaining how to <a href="https://www.airdroid.com/screen-mirror/chromecast-from-firefox/" target="_blank">set it up using the Firefox</a> web browser or, by using <a href="https://support.apple.com/en-us/102661" target="_blank">Apple AirPlay</a> with iOS devices.</p>
        </li>
        <li>
          <p class="fs-5 fw-bold py-3">Rewind 10 Seconds:</p>
          <p><img src="/assets/help/help_rewind.png" class="img-fluid"/></p>
        </li>
      </ul>
    </section>
  );
}
