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

header('Content-Type: text/plain; charset=UTF-8');
echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /admin/\n";
echo "Disallow: /config/\n";
echo "Disallow: /core/\n";
echo "Disallow: /install/\n\n";
echo 'Sitemap: '.rtrim($canonical,'/')."/sitemap.xml\n";
