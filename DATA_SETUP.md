# Data Setup System

## Overview

The Admin Dashboard now includes a comprehensive data validation and setup system to handle cases where developers clone the repository without any application data.

## How It Works

### 1. Data Validation
- **useDataValidation hook** - Checks for required files on startup:
  - `/config.json` - Application configuration
  - `/playlists/index.json` - Playlist index file
  - Default playlist file (as specified in index.json)
  - `/thumbs/` directory accessibility

### 2. Automatic Detection
The system runs validation checks in two places:
- **Login page (`/`)** - Before allowing login
- **Dashboard page (`/dashboard`)** - Before loading admin interface

### 3. Data Setup Options

When missing data is detected, users see three options:

#### Option 1: Load Official Data
- Downloads complete FreeTv dataset from GitHub
- Includes all playlists, thumbnails, and production config
- **Note**: Currently shows error message - needs GitHub API integration

#### Option 2: Load Sample Data  
- Creates minimal test dataset perfect for development
- Includes:
  - 2 sample playlists (Movies & Classics)
  - 5 sample shows total
  - Basic configuration
  - Empty thumbnails directory

#### Option 3: Start Fresh
- Creates minimal required files only
- Empty playlist structure  
- Basic configuration
- User must add content manually via "Add Playlist" feature

## Files Created

### New Files:
- `src/hooks/useDataValidation.js` - Data validation logic
- `src/pages/DataSetupPage.jsx` - Setup interface
- `public/api/admin/setup-data.php` - Backend setup handler

### Modified Files:
- `src/pages/index.jsx` - Added data validation to login
- `src/pages/dashboard.jsx` - Added data validation to dashboard
- `src/pages/ErrorPage.jsx` - Enhanced with better props

## Usage

### For Developers:
1. Clone `freetv-server` repository
2. Run `npm install && npm run dev`
3. Navigate to `http://localhost:5173`
4. If no data exists, choose one of the setup options
5. Login and start using the admin dashboard

### For Production:
- The data validation only triggers when required files are missing
- Production environments with existing data will bypass the setup system
- No performance impact on systems with existing data

## Configuration

The validation checks can be customized in `useDataValidation.js`:

```javascript
// Critical issues prevent app startup
issues.push({
  type: 'config',
  message: 'config.json not found',
  severity: 'critical'  // 'critical' | 'warning'
});
```

## Sample Data Structure

When "Load Sample Data" is chosen, the system creates:

```
public/
├── config.json (basic configuration)
├── playlists/
│   ├── index.json (playlist index)
│   ├── sample-movies.json (3 sample movies)
│   └── sample-classics.json (2 sample classics)
└── thumbs/
    └── index.html (placeholder)
```

## Error Handling

- Network errors during setup are caught and displayed
- Users can retry failed operations
- "Check Again" button re-validates data without setup
- Fallback navigation back to login page

## Future Enhancements

1. **GitHub Integration**: Implement actual download from `freetv-data` repository
2. **Progress Indicators**: Show download/setup progress
3. **Selective Loading**: Allow users to choose specific playlists/categories
4. **Data Migration**: Tools for upgrading existing data structures