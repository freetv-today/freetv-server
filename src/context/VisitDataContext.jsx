import { createContext } from 'preact';
import { useVisitBeacon } from '@/hooks/useVisitBeacon';
import { useConfig } from '@/context/ConfigContext';

export const VisitDataContext = createContext({});

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
