<?php
require __DIR__.'/bootstrap.php';
require_permission('content.view');
$pdo=db();
$set=settings();
$error='';

$tableReady=false;
try {
    $tableReady=(bool)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='flexible_content_blocks'")->fetchColumn();
} catch(Throwable $ignored) {
    $tableReady=false;
}

if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    if(!user_can('content.edit')) {
        http_response_code(403);
        exit('Access denied.');
    }
    $action=(string)($_POST['action']??'');
    try {
        if(!$tableReady)throw new RuntimeException('Run database-update.sql before using Flexible Content.');
        if($action==='section') {
            save_setting('flexible_section_kicker',trim((string)($_POST['section_kicker']??'')));
            save_setting('flexible_section_title',trim((string)($_POST['section_title']??'')));
            save_setting('flexible_section_text',trim((string)($_POST['section_text']??'')));
            log_activity('flexible_section_update','Updated flexible content section heading');
            flash('success','Section heading saved.');
        } elseif($action==='save') {
            $id=(int)($_POST['id']??0);
            $title=trim((string)($_POST['title']??''));
            $body=trim((string)($_POST['body']??''));
            $imagePath=trim((string)($_POST['current_image_path']??''));
            $imageAlt=trim((string)($_POST['image_alt']??''));
            $buttonText=trim((string)($_POST['button_text']??''));
            $buttonUrl=trim((string)($_POST['button_url']??''));
            $sortOrder=(int)($_POST['sort_order']??0);
            $active=isset($_POST['active'])?1:0;

            if($title==='')throw new RuntimeException('Enter a title for this content block.');
            if(isset($_POST['remove_image']))$imagePath='';
            $libraryPath=trim((string)($_POST['media_path']??''));
            if($libraryPath!=='') {
                $check=$pdo->prepare("SELECT COUNT(*) FROM media_library WHERE file_path=? AND mime_type LIKE 'image/%'");
                $check->execute([$libraryPath]);
                if((int)$check->fetchColumn()!==1)throw new RuntimeException('Select a valid image from the Media Library.');
                $imagePath=$libraryPath;
            }
            if(!empty($_FILES['image']['name'])) {
                $imagePath=upload_image($_FILES['image'],'flexible-content','content-block',12);
                media_add($imagePath,$title,$sortOrder);
            }
            if($buttonUrl!==''&&!filter_var($buttonUrl,FILTER_VALIDATE_URL)&&!str_starts_with($buttonUrl,'#')&&!str_starts_with($buttonUrl,'/')) {
                throw new RuntimeException('The button URL must be a complete URL or an internal link beginning with # or /.');
            }

            if($id>0) {
                $pdo->prepare('UPDATE flexible_content_blocks SET title=?,body=?,image_path=?,image_alt=?,button_text=?,button_url=?,sort_order=?,active=? WHERE id=?')
                    ->execute([$title,$body,$imagePath,$imageAlt,$buttonText,$buttonUrl,$sortOrder,$active,$id]);
            } else {
                $pdo->prepare('INSERT INTO flexible_content_blocks(title,body,image_path,image_alt,button_text,button_url,sort_order,active) VALUES(?,?,?,?,?,?,?,?)')
                    ->execute([$title,$body,$imagePath,$imageAlt,$buttonText,$buttonUrl,$sortOrder,$active]);
            }
            log_activity('flexible_content_save','Saved flexible public content',['block_id'=>$id?:null,'title'=>$title,'published'=>$active]);
            flash('success',$active?'Content saved and published.':'Content saved privately. Use Preview before publishing.');
        } elseif($action==='delete') {
            $id=(int)($_POST['id']??0);
            $pdo->prepare('DELETE FROM flexible_content_blocks WHERE id=?')->execute([$id]);
            log_activity('flexible_content_delete','Deleted flexible public content',['block_id'=>$id]);
            flash('success','Content block deleted.');
        }
        header('Location: flexible-content.php');
        exit;
    } catch(Throwable $e) {
        $error=$e->getMessage();
    }
}

$edit=null;
$rows=[];
$nextOrder=10;
if($tableReady) {
    if(isset($_GET['edit'])) {
        $st=$pdo->prepare('SELECT * FROM flexible_content_blocks WHERE id=?');
        $st->execute([(int)$_GET['edit']]);
        $edit=$st->fetch()?:null;
    }
    $rows=$pdo->query('SELECT * FROM flexible_content_blocks ORDER BY sort_order,id')->fetchAll();
    $nextOrder=(int)$pdo->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM flexible_content_blocks')->fetchColumn();
}
$libraryImages=[];
try {
    $libraryImages=$pdo->query("SELECT file_path,title FROM media_library WHERE mime_type LIKE 'image/%' ORDER BY sort_order,id LIMIT 150")->fetchAll();
} catch(Throwable $ignored) {
    $libraryImages=[];
}

$pageTitle='Flexible Content';
$active='flexible-content';
require __DIR__.'/_header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">UNLIMITED PUBLIC CONTENT</p>
        <h1>Flexible content blocks</h1>
        <p class="muted">Add as many image and text cards as the website needs. Every item has its own order, private preview and publication control.</p>
    </div>
    <div class="heading-actions">
        <a class="button secondary" href="../?preview=1#custom-content" target="_blank" rel="noopener"><?=icon('eye')?>Private preview</a>
        <a class="button" href="flexible-content.php"><?=icon('edit')?>New block</a>
    </div>
</div>

<?php if(!$tableReady): ?>
<div class="alert error"><strong>Database update required:</strong> import <code>database-update.sql</code> once, then reload this page.</div>
<?php endif; ?>
<?php if($error!==''): ?><div class="alert error"><?=h($error)?></div><?php endif; ?>

<?php if($tableReady): ?>
<section class="panel flexible-heading-panel">
    <div class="panel-heading"><div class="panel-icon"><?=icon('edit')?></div><div><h2>Public section heading</h2><p>This text appears above all additional content cards.</p></div></div>
    <form method="post" data-unsaved-form>
        <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
        <input type="hidden" name="action" value="section">
        <div class="two-col">
            <label>Small heading<input name="section_kicker" value="<?=h($set['flexible_section_kicker']??'MORE FROM CASTRO\'S READY')?>"></label>
            <label>Main heading<input name="section_title" value="<?=h($set['flexible_section_title']??'More projects, ideas and information.')?>"></label>
        </div>
        <label>Introduction<textarea name="section_text"><?=h($set['flexible_section_text']??'Explore additional content selected by our team.')?></textarea></label>
        <div class="form-actions"><button><?=icon('edit')?>Save section heading</button></div>
    </form>
</section>

<section class="panel flexible-block-editor">
    <div class="panel-heading"><div class="panel-icon"><?=icon('image')?></div><div><h2><?=$edit?'Edit content block':'Add content block'?></h2><p>Image and button are optional. Save it hidden first when you want to review it privately.</p></div></div>
    <form method="post" enctype="multipart/form-data" data-unsaved-form>
        <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?=h((string)($edit['id']??0))?>">
        <input type="hidden" name="current_image_path" value="<?=h((string)($edit['image_path']??''))?>">
        <div class="two-col">
            <label>Title<input name="title" required value="<?=h((string)($edit['title']??''))?>"></label>
            <label>Display order<input type="number" name="sort_order" value="<?=h((string)($edit['sort_order']??$nextOrder))?>"></label>
        </div>
        <label>Content<textarea name="body" rows="7" placeholder="Write all the information needed for this card."><?=h((string)($edit['body']??''))?></textarea></label>

        <div class="upload-zone premium-media-zone full-width-upload" data-upload-zone tabindex="0">
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-empty-label="Drop, paste or choose an image">
            <div class="upload-icon"><?=icon('image')?></div>
            <strong>Content image</strong>
            <small>Drag & drop, paste from clipboard or select JPG, PNG or WEBP.</small>
            <span class="upload-zone-action">Choose image</span>
            <div class="upload-selection-name" data-upload-name>Drop, paste or choose an image</div>
            <div class="upload-preview premium-upload-preview" data-upload-preview></div>
        </div>

        <div class="two-col">
            <label>Reuse image from Media Library
                <select name="media_path"><option value="">Keep current / upload a new image</option><?php foreach($libraryImages as $image): ?><option value="<?=h($image['file_path'])?>"><?=h($image['title']?:basename($image['file_path']))?></option><?php endforeach; ?></select>
            </label>
            <label>Alternative text<input name="image_alt" value="<?=h((string)($edit['image_alt']??''))?>" placeholder="Describe the image"></label>
        </div>
        <?php if(!empty($edit['image_path'])): ?>
        <div class="saved-image-row flexible-current-image">
            <img src="../<?=h($edit['image_path'])?>" alt="Current content image">
            <div><strong>Current image</strong><small><?=h($edit['image_path'])?></small></div>
            <label class="compact-check"><input type="checkbox" name="remove_image"> Remove image</label>
        </div>
        <?php endif; ?>

        <div class="two-col">
            <label>Button text<input name="button_text" value="<?=h((string)($edit['button_text']??''))?>" placeholder="Learn more"></label>
            <label>Button URL<input name="button_url" value="<?=h((string)($edit['button_url']??''))?>" placeholder="https://... or #estimate"></label>
        </div>
        <label class="premium-switch">
            <input type="checkbox" name="active" <?=!empty($edit['active'])?'checked':''?>>
            <span class="switch-ui"></span><span><b>Publish on website</b><small>Leave disabled to save privately and inspect it using Private preview.</small></span>
        </label>
        <div class="form-actions">
            <button><?=icon('edit')?>Save content block</button>
            <?php if($edit): ?><a class="button secondary" href="flexible-content.php">Cancel editing</a><?php endif; ?>
            <a class="button secondary" href="../?preview=1#custom-content" target="_blank" rel="noopener"><?=icon('eye')?>Preview hidden and published blocks</a>
        </div>
    </form>
</section>

<div class="flexible-content-admin-grid">
<?php foreach($rows as $row): ?>
    <article class="flexible-content-admin-card">
        <?php if(!empty($row['image_path'])): ?><img src="../<?=h($row['image_path'])?>" alt="<?=h($row['image_alt']?:$row['title'])?>"><?php else: ?><div class="flexible-content-no-image"><?=icon('image')?><span>Text-only block</span></div><?php endif; ?>
        <div class="flexible-content-admin-copy">
            <div class="list-head"><div><strong><?=h($row['title'])?></strong><small>Order <?=h((string)$row['sort_order'])?></small></div><span class="badge <?=$row['active']?'contacted':'closed'?>"><?=$row['active']?'Published':'Private'?></span></div>
            <?php if(trim((string)$row['body'])!==''): ?><p><?=h($row['body'])?></p><?php endif; ?>
            <div class="actions">
                <a class="button secondary small" href="?edit=<?=$row['id']?>"><?=icon('edit')?>Edit</a>
                <form method="post" data-swal-confirm="Delete this content block?" data-swal-text="This action cannot be undone.">
                    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$row['id']?>">
                    <button class="button danger-lite small">Delete</button>
                </form>
            </div>
        </div>
    </article>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php require __DIR__.'/_footer.php'; ?>
