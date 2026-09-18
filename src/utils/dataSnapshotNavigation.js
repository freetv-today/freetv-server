export function shouldShowDataSnapshotNavigation(isAdmin, flagValue) {
  return isAdmin && flagValue === 'true';
}
