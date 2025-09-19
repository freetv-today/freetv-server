import { createContext } from 'preact';
import { useVisitBeacon } from '@/hooks/useVisitBeacon';
import { useConfig } from '@/context/ConfigContext';

/**
 * VisitDataContext - Context for visit/session analytics data.
 * Currently provides an empty object; can be extended to expose visitData.
 * Use VisitDataProvider to wrap your app or relevant subtree.
 */

export const VisitDataContext = createContext({});

/**
 * VisitDataProvider - Context provider for visit/session analytics.
 * @param {Object} props
 * @param {import('preact').ComponentChildren} props.children
 */

export function VisitDataProvider({ children }) {
  const config = useConfig();
  useVisitBeacon(config);
  // No value needed unless you want to expose visitData
  return (
    <VisitDataContext.Provider value={{}}>
      {children}
    </VisitDataContext.Provider>
  );
}
