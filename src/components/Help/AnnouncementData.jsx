export const AnnouncementItems = [
  {
    title: 'Having problems viewing videos? Click here!',
    content: (
      <>
        <p className="fst-italic text-danger" style={{padding: 20, border: '2px dashed red', borderRadius: 24}}>
            <b>If you're having problems</b> streaming videos, it could indicate a problem with the Internet Archive server. The best thing to do is to verify that your device is working by playing a <a className="fw-bold" href="https://youtube.com" target="_blank">YouTube</a> video. If the YouTube video plays correctly, it means that your internet connection is working properly. <b>This could indicate that the Internet Archive servers are having temporary problems</b>. The best thing to do in this situations is wait awhile and then try again later. Free TV is dependent on the Internet Archive servers to stream videos. <b>If they're having problems, so are we!</b> Click the button below to open a new window to view the status of the Internet Archive servers (as reported by other users).
        </p>
        <div className="text-center my-5">
            <a href="https://downdetector.com/status/internetarchive/" target="_blank" className="btn btn-lg btn-danger shadow border border-1 border-dark" title="Open new tab or window showing Internet Archive Network Status">
            <img src="/assets/help/health-white.svg" height="40" className="me-2" />
                Check Internet Archive Network Status
            </a>
        </div>
      </>
    )
  }
];
