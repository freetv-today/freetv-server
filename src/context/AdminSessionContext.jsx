import { createContext } from 'preact';
import { useContext } from 'preact/hooks';

const AdminSessionContext = createContext(null);

const ROLE_RANK = {
  viewer: 1,
  editor: 2,
  admin: 3,
};

export function hasMinimumRole(user, minimumRole) {
  return Boolean(
    user
    && ROLE_RANK[user.role]
    && ROLE_RANK[minimumRole]
    && ROLE_RANK[user.role] >= ROLE_RANK[minimumRole]
  );
}

export function AdminSessionProvider({ user, children }) {
  return (
    <AdminSessionContext.Provider value={user}>
      {children}
    </AdminSessionContext.Provider>
  );
}

export function useAdminAuth() {
  const user = useContext(AdminSessionContext);

  return {
    user,
    isAdmin: hasMinimumRole(user, 'admin'),
    canEditContent: hasMinimumRole(user, 'editor'),
    canManageReports: hasMinimumRole(user, 'editor'),
    canManageThumbnails: hasMinimumRole(user, 'editor'),
  };
}
