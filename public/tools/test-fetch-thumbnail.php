<?php

// Temporary development-only IMDb thumbnail fetch diagnostic.
// Start locally with:
// FREETV_ENABLE_LOCAL_THUMBNAIL_TESTER=1 php -S 127.0.0.1:8080 -t public

$loopbackAddresses = ['127.0.0.1', '::1'];
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$testerEnabled = getenv('FREETV_ENABLE_LOCAL_THUMBNAIL_TESTER') === '1';
$isDevelopmentServer = PHP_SAPI === 'cli-server';

if (
    !$testerEnabled
    || !$isDevelopmentServer
    || !in_array($remoteAddress, $loopbackAddresses, true)
) {
    http_response_code(404);
    exit;
}

set_time_limit(60);
header('Content-Type: text/html; charset=utf-8');

function escapeHtml($value): string
{
    if (is_bool($value)) {
        $value = $value ? 'yes' : 'no';
    } elseif ($value === null || $value === '') {
        $value = '(none)';
    }

    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function requestHeaders(string $profile, string $requestType): array
{
    if ($profile === 'browser') {
        if ($requestType === 'image') {
            return [
                'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
            ];
        }

        return [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Cache-Control: no-cache',
            'Upgrade-Insecure-Requests: 1',
        ];
    }

    return ['Accept: */*'];
}

function requestUserAgent(string $profile): string
{
    if ($profile === 'browser') {
        return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
            . 'AppleWebKit/537.36 (KHTML, like Gecko) '
            . 'Chrome/127.0.0.0 Safari/537.36';
    }

    return 'FreeTV-Thumbnail-Diagnostic/1.0';
}

function fetchDiagnostic(
    string $url,
    string $profile,
    string $requestType,
    int $timeoutSeconds,
    int $maximumBytes,
    ?string $referer = null
): array {
    $body = '';
    $sizeLimitExceeded = false;
    $handle = curl_init($url);

    curl_setopt_array($handle, [
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $timeoutSeconds,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_USERAGENT => requestUserAgent($profile),
        CURLOPT_HTTPHEADER => requestHeaders($profile, $requestType),
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_WRITEFUNCTION => function ($curl, string $chunk) use (
            &$body,
            &$sizeLimitExceeded,
            $maximumBytes
        ): int {
            if (strlen($body) + strlen($chunk) > $maximumBytes) {
                $sizeLimitExceeded = true;
                return 0;
            }

            $body .= $chunk;
            return strlen($chunk);
        },
    ]);

    if ($referer !== null) {
        curl_setopt($handle, CURLOPT_REFERER, $referer);
    }

    $result = curl_exec($handle);
    $curlErrorNumber = curl_errno($handle);
    $curlError = curl_error($handle);
    $info = curl_getinfo($handle);
    curl_close($handle);

    if ($sizeLimitExceeded) {
        $curlError = 'Response exceeded the diagnostic size limit of '
            . $maximumBytes . ' bytes';
    }

    return [
        'curl_success' => $result !== false && $curlErrorNumber === 0 && !$sizeLimitExceeded,
        'http_status' => (int) ($info['http_code'] ?? 0),
        'effective_url' => $info['url'] ?? '',
        'redirect_count' => (int) ($info['redirect_count'] ?? 0),
        'response_bytes' => strlen($body),
        'content_type' => $info['content_type'] ?? '',
        'curl_error' => $curlError,
        'body' => $body,
    ];
}

function findOpenGraphImage(string $html): array
{
    $document = new DOMDocument();
    $previousSetting = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previousSetting);

    if (!$loaded) {
        return ['parsed' => false, 'image_url' => ''];
    }

    foreach ($document->getElementsByTagName('meta') as $meta) {
        if (strcasecmp($meta->getAttribute('property'), 'og:image') === 0) {
            return [
                'parsed' => true,
                'image_url' => trim($meta->getAttribute('content')),
            ];
        }
    }

    return ['parsed' => true, 'image_url' => ''];
}

$imdbId = isset($_POST['imdb']) ? trim((string) $_POST['imdb']) : 'tt0052520';
$profile = isset($_POST['profile']) ? (string) $_POST['profile'] : 'basic';
$diagnostic = null;
$validationError = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!preg_match('/^tt\d+$/', $imdbId)) {
        $validationError = 'IMDb ID must match tt followed only by digits.';
    } elseif (!in_array($profile, ['basic', 'browser'], true)) {
        $validationError = 'Unknown request profile.';
    } elseif (!extension_loaded('curl')) {
        $validationError = 'The PHP cURL extension is not available.';
    } elseif (!class_exists('DOMDocument')) {
        $validationError = 'The PHP DOM extension is not available.';
    } else {
        $requestedUrl = 'https://www.imdb.com/title/' . $imdbId . '/';
        $pageRequest = fetchDiagnostic(
            $requestedUrl,
            $profile,
            'page',
            20,
            10 * 1024 * 1024
        );
        $htmlReceived = $pageRequest['response_bytes'] > 0;
        $parsing = $htmlReceived
            ? findOpenGraphImage($pageRequest['body'])
            : ['parsed' => false, 'image_url' => ''];
        $tempDirectory = dirname(__DIR__) . '/temp';
        $destinationPath = $tempDirectory . '/' . $imdbId . '.jpg';

        $diagnostic = [
            'imdb_id' => $imdbId,
            'requested_url' => $requestedUrl,
            'profile' => $profile,
            'page' => $pageRequest,
            'html_received' => $htmlReceived,
            'html_parsed' => $parsing['parsed'],
            'og_image_found' => $parsing['image_url'] !== '',
            'image_url' => $parsing['image_url'],
            'image' => null,
            'filesystem' => [
                'destination_path' => $destinationPath,
                'temp_directory_existed' => is_dir($tempDirectory),
                'temp_directory_created' => false,
                'write_attempted' => false,
                'write_succeeded' => false,
                'bytes_written' => 0,
                'error' => 'Not attempted because no image was downloaded',
            ],
            'preview_url' => '',
        ];

        if ($parsing['image_url'] !== '') {
            $imageUrlParts = parse_url($parsing['image_url']);
            if (
                !is_array($imageUrlParts)
                || ($imageUrlParts['scheme'] ?? '') !== 'https'
                || empty($imageUrlParts['host'])
            ) {
                $diagnostic['image'] = [
                    'curl_success' => false,
                    'http_status' => 0,
                    'effective_url' => '',
                    'redirect_count' => 0,
                    'response_bytes' => 0,
                    'content_type' => '',
                    'curl_error' => 'Discovered image URL is not a valid HTTPS URL',
                    'body' => '',
                ];
            } else {
                $imageRequest = fetchDiagnostic(
                    $parsing['image_url'],
                    $profile,
                    'image',
                    20,
                    20 * 1024 * 1024,
                    $pageRequest['effective_url'] ?: $requestedUrl
                );
                $diagnostic['image'] = $imageRequest;

                $imageHttpSuccess = $imageRequest['http_status'] >= 200
                    && $imageRequest['http_status'] < 300;
                if (
                    $imageRequest['curl_success']
                    && $imageHttpSuccess
                    && $imageRequest['response_bytes'] > 0
                ) {
                    $directoryExisted = is_dir($tempDirectory);
                    $directoryCreated = false;
                    $directoryError = '';

                    if (!$directoryExisted) {
                        $directoryCreated = mkdir($tempDirectory, 0775, true);
                        if (!$directoryCreated) {
                            $directoryError = 'Could not create public/temp/';
                        }
                    }

                    $bytesWritten = false;
                    if (is_dir($tempDirectory)) {
                        $bytesWritten = file_put_contents($destinationPath, $imageRequest['body']);
                        if ($bytesWritten === false) {
                            $directoryError = 'Could not write the downloaded image';
                        }
                    }

                    $diagnostic['filesystem'] = [
                        'destination_path' => $destinationPath,
                        'temp_directory_existed' => $directoryExisted,
                        'temp_directory_created' => $directoryCreated,
                        'write_attempted' => is_dir($tempDirectory),
                        'write_succeeded' => $bytesWritten !== false,
                        'bytes_written' => $bytesWritten === false ? 0 : $bytesWritten,
                        'error' => $directoryError,
                    ];

                    if ($bytesWritten !== false) {
                        $diagnostic['preview_url'] = '/temp/' . $imdbId . '.jpg';
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>IMDb Thumbnail Fetch Diagnostic</title>
  <style>
    body { font-family: sans-serif; margin: 2rem auto; max-width: 64rem; padding: 0 1rem; }
    label { display: block; font-weight: 600; margin-top: 1rem; }
    input, select, button { box-sizing: border-box; font: inherit; padding: 0.6rem; width: 100%; }
    button { cursor: pointer; margin-top: 1rem; }
    dl { display: grid; grid-template-columns: minmax(12rem, 1fr) 3fr; margin: 0; }
    dt, dd { border-bottom: 1px solid #ddd; margin: 0; overflow-wrap: anywhere; padding: 0.5rem; }
    dt { font-weight: 600; }
    .error { background: #f8d7da; border: 1px solid #f1aeb5; padding: 1rem; }
    .preview { display: block; height: auto; margin-top: 1rem; max-width: 100%; }
    .warning { background: #fff3cd; border: 1px solid #ffe69c; padding: 1rem; }
  </style>
</head>
<body>
  <h1>IMDb Thumbnail Fetch Diagnostic</h1>
  <p class="warning">
    Development-only tool. It is available only when explicitly enabled on a
    loopback-bound PHP development server.
  </p>

  <form method="post">
    <label for="imdb">IMDb ID</label>
    <input id="imdb" name="imdb" value="<?= escapeHtml($imdbId) ?>" pattern="tt[0-9]+" required>

    <label for="profile">Request profile</label>
    <select id="profile" name="profile">
      <option value="basic" <?= $profile === 'basic' ? 'selected' : '' ?>>Basic server request</option>
      <option value="browser" <?= $profile === 'browser' ? 'selected' : '' ?>>Browser-like request</option>
    </select>

    <button type="submit">Run diagnostic</button>
  </form>

  <?php if ($validationError !== ''): ?>
    <h2>Validation failure</h2>
    <p class="error"><?= escapeHtml($validationError) ?></p>
  <?php elseif ($diagnostic !== null): ?>
    <h2>IMDb page request</h2>
    <dl>
      <dt>Requested IMDb ID</dt><dd><?= escapeHtml($diagnostic['imdb_id']) ?></dd>
      <dt>Requested URL</dt><dd><?= escapeHtml($diagnostic['requested_url']) ?></dd>
      <dt>Request profile</dt><dd><?= escapeHtml($diagnostic['profile']) ?></dd>
      <dt>cURL success</dt><dd><?= escapeHtml($diagnostic['page']['curl_success']) ?></dd>
      <dt>HTTP status</dt><dd><?= escapeHtml($diagnostic['page']['http_status']) ?></dd>
      <dt>Effective URL</dt><dd><?= escapeHtml($diagnostic['page']['effective_url']) ?></dd>
      <dt>Redirect count</dt><dd><?= escapeHtml($diagnostic['page']['redirect_count']) ?></dd>
      <dt>Response bytes</dt><dd><?= escapeHtml($diagnostic['page']['response_bytes']) ?></dd>
      <dt>Content type</dt><dd><?= escapeHtml($diagnostic['page']['content_type']) ?></dd>
      <dt>cURL error</dt><dd><?= escapeHtml($diagnostic['page']['curl_error']) ?></dd>
    </dl>

    <h2>HTML parsing</h2>
    <dl>
      <dt>HTML received</dt><dd><?= escapeHtml($diagnostic['html_received']) ?></dd>
      <dt>HTML parsed</dt><dd><?= escapeHtml($diagnostic['html_parsed']) ?></dd>
      <dt>og:image found</dt><dd><?= escapeHtml($diagnostic['og_image_found']) ?></dd>
      <dt>Discovered image URL</dt><dd><?= escapeHtml($diagnostic['image_url']) ?></dd>
    </dl>

    <?php if ($diagnostic['image'] !== null): ?>
      <h2>Image request</h2>
      <dl>
        <dt>cURL success</dt><dd><?= escapeHtml($diagnostic['image']['curl_success']) ?></dd>
        <dt>HTTP status</dt><dd><?= escapeHtml($diagnostic['image']['http_status']) ?></dd>
        <dt>Effective URL</dt><dd><?= escapeHtml($diagnostic['image']['effective_url']) ?></dd>
        <dt>Redirect count</dt><dd><?= escapeHtml($diagnostic['image']['redirect_count']) ?></dd>
        <dt>Downloaded bytes</dt><dd><?= escapeHtml($diagnostic['image']['response_bytes']) ?></dd>
        <dt>Content type</dt><dd><?= escapeHtml($diagnostic['image']['content_type']) ?></dd>
        <dt>cURL error</dt><dd><?= escapeHtml($diagnostic['image']['curl_error']) ?></dd>
      </dl>
    <?php endif; ?>

    <?php if ($diagnostic['filesystem'] !== null): ?>
      <h2>Filesystem</h2>
      <dl>
        <dt>Destination temp path</dt><dd><?= escapeHtml($diagnostic['filesystem']['destination_path']) ?></dd>
        <dt>public/temp existed</dt><dd><?= escapeHtml($diagnostic['filesystem']['temp_directory_existed']) ?></dd>
        <dt>public/temp created</dt><dd><?= escapeHtml($diagnostic['filesystem']['temp_directory_created']) ?></dd>
        <dt>Write attempted</dt><dd><?= escapeHtml($diagnostic['filesystem']['write_attempted']) ?></dd>
        <dt>Write succeeded</dt><dd><?= escapeHtml($diagnostic['filesystem']['write_succeeded']) ?></dd>
        <dt>Bytes written</dt><dd><?= escapeHtml($diagnostic['filesystem']['bytes_written']) ?></dd>
        <dt>Filesystem error</dt><dd><?= escapeHtml($diagnostic['filesystem']['error']) ?></dd>
      </dl>
    <?php endif; ?>

    <?php if ($diagnostic['preview_url'] !== ''): ?>
      <h2>Downloaded image preview</h2>
      <img
        class="preview"
        src="<?= escapeHtml($diagnostic['preview_url']) ?>"
        alt="Downloaded thumbnail for <?= escapeHtml($diagnostic['imdb_id']) ?>"
      >
    <?php endif; ?>
  <?php endif; ?>
</body>
</html>
