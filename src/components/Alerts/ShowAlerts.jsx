// src/components/ShowAlerts.jsx
import { LayoutFullpage } from '@components/Layouts/LayoutFullpage';
import { useLocation } from 'preact-iso';

// Map alert types to Bootstrap classes and icon paths
const alertConfig = {
  success: {
    bootstrapClass: 'alert-success',
    icon: '/src/assets/tv-icon-green.svg',
    title: 'Success!',
  },
  info: {
    bootstrapClass: 'alert-info',
    icon: '/src/assets/tv-icon-blue.svg',
    title: 'Info',
  },
  warn: {
    bootstrapClass: 'alert-warning',
    icon: '/src/assets/tv-icon-yellow.svg',
    title: 'Warning',
  },
  error: {
    bootstrapClass: 'alert-danger',
    icon: '/src/assets/tv-icon-red.svg',
    title: 'Error!',
  },
  blue: {
    bootstrapClass: 'alert-primary',
    icon: '/src/assets/tv-icon-blue.svg',
    title: 'Message',
  },
  default: {
    bootstrapClass: 'alert-primary',
    icon: '/src/assets/freetv.png',
    title: 'Free TV',
  },
};

/**
 * @param {{ alert?: { type?: string; heading?: string; body?: string }; onDismiss?: () => void }} props
 */
export function ShowAlerts({ alert = {}, onDismiss }) {
  const { route } = useLocation();
  const { type = 'default', heading = 'Alert', body = 'An error occurred.' } = alert;

  // Get configuration based on alert type
  const { bootstrapClass, icon, title } = alertConfig[type] || alertConfig.default;

  // Handle dismiss button click
  const handleDismiss = () => {
    if (onDismiss) {
      onDismiss(); // Call onDismiss for context usage
    } else {
      route('/'); // Fallback to navigation for direct usage
    }
  };

  return (
    <LayoutFullpage>
      <div className="container mt-3">
        <div className={`alert alert-dismissible fade show ${bootstrapClass}`} role="alert">
          <h3 className="alert-heading">{heading}</h3>
          <p>{body}</p>
          <button
            type="button"
            className="btn-close"
            aria-label="Close"
            onClick={handleDismiss}
          />
        </div>
        <div className="text-center">
          <img src={icon} width="200" title={title} alt={title} />
        </div>
      </div>
    </LayoutFullpage>
  );
}