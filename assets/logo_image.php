<?php
// Serve website logo from project files (hosting-safe).
$candidates = [
  __DIR__ . '/logo.png',
  __DIR__ . '/logo.jpg',
  __DIR__ . '/logo.jpeg',
  __DIR__ . '/logo.webp',
  __DIR__ . '/images/logo.png',
  __DIR__ . '/images/logo.jpg',
  __DIR__ . '/images/logo.jpeg',
  __DIR__ . '/images/logo.webp',
];

$source = '';
foreach ($candidates as $candidate) {
  if (is_file($candidate)) {
    $source = $candidate;
    break;
  }
}

if ($source !== '') {
  if (function_exists('mime_content_type')) {
    $mime = (string)mime_content_type($source);
    header('Content-Type: ' . ($mime !== '' ? $mime : 'application/octet-stream'));
  } else {
    header('Content-Type: application/octet-stream');
  }
  header('Cache-Control: public, max-age=3600');
  readfile($source);
  exit;
}

// Fallback logo so site never breaks if file is missing.
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=300');
echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256">
  <rect width="256" height="256" rx="36" fill="#fff8f0"/>
  <circle cx="128" cy="128" r="92" fill="#f4e7d6" stroke="#c9a46a" stroke-width="8"/>
  <text x="128" y="145" text-anchor="middle" font-family="Arial, sans-serif" font-size="64" font-weight="700" fill="#7b5728">CC</text>
</svg>
SVG;
