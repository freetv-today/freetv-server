// src/context/ShowDataContext.jsx
import { createContext } from 'preact';
import { useContext } from 'preact/hooks';

// Create a context with a default value (null for now)
const ShowDataContext = createContext(null);

// Hook to access the context easily
export function useShowData() {
  const showData = useContext(ShowDataContext);
  if (!showData) {
    throw new Error('useShowData must be used within a ShowDataProvider');
  }
  return showData;
}

// Provider component to wrap the app
export function ShowDataProvider({ showData, children }) {
  return (
    <ShowDataContext.Provider value={showData}>
      {children}
    </ShowDataContext.Provider>
  );
}