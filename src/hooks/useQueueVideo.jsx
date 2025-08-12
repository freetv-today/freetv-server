import { useLocalStorage } from '@hooks/useLocalStorage';
import { useConfig } from '@/context/ConfigContext.jsx';
import { useLocation } from 'preact-iso';

/**
 * Returns a function to queue a video and save it to recently watched.
 * Usage: const { queueVideo } = useQueueVideo();
 */
export function useQueueVideo() {
  const { debugmode } = useConfig();
  const [, setCurrentVid] = useLocalStorage('currentVid', null);
  const [recentTitles, setRecentTitles] = useLocalStorage('recentTitles', { title: [] });
  const { route } = useLocation();

  // Save to recently watched (max 25, no duplicates)
  const saveRecent = (title) => {
    setRecentTitles(prev => {
      let titlesArr = Array.isArray(prev?.title) ? prev.title : [];
      titlesArr = titlesArr.filter(t => t !== title);
      titlesArr.unshift(title);
      if (titlesArr.length > 25) titlesArr = titlesArr.slice(0, 25);
      return { title: titlesArr };
    });
    if (debugmode) {
      console.log('Adding to Recently Watched');
    }
  };

  // Main function to queue video and save to recent
  const queueVideo = ({ category, identifier, title }) => {
    if (!category || !identifier || !title) {
      if (debugmode) {
        console.error('Missing required data: (category, identifier, title)');
      }
      return;
    }
    setCurrentVid({ category, identifier, title });
    saveRecent(title);
    if (debugmode) {
      console.log(`Queuing video: ${title.replace(/_/g, ' ')}`);
    }
    route('/nowplaying');
  };

  return { queueVideo };
}