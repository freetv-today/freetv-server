# Admin Dashboard Roadmap

## Major Features To Implement

1. **Search**
   - Reuse `SearchQueryComponent.jsx` from the frontend.
   - Create a custom `SearchResults` component for the admin dashboard to display results in an admin-friendly layout.

2. **User Manager**
   - CRUD interface for managing users stored in `public/assets/apdata.key`.
   - Admin can add, edit, and delete users.

3. **Settings**
   - Manage app preferences and settings (front and back end).
   - Includes items from `config.json` and preferences like default playlist.
   - Use Bootstrap toggle switches for boolean values.

4. **Problems**
   - Integrate with `public/api/report-problem.php` to allow admin to address reported problematic titles.
   - Use `src/hooks/useProblemCount.js` to display a badge with the number of problems.
   - More detailed spec to be determined.

5. **Create New Playlist**
   - Feature to create a new playlist from the admin dashboard.
   - Implementation details to be determined.

---

## Add Video Page Enhancements

1. **Thumbnail Fetching**
   - Add ability to fetch and manage thumbnails for videos.

2. **IMDB Search**
   - Integrate IMDB search to help populate video metadata.

3. **Save and Add More**
   - Add a "Save and Add More" button to allow adding multiple videos without returning to the dashboard.

---

## Possible Future Feature

- **Thumbnails Manager**
  - Add a button to `NavbarSubNavAdmin` for managing thumbnails.
  - Tools for resizing, counting, and managing thumbnail data.

---

## Implementation Order

1. Search
2. User Manager
3. Settings
4. Problems
5. Create New Playlist

---

*These notes serve as a development roadmap for the Admin Dashboard. Features and priorities may be adjusted as needed.*
