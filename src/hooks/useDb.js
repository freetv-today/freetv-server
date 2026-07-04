// src/hooks/useDb.js
import { showRepo } from '@/db/repositories/showRepo.js';

/**
 * useDb - Provides access to database repositories.
 * Note: mysql2 is server-side only. For client-side Preact, call via API endpoints.
 */
export function useDb() {
  return {
    showRepo,
    // playlistRepo, etc. when ready
  };
}

// Direct exports for signals / non-hook usage
export { showRepo };
export * from '@/db/index.js';