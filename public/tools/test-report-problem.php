<?php

// Temporary development-only manual tester.
// Start locally with:
// FREETV_ENABLE_LOCAL_REPORT_TESTER=1 php -S 127.0.0.1:8080 -t public

$loopbackAddresses = ['127.0.0.1', '::1'];
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$serverAddress = $_SERVER['SERVER_ADDR'] ?? '';
$testerEnabled = getenv('FREETV_ENABLE_LOCAL_REPORT_TESTER') === '1';

if (
    !$testerEnabled
    || !in_array($remoteAddress, $loopbackAddresses, true)
    || !in_array($serverAddress, $loopbackAddresses, true)
) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Temporary Problem Report Tester</title>
  <style>
    body { font-family: sans-serif; margin: 2rem auto; max-width: 48rem; padding: 0 1rem; }
    label { display: block; font-weight: 600; margin-top: 1rem; }
    input, button { box-sizing: border-box; font: inherit; padding: 0.6rem; width: 100%; }
    button { cursor: pointer; margin-top: 1rem; }
    pre { background: #f4f4f4; min-height: 4rem; overflow-wrap: anywhere; padding: 1rem; white-space: pre-wrap; }
    .warning { background: #fff3cd; border: 1px solid #ffe69c; padding: 1rem; }
  </style>
</head>
<body>
  <h1>Problem Report Manual Tester</h1>
  <p class="warning">
    Development-only and temporary. This page is available only when explicitly
    enabled on a loopback-bound local server.
  </p>

  <form id="report-form">
    <label for="playlist">Playlist filename</label>
    <input id="playlist" name="playlist" value="freetv.json" required>

    <label for="identifier">Internet Archive identifier</label>
    <input id="identifier" name="identifier" required>

    <button type="submit">Submit problem report</button>
  </form>

  <h2>Response</h2>
  <pre id="response">No request submitted yet.</pre>

  <script>
    const form = document.querySelector('#report-form');
    const output = document.querySelector('#response');

    form.addEventListener('submit', async event => {
      event.preventDefault();
      output.textContent = 'Submitting...';

      const formData = new FormData(form);
      const payload = {
        title: 'Manual tester placeholder',
        category: 'manual-test',
        identifier: formData.get('identifier'),
        desc: 'Manual tester placeholder',
        start: '',
        end: '',
        imdb: '',
        playlist: formData.get('playlist')
      };

      try {
        const response = await fetch('/api/report-problem.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const raw = await response.text();
        let parsed = null;

        try {
          parsed = JSON.parse(raw);
        } catch {
          parsed = null;
        }

        output.textContent = [
          `HTTP ${response.status} ${response.statusText}`,
          '',
          'Parsed response:',
          parsed === null ? '(not valid JSON)' : JSON.stringify(parsed, null, 2),
          '',
          'Raw response:',
          raw
        ].join('\n');
      } catch (error) {
        output.textContent = `Request failed: ${error instanceof Error ? error.message : String(error)}`;
      }
    });
  </script>
</body>
</html>
