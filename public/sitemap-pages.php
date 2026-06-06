<?php
header("Content-Type: application/xml; charset=UTF-8");

$urls = [
  "https://academy.worldison.org/",
  "https://academy.worldison.org/courses",
  "https://academy.worldison.org/about",
  "https://academy.worldison.org/contact",
];

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

foreach ($urls as $url) {
  echo "  <url>\n";
  echo "    <loc>" . htmlspecialchars($url, ENT_XML1, 'UTF-8') . "</loc>\n";
  echo "    <lastmod>" . date("Y-m-d") . "</lastmod>\n";
  echo "  </url>\n";
}

echo "</urlset>";
