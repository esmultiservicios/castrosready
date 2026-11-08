<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';

$canonical='https://castrosready.us/';
if(config_ready()) {
    try {
        $configured=trim(setting('seo_canonical_url',$canonical));
        if(filter_var($configured,FILTER_VALIDATE_URL)!==false)$canonical=$configured;
    } catch(Throwable $ignored) {
    }
}

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc><?=h($canonical)?></loc>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
</urlset>
