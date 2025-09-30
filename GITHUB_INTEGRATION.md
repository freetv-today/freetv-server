# GitHub Integration for Data Setup

## Overview

This document explains how the Admin Dashboard automatically downloads data from GitHub repositories using GitHub's built-in ZIP download feature.

## ✅ GitHub's Built-in ZIP Downloads

### **Automatic ZIP URLs:**
GitHub automatically provides ZIP downloads for every repository:
```
https://github.com/{owner}/{repo}/archive/refs/heads/{branch}.zip
```

### **Your Repository URLs:**
- **Production Data**: `https://github.com/freetv-today/freetv-data/archive/refs/heads/main.zip`
- **Sample Data**: `https://github.com/freetv-today/freetv-sampledata/archive/refs/heads/main.zip`

### **Benefits:**
- ✅ **No rate limits** - direct file download
- ✅ **Single HTTP request** for entire repository
- ✅ **Compressed transfer** - automatic ZIP compression
- ✅ **No GitHub API complexity** - simple HTTP download
- ✅ **Works instantly** - no setup required

## 🚀 **Implementation**

### **PHP Download Function:**
```php
function downloadAndExtractGitHubZip($zipUrl, $extractToPath, $rootFolderName) {
    // 1. Download ZIP file from GitHub
    $zipContent = curl_download($zipUrl);
    
    // 2. Extract to temporary directory
    $zip = new ZipArchive();
    $zip->extractTo($tempPath);
    
    // 3. Copy files to target location (GitHub adds repo-name-branch folder)
    copyDirectory($tempPath . '/' . $rootFolderName, $extractToPath);
    
    // 4. Cleanup temporary files
}
```

### **Setup Functions:**
```php
// Download full production data
function setupOfficialData() {
    $zipUrl = 'https://github.com/freetv-today/freetv-data/archive/refs/heads/main.zip';
    downloadAndExtractGitHubZip($zipUrl, $publicDir, 'freetv-data-main');
}

// Download sample development data  
function setupSampleData() {
    $zipUrl = 'https://github.com/freetv-today/freetv-sampledata/archive/refs/heads/main.zip';
    downloadAndExtractGitHubZip($zipUrl, $publicDir, 'freetv-sampledata-main');
}
```

### **Important Note: Root Folder Names**
GitHub ZIP files always contain a root folder named `{repo-name}-{branch}`:
- `freetv-data-main/` (contains your actual files)
- `freetv-sampledata-main/` (contains your actual files)

The PHP code handles this automatically by specifying the expected root folder name.

## Implementation Benefits

### **1. Automatic Setup**
- Clone repo → Run app → Automatic data setup
- No manual file copying required
- Perfect developer onboarding

### **2. Version Control**
- Data changes tracked in Git
- Easy rollbacks if needed
- Collaborative data management

### **3. Selective Downloads**
- Download only needed files
- Skip large thumbnail directories if desired
- Progressive enhancement

## Code Example: Enhanced Setup

```php
function setupOfficialData() {
    $repo = 'freetv-today/freetv-data';
    $apiBase = "https://api.github.com/repos/{$repo}/contents/";
    
    // Download essential files first
    downloadFileFromGitHub($apiBase . 'config.json', $configFile);
    downloadDirectoryFromGitHub($apiBase . 'playlists', $playlistsDir);
    
    // Optional: Download thumbnails (can be skipped for faster setup)
    $downloadThumbs = $_POST['include_thumbnails'] ?? false;
    if ($downloadThumbs) {
        downloadDirectoryFromGitHub($apiBase . 'thumbs', $thumbsDir);
    }
}
```

## Error Handling

### **Common Issues & Solutions:**

#### **Rate Limit Exceeded:**
```php
if ($httpCode === 403) {
    $rateLimitReset = curl_getinfo($ch, CURLINFO_HEADER_OUT);
    throw new Exception('GitHub rate limit exceeded. Try again after: ' . $rateLimitReset);
}
```

#### **Large File Handling:**
```php
// For files > 1MB, GitHub returns download_url instead of content
if (isset($response['download_url'])) {
    $content = file_get_contents($response['download_url']);
} else {
    $content = base64_decode($response['content']);
}
```

#### **Network Timeouts:**
```php
curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minute timeout for large downloads
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // 30 second connection timeout
```

## Production Recommendations

### **1. Create freetv-sampledata Repository**
- Small, curated dataset for development
- 2-3 playlists with 5-10 shows each
- Sample thumbnails included
- Quick download (< 1 minute)

### **2. Implement Progress Tracking**
```javascript
// Frontend: Show download progress
const response = await fetch('/api/admin/setup-data.php', {
    method: 'POST',
    body: JSON.stringify({ 
        type: 'official',
        progress_callback: true 
    })
});
```

### **3. Optional Authentication**
- Add GitHub token for higher rate limits
- Store in environment variable or config
- Only needed for heavy usage

## Testing the Integration

### **1. Test with Sample Data:**
```bash
# Delete current data
rm -rf public/playlists public/thumbs public/config.json

# Start development server
npm run dev

# Navigate to app → Should show setup page
# Click "Load Sample Data" → Should download from GitHub
```

### **2. Monitor GitHub API Usage:**
```bash
curl -I https://api.github.com/repos/freetv-today/freetv-data
# Check X-RateLimit-Remaining header
```

This implementation provides a robust, automated way to set up your application data while maintaining version control and developer-friendly onboarding!