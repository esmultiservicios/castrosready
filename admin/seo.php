<?php
require __DIR__.'/bootstrap.php';
require_permission('seo.manage');
$set=settings();
$error='';

if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        $canonical=trim((string)($_POST['seo_canonical_url']??''));
        if($canonical!==''&&filter_var($canonical,FILTER_VALIDATE_URL)===false) {
            throw new RuntimeException('Canonical URL must be a complete http:// or https:// address.');
        }
        foreach(['seo_title','seo_description','seo_robots','seo_canonical_url','seo_site_name','seo_business_type','seo_area_served'] as $key) {
            save_setting($key,trim((string)($_POST[$key]??'')));
        }
        if(!empty($_FILES['seo_social_image']['name'])) {
            $path=upload_image($_FILES['seo_social_image'],'seo','social',8);
            save_setting('seo_social_image',$path);
            media_add($path,'SEO social image');
        }
        if(isset($_POST['remove_social']))save_setting('seo_social_image','');
        log_activity('seo_update','Updated complete SEO and structured data settings');
        flash('success','SEO settings saved.');
        header('Location: seo.php');
        exit;
    } catch(Throwable $e) {
        $error=$e->getMessage();
    }
}

$set=settings();
$pageTitle='SEO Manager';
$active='seo';
require __DIR__.'/_header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">SEO MANAGER</p>
        <h1>Search & social appearance</h1>
        <p class="muted">Control how the website appears in Google, social networks and structured local-business results.</p>
    </div>
</div>

<?php if($error): ?>
<div class="alert error"><?=h($error)?></div>
<?php endif; ?>

<div class="seo-layout">
    <section class="panel">
        <form method="post" enctype="multipart/form-data" data-seo-form>
            <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">

            <label>Browser / search title
                <input name="seo_title" maxlength="60" required value="<?=h($set['seo_title']??'')?>" data-seo-title>
                <small><span data-seo-title-count>0</span>/60 characters · keep the main service and brand clear.</small>
            </label>

            <label>Meta description
                <textarea name="seo_description" maxlength="160" required data-seo-description><?=h($set['seo_description']??'')?></textarea>
                <small><span data-seo-description-count>0</span>/160 characters · explain the service area and customer benefit.</small>
            </label>

            <div class="two-col">
                <label>Canonical website URL
                    <input type="url" name="seo_canonical_url" value="<?=h($set['seo_canonical_url']??'https://castrosready.us/')?>" placeholder="https://castrosready.us/">
                    <small>Identifies the preferred public URL and avoids duplicate-page signals.</small>
                </label>
                <label>Website / brand name
                    <input name="seo_site_name" value="<?=h($set['seo_site_name']??"Castro's Ready")?>">
                </label>
            </div>

            <div class="two-col">
                <label>Business schema type
                    <select name="seo_business_type">
                        <?php foreach(['HomeAndConstructionBusiness'=>'Home & construction business','GeneralContractor'=>'General contractor','HousePainter'=>'House painter','LocalBusiness'=>'Local business'] as $value=>$label): ?>
                        <option value="<?=h($value)?>" <?=($set['seo_business_type']??'HomeAndConstructionBusiness')===$value?'selected':''?>><?=h($label)?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Primary service area
                    <input name="seo_area_served" value="<?=h($set['seo_area_served']??'Elkridge, Maryland and surrounding areas')?>">
                </label>
            </div>

            <label>Search-engine access
                <select name="seo_robots">
                    <option value="index,follow" <?=($set['seo_robots']??'')==='index,follow'?'selected':''?>>Index & follow</option>
                    <option value="noindex,nofollow" <?=($set['seo_robots']??'')==='noindex,nofollow'?'selected':''?>>Hide from search engines</option>
                </select>
            </label>

            <div class="upload-zone" data-upload-zone tabindex="0">
                <div class="upload-icon"><?=icon('image')?></div>
                <strong>Social sharing image</strong>
                <small data-upload-name>Drag, paste or choose a 1200 × 630 image</small>
                <input type="file" name="seo_social_image" accept="image/jpeg,image/png,image/webp">
                <div class="upload-preview" data-upload-preview></div>
            </div>

            <?php if(!empty($set['seo_social_image'])): ?>
            <div class="saved-image-row">
                <img src="../<?=h($set['seo_social_image'])?>" alt="Current social sharing image">
                <div><strong>Current social image</strong><small><?=h($set['seo_social_image'])?></small></div>
                <label class="compact-check"><input type="checkbox" name="remove_social" value="1"> Remove image</label>
            </div>
            <?php endif; ?>

            <div class="form-actions">
                <button>Save SEO settings</button>
                <a class="button secondary" href="../?preview=1" target="_blank" rel="noopener">Preview website</a>
            </div>
        </form>
    </section>

    <aside class="search-preview">
        <span>Live search preview</span>
        <div class="search-domain" data-seo-preview-domain><?=h(parse_url($set['seo_canonical_url']??'https://castrosready.us/',PHP_URL_HOST)?:'castrosready.us')?></div>
        <h3 data-seo-preview-title><?=h($set['seo_title']??"Castro's Ready | Home Improvement")?></h3>
        <p data-seo-preview-description><?=h($set['seo_description']??'Professional home improvement services.')?></p>
        <small>The exact position in search results also depends on content quality, competition, indexing and Google Business Profile.</small>
    </aside>
</div>
<?php require __DIR__.'/_footer.php'; ?>
