<?php
require __DIR__.'/bootstrap.php';
require_permission('content.view');

$pdo=db();
$set=settings();

function website_media_url(string $path): string {
    $path=trim($path);
    if($path==='')return '';
    return preg_match('~^https?://~i',$path)?$path:'../'.ltrim($path,'/');
}

function website_media_card(string $title,string $description,string $path,string $manageUrl,string $manageLabel='Manage image',string $type='image'): void {
    $url=website_media_url($path);
    ?>
    <article class="website-media-card">
        <div class="website-media-preview <?=h($type)?>">
            <?php if($type==='video'&&$url!==''): ?>
                <video src="<?=h($url)?>" controls muted preload="metadata" playsinline></video>
            <?php elseif($type==='embed'&&$url!==''): ?>
                <div class="website-media-placeholder"><span>▶</span><strong>External video</strong></div>
            <?php elseif($url!==''): ?>
                <img src="<?=h($url)?>" alt="<?=h($title)?>" loading="lazy">
            <?php else: ?>
                <div class="website-media-placeholder"><span>＋</span><strong>No custom media</strong></div>
            <?php endif; ?>
        </div>
        <div class="website-media-copy">
            <div>
                <h2><?=h($title)?></h2>
                <p><?=h($description)?></p>
            </div>
            <span class="website-media-status <?=$url!==''?'ready':'empty'?>"><?=$url!==''?'Configured':'Needs media'?></span>
        </div>
        <?php if($path!==''): ?><code title="<?=h($path)?>"><?=h($path)?></code><?php endif; ?>
        <a class="button small" href="<?=h($manageUrl)?>"><?=icon('edit')?><?=h($manageLabel)?></a>
    </article>
    <?php
}

$services=$pdo->query("SELECT title,icon_path FROM services WHERE icon_path IS NOT NULL AND icon_path<>'' ORDER BY sort_order,id")->fetchAll();
$gallery=$pdo->query("SELECT title,image_path FROM gallery WHERE image_path IS NOT NULL AND image_path<>'' ORDER BY sort_order,id")->fetchAll();
$artworks=$pdo->query("SELECT title,image_path FROM about_artworks WHERE image_path<>'' ORDER BY sort_order,id")->fetchAll();
$posters=$pdo->query("SELECT title,poster_path FROM videos WHERE poster_path IS NOT NULL AND poster_path<>'' ORDER BY sort_order,id")->fetchAll();

$bannerType=(string)($set['banner_type']??'image');
$bannerPath=match($bannerType) {
    'video_upload'=>(string)($set['banner_video_path']??''),
    'video_embed'=>(string)($set['banner_embed_url']??''),
    default=>(string)($set['banner_image_path']??'assets/hero-banner.png'),
};
$bannerMediaType=match($bannerType){'video_upload'=>'video','video_embed'=>'embed',default=>'image'};

$pageTitle='Website Images';
$active='website-media';
require __DIR__.'/_header.php';
?>
<div class="page-heading animate-in">
    <div>
        <p class="eyebrow">PUBLIC WEBSITE MEDIA</p>
        <h1>Every public image in one place</h1>
        <p class="muted">Review every visual area and open the exact administrator screen where it can be replaced.</p>
    </div>
    <?php if(user_can('media.manage')): ?><a class="button" href="media.php"><?=icon('image')?>Open Media Library</a><?php endif; ?>
</div>

<div class="website-media-note">
    <?=icon('image')?>
    <div><strong>Nothing is hidden in the code.</strong><span>Branding, banner, Home, About, services, gallery, video posters, maintenance and social sharing images are covered below.</span></div>
</div>

<section class="website-media-section">
    <div class="website-media-section-heading"><div><p class="eyebrow">BRAND & HEADER</p><h2>Identity and first impression</h2></div></div>
    <div class="website-media-grid">
        <?php website_media_card('Public website logo','Header and footer logo.',(string)($set['site_logo_path']??'assets/logo.jpg'),'settings.php#branding','Change logo'); ?>
        <?php website_media_card('Header banner','Image, uploaded video, YouTube or Vimeo shown above the navigation.',$bannerPath,'appearance.php#header-banner','Manage banner',$bannerMediaType); ?>
        <?php website_media_card('Browser icon','Favicon shown in the browser tab.',(string)($set['favicon_path']??''),'settings.php#branding','Change favicon'); ?>
    </div>
</section>

<section class="website-media-section">
    <div class="website-media-section-heading"><div><p class="eyebrow">MAIN SECTIONS</p><h2>Home and About</h2></div></div>
    <div class="website-media-grid">
        <?php website_media_card('Home main image','Large project image beside the primary Home message.',(string)($set['hero_image_path']??'https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=1200&q=88'),'content.php?section=home','Change Home image'); ?>
        <?php website_media_card('About main image','Large image beside the company story.',(string)($set['about_image_path']??'https://images.unsplash.com/photo-1531835551805-16d864c8d311?auto=format&fit=crop&w=1100&q=85'),'content.php?section=about','Change About image'); ?>
        <?php foreach($artworks as $item) website_media_card((string)($item['title']?:'About artwork'),'Mission, vision or supporting About artwork.',(string)$item['image_path'],'content.php?section=about','Manage About artwork'); ?>
    </div>
</section>

<section class="website-media-section">
    <div class="website-media-section-heading"><div><p class="eyebrow">SERVICES & PORTFOLIO</p><h2>Cards, projects and video covers</h2></div></div>
    <div class="website-media-grid compact">
        <?php foreach($services as $item) website_media_card((string)$item['title'],'Service badge shown on the public services list.',(string)$item['icon_path'],'services.php','Manage service badge'); ?>
        <?php foreach($gallery as $item) website_media_card((string)$item['title'],'Public gallery project image.',(string)$item['image_path'],'gallery.php','Manage gallery image'); ?>
        <?php foreach($posters as $item) website_media_card((string)$item['title'],'Poster shown before a website video plays.',(string)$item['poster_path'],'videos.php','Manage video poster'); ?>
    </div>
</section>

<section class="website-media-section">
    <div class="website-media-section-heading"><div><p class="eyebrow">SYSTEM VISUALS</p><h2>Maintenance and social sharing</h2></div></div>
    <div class="website-media-grid">
        <?php website_media_card('Maintenance image','Image displayed when the public site is in maintenance mode.',(string)($set['maintenance_image_path']??''),'settings.php#site-status','Change maintenance image'); ?>
        <?php website_media_card('Social sharing image','Preview image used when the website link is shared.',(string)($set['seo_social_image']??''),'seo.php','Change social image'); ?>
        <?php website_media_card('Administrator logo','Logo used by the CMS login and administrator header.',(string)($set['admin_logo_path']??''),'settings.php#branding','Change admin logo'); ?>
    </div>
</section>
<?php require __DIR__.'/_footer.php'; ?>
