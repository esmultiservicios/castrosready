<?php
require __DIR__.'/bootstrap.php';
require_permission('content.view');

$pdo=db();
$set=settings();
$error='';
$flexibleTableReady=false;
try {
    $flexibleTableReady=(bool)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='flexible_content_blocks'")->fetchColumn();
} catch(Throwable $ignored) {
}

if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        if(!user_can('content.edit'))throw new RuntimeException('Your role cannot create website content.');
        if(!$flexibleTableReady)throw new RuntimeException('Import database-update.sql once before adding new website content.');
        if(($_POST['action']??'')!=='create_website_content')throw new RuntimeException('Invalid action.');
        $title=trim((string)($_POST['title']??''));
        $body=trim((string)($_POST['body']??''));
        $alt=trim((string)($_POST['image_alt']??''));
        $sort=(int)($_POST['sort_order']??0);
        $active=isset($_POST['active'])?1:0;
        if($title==='')throw new RuntimeException('Enter a title for the new website content.');
        if(empty($_FILES['image']['name']))throw new RuntimeException('Choose, drop or paste an image before saving.');
        $path=upload_image($_FILES['image'],'flexible-content','website-content',12);
        media_add($path,$title,$sort);
        $pdo->prepare('INSERT INTO flexible_content_blocks(title,body,image_path,image_alt,button_text,button_url,sort_order,active) VALUES(?,?,?,?,?,?,?,?)')
            ->execute([$title,$body,$path,$alt,'','',$sort,$active]);
        log_activity('website_media_create','Created additional website image and content',['title'=>$title,'published'=>$active]);
        flash('success',$active?'New website content created and published.':'New website content saved privately. Review it before publishing.');
        header('Location: website-media.php#new-content');
        exit;
    } catch(Throwable $e) {
        $error=$e->getMessage();
    }
}

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
$gallery=$pdo->query("SELECT title,image_path FROM gallery ORDER BY sort_order,id")->fetchAll();
$artworks=$pdo->query("SELECT title,image_path FROM about_artworks WHERE image_path<>'' ORDER BY sort_order,id")->fetchAll();
$posters=$pdo->query("SELECT title,poster_path FROM videos WHERE poster_path IS NOT NULL AND poster_path<>'' ORDER BY sort_order,id")->fetchAll();
$flexibleImages=[];
try {
    $flexibleImages=$pdo->query("SELECT title,image_path FROM flexible_content_blocks WHERE image_path IS NOT NULL AND image_path<>'' ORDER BY sort_order,id")->fetchAll();
} catch(Throwable $ignored) {
    $flexibleImages=[];
}
$nextFlexibleOrder=10;
if($flexibleTableReady) {
    $nextFlexibleOrder=(int)$pdo->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM flexible_content_blocks')->fetchColumn();
}

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
    <div class="heading-actions">
        <?php if(user_can('content.edit')): ?><a class="button" href="#new-content"><?=icon('edit')?>Add image & content</a><?php endif; ?>
        <?php if(user_can('media.manage')): ?><a class="button secondary" href="media.php"><?=icon('image')?>Open Media Library</a><?php endif; ?>
    </div>
</div>

<?php if($error!==''): ?><div class="alert error"><?=h($error)?></div><?php endif; ?>

<div class="website-media-note">
    <?=icon('image')?>
    <div><strong>Nothing is hidden in the code.</strong><span>Branding, banner, Home, About, services, gallery, video posters, maintenance and social sharing images are covered below.</span></div>
</div>

<section class="website-media-create-options">
    <div class="website-media-section-heading"><div><p class="eyebrow">ADD NEW VISUALS</p><h2>Choose what you want to create</h2><p class="muted">This screen is not only an index: use these shortcuts to add more images, services, gallery projects, videos or completely new content.</p></div></div>
    <div class="manage-grid website-media-action-grid">
        <?php if(user_can('gallery.manage')): ?><a class="manage-card" href="gallery.php"><span class="manage-icon"><?=icon('image')?></span><div><strong>New gallery image</strong><small>Add another project and set its order.</small></div><span class="manage-arrow">→</span></a><?php endif; ?>
        <?php if(user_can('services.manage')): ?><a class="manage-card" href="services.php"><span class="manage-icon"><?=icon('tools')?></span><div><strong>New service image</strong><small>Create another service with its badge or photo.</small></div><span class="manage-arrow">→</span></a><?php endif; ?>
        <?php if(user_can('videos.manage')): ?><a class="manage-card" href="videos.php"><span class="manage-icon"><?=icon('eye')?></span><div><strong>New video or cover</strong><small>Add YouTube, Vimeo or an uploaded video.</small></div><span class="manage-arrow">→</span></a><?php endif; ?>
        <?php if(user_can('content.edit')): ?><a class="manage-card" href="content.php?section=about"><span class="manage-icon"><?=icon('users')?></span><div><strong>New About artwork</strong><small>Add more Mission, Vision or company images.</small></div><span class="manage-arrow">→</span></a>
        <a class="manage-card" href="#new-content"><span class="manage-icon"><?=icon('edit')?></span><div><strong>New image & content</strong><small>Create an unlimited visual content block below.</small></div><span class="manage-arrow">↓</span></a><?php endif; ?>
    </div>
</section>

<?php if(user_can('content.edit')): ?>
<section class="panel website-media-create-panel" id="new-content">
    <div class="panel-heading"><div class="panel-icon"><?=icon('image')?></div><div><p class="eyebrow">CREATE MORE CONTENT</p><h2>Add a new image and content block</h2><p>Create unlimited visual content here. It is saved privately unless you explicitly activate publication.</p></div></div>
    <?php if(!$flexibleTableReady): ?>
        <div class="alert error"><strong>Database update required:</strong> import <code>database-update.sql</code> once.</div>
    <?php else: ?>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
        <input type="hidden" name="action" value="create_website_content">
        <div class="two-col">
            <label>Content title<input name="title" required placeholder="Example: Featured remodeling project"></label>
            <label>Display order<input type="number" name="sort_order" value="<?=h((string)$nextFlexibleOrder)?>"></label>
        </div>
        <label>Description or additional information<textarea name="body" rows="6" placeholder="Explain the image, project, promotion or information the visitor should see."></textarea></label>
        <div class="upload-zone premium-media-zone full-width-upload" data-upload-zone tabindex="0">
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-empty-label="Drop, paste or choose the new website image">
            <div class="upload-icon"><?=icon('image')?></div><strong>New website image</strong><small>Drag & drop, paste from clipboard or select JPG, PNG or WEBP.</small><span class="upload-zone-action">Choose image</span>
            <div class="upload-selection-name" data-upload-name>Drop, paste or choose the new website image</div><div class="upload-preview premium-upload-preview" data-upload-preview></div>
        </div>
        <label>Alternative text<input name="image_alt" placeholder="Describe the image for accessibility and SEO"></label>
        <label class="premium-switch"><input type="checkbox" name="active"><span class="switch-ui"></span><span><b>Publish immediately</b><small>Leave disabled to save privately and review it before visitors can see it.</small></span></label>
        <div class="form-actions"><button><?=icon('edit')?>Save new content</button><a class="button secondary" href="../?preview=1#custom-content" target="_blank" rel="noopener"><?=icon('eye')?>Private preview</a><a class="button secondary" href="flexible-content.php"><?=icon('gear')?>Open advanced editor</a></div>
    </form>
    <?php endif; ?>
</section>
<?php endif; ?>

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
        <?php foreach($gallery as $item): $galleryPath=trim((string)$item['image_path'])?:gallery_default_image((string)$item['title']); website_media_card((string)$item['title'],'Public gallery project image.',$galleryPath,'gallery.php','Manage gallery image'); endforeach; ?>
        <?php foreach($posters as $item) website_media_card((string)$item['title'],'Poster shown before a website video plays.',(string)$item['poster_path'],'videos.php','Manage video poster'); ?>
        <?php foreach($flexibleImages as $item) website_media_card((string)$item['title'],'Image used by an additional public content block.',(string)$item['image_path'],'flexible-content.php','Manage additional content'); ?>
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
