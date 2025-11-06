<?php
// Vercel entrypoint for Laravel via vercel-php runtime
// Routes are configured in vercel.json to send all dynamic requests here.
// Static assets are served directly from /public via routes.

// Ensure we are in the project root
chdir(__DIR__ . '/..');

// Bootstrap Laravel just like public/index.php
require __DIR__ . '/../public/index.php';
