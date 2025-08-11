// src/context/AlertContext.jsx
import { createContext } from 'preact';
import { useContext, useState } from 'preact/hooks';
import { ShowAlerts } from '@components/Alerts/ShowAlerts';

/**
 * @typedef {Object} Alert
 * @property {number} id
 * @property {string} [type]
 * @property {string} [heading]
 * @property {string} [body]
 */

/**
 * @typedef {Object} AlertContextType
 * @property {Alert[]} alerts
 * @property {(alert: Omit<Alert, 'id'>) => void} addAlert
 * @property {(id: number) => void} removeAlert
 */

/** @type {import('preact').Context<AlertContextType>} */
const AlertContext = createContext({
  alerts: [],
  addAlert: () => {},
  removeAlert: () => {},
});

/**
 * @param {{ children: import('preact').ComponentChildren }} props
 */
export function AlertProvider({ children }) {
  const [alerts, setAlerts] = useState([]);

  // Add a new alert
  const addAlert = (alert) => {
    console.log('Adding alert:', alert); // Debug log
    setAlerts((prev) => [...prev, { id: Date.now(), ...alert }]);
  };

  // Remove an alert by ID
  const removeAlert = (id) => {
    console.log('Removing alert with id:', id); 
    setAlerts((prev) => prev.filter((alert) => alert.id !== id));
  };

  if (alerts.length > 0) {
    console.log('Array of alerts to display: ', alerts); 
  } else {
    console.log('There are no alerts to display');
  }
  
  
  return (
    <AlertContext.Provider value={{ alerts, addAlert, removeAlert }}>
      <div>
        {alerts.map((alert) => (
          <ShowAlerts key={alert.id} alert={alert} onDismiss={() => removeAlert(alert.id)} />
        ))}
        {children}
      </div>
    </AlertContext.Provider>
  );
}

/** @returns {AlertContextType} */
export function useAlert() {
  return useContext(AlertContext);
}