<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VivalaTable Embed Test</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        h1 {
            color: var(--vt-primary);
            margin-bottom: 30px;
        }
        .test-section {
            margin-bottom: 40px;
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
        }
        .test-url {
            font-family: monospace;
            background: #e5e7eb;
            padding: 8px 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            word-break: break-all;
        }
        .debug-info {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 10px;
        }
    </style>
</head>
<body>
<?php
require_once __DIR__ . '/includes/bootstrap.php';
?>

<h1>VivalaTable Embed System Test</h1>

<?php
$testUrls = [
    'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'https://vimeo.com/76979871',
    'https://github.com/anthropics/claude-code',
    'https://dev.to/ben/explaining-the-ben-value-of-using-a-static-site-generator-44bp',
];

foreach ($testUrls as $url) {
    echo '<div class="test-section">';
    echo '<div class="test-url">' . htmlspecialchars($url) . '</div>';

    $embed = VT_Embed_Service::buildEmbedFromUrl($url);

    if ($embed) {
        $html = VT_Embed_Renderer::render($embed);
        echo $html;

        echo '<div class="debug-info">';
        echo 'Type: ' . htmlspecialchars($embed['type'] ?? 'unknown');
        if (isset($embed['oembed_type'])) {
            echo ' (oEmbed: ' . htmlspecialchars($embed['oembed_type']) . ')';
        }
        if (isset($embed['og_type'])) {
            echo ' (OG: ' . htmlspecialchars($embed['og_type']) . ')';
        }
        echo ' | Provider: ' . htmlspecialchars($embed['provider_name'] ?? 'N/A');
        echo '</div>';
    } else {
        echo '<p style="color: #ef4444;">No embed data found for this URL</p>';
    }

    echo '</div>';
}
?>

<div style="margin-top: 40px; padding: 20px; background: #dbeafe; border-radius: 8px;">
    <h2>Test Instructions</h2>
    <ol>
        <li>Open this page in a browser: <code>http://localhost/test-embed-visual.php</code></li>
        <li>YouTube video should show as an embedded iframe</li>
        <li>GitHub link should show as a card with image and description</li>
        <li>Check browser console for any errors</li>
    </ol>
</div>

</body>
</html>
