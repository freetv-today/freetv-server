import { Link } from '@components/UI/Link';

export function HelpAbout() {
  return (
    <>
      <h1 className="fs-1 fw-bold mt-5 mb-4">About Free TV:</h1>
      <p className="lead">Free TV is an app to watch videos featuring a hand-picked list of TV shows and movies from the <Link className="fw-bold" href="https://archive.org" target="_blank">Internet Archive</Link>.</p>
      <p>All shows are grouped by category. A viewer can select a category, see a list of shows, and click to watch all available episodes for free. Since Free TV is a video streaming app, you'll have to be connected to the internet to stream (or play) videos. Shows listed on Free TV are never downloaded to your device. There's no sign up, or log in process and, we don't store any of your personal information. Videos are streamed over a secure connection directly from the Internet Archive servers to your device.</p>
    </>
  );
}
