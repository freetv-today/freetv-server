import { computed, signal } from '@preact/signals';

export const publicationStatusSignal = signal({
  status: null,
  loading: false,
  error: null,
});

export const hasUnpublishedChangesSignal = computed(() => {
  const status = publicationStatusSignal.value.status;

  return Boolean(
    status?.playlists?.some(item => item.changed === true)
    || status?.config?.changed === true
    || status?.default_playlist?.changed === true
  );
});

let latestRequestId = 0;

export async function refreshPublicationStatus() {
  const requestId = ++latestRequestId;
  publicationStatusSignal.value = {
    ...publicationStatusSignal.value,
    loading: true,
    error: null,
  };

  try {
    const response = await fetch('/api/admin/publication/status.php');
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.message || 'Could not load publication status');
    }
    if (!result.status || !Array.isArray(result.status.playlists)) {
      throw new Error('Invalid publication status response');
    }

    if (requestId !== latestRequestId) return null;

    publicationStatusSignal.value = {
      status: result.status,
      loading: false,
      error: null,
    };
    return result.status;
  } catch (error) {
    if (requestId !== latestRequestId) return null;

    publicationStatusSignal.value = {
      ...publicationStatusSignal.value,
      loading: false,
      error: error instanceof Error ? error.message : 'Could not load publication status',
    };
    return null;
  }
}
