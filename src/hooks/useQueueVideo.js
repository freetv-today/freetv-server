import { useLocalStorage } from '@hooks/useLocalStorage';
import { logShowView } from '@/utils';
import { useLocation } from 'preact-iso';
import { useDebugLog } from '@hooks/useDebugLog';

/**
 * Custom hook to queue a video and add it to recently watched.
 * Usage: const { queueVideo } = useQueueVideo();
 */
export function useQueueVideo() {

  const log = useDebugLog();
  const [, setCurrentVid] = useLocalStorage('currentVid', null);
  const [, setRecentTitles] = useLocalStorage('recentTitles', { title: [] });  const { route } = useLocation();

  // Save to recently watched (max 25, no duplicates)
  const saveRecent = (title) => {
    setRecentTitles(prev => {
      let titlesArr = Array.isArray(prev?.title) ? prev.title : [];
      titlesArr = titlesArr.filter(t => t !== title);
      titlesArr.unshift(title);
      if (titlesArr.length > 25) titlesArr = titlesArr.slice(0, 25);
      return { title: titlesArr };
    });
  };

  // Main function to queue video and save to recent
  const queueVideo = ({ imdb, category, identifier, title }) => {
    if (!imdb || !category || !identifier || !title) {
      log('Missing required data: (imdb, category, identifier, title)','error');
      return;
    }
    setCurrentVid({ imdb, category, identifier, title });
    logShowView(imdb, title, category);
    log(`Queuing video: ${title.replace(/_/g, ' ')}`);
    saveRecent(title);
    log('Adding to recently-watched list');
    route('/nowplaying');
  };

  return { queueVideo };
}