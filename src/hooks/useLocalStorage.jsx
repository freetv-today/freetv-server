import { useState } from 'preact/hooks';

export function useLocalStorage(key, initialValue) {

  const [storedValue, setStoredValue] = useState(() => {
    try {
      const item = window.localStorage.getItem(key);
      return item ? JSON.parse(item) : initialValue;
    } catch (err) {
      console.warn(`useLocalStorage: Error reading key "${key}" from localStorage:`, err);
      return initialValue;
    }
  });

  const setValue = value => {
    try {
      let latestValue = storedValue;
      // Always get the latest value from localStorage for functional updates
      if (value instanceof Function) {
        const item = window.localStorage.getItem(key);
        latestValue = item ? JSON.parse(item) : initialValue;
      }
      const valueToStore = value instanceof Function ? value(latestValue) : value;
      setStoredValue(valueToStore);
      window.localStorage.setItem(key, JSON.stringify(valueToStore));
    } catch (err) {
      console.warn(`useLocalStorage: Error saving key "${key}" to localStorage:`, err);
    }
  };
  return [storedValue, setValue];
}