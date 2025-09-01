import { useConfig } from '@/context/ConfigContext';

/**
 * useDebugLog - Custom hook for conditional debug logging based on config.debugmode
 *
 * Usage:
 *   const log = useDebugLog();
 *   log('This is a debug message');
 *   log('This is a warning', 'warn');
 *   log('This is an error', 'error');
 *   log('Start of group', 'group'); // collapsed group
 *   log('This is a message');
 *   log('', 'end');
 */
export function useDebugLog() {
  const { debugmode } = useConfig();
  /**
   * @param {any} message - The main message to log
   * @param {string} [type] - Optional log type: 'log', 'warn', 'error', 'group', 'end', 'groupCollapsed', 'groupEnd'.
   *   'group' is an alias for 'groupCollapsed', 'end' is an alias for 'groupEnd'.
   * @param {...any} args - Additional arguments to pass to the console method
   */
  return (message, type = 'log', ...args) => {
    if (!debugmode) return;
    let logType = 'log';
    if (typeof type === 'string') {
      if (type === 'group') logType = 'groupCollapsed';
      else if (type === 'end') logType = 'groupEnd';
      else if (['log','warn','error','groupCollapsed','groupEnd'].includes(type)) logType = type;
    }
    if (typeof console[logType] === 'function') {
      console[logType](message, ...args);
    } else {
      console.log(message, ...args);
    }
  };
}