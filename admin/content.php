<?php require __DIR__.'/bootstrap.php'; require_login();
$groups=[
 'home'=>['title'=>'Home / Hero','description'=>'The first message visitors see when they open the website.','icon'=>'🏠','fields'=>[
   'hero_eyebrow'=>['Hero eyebrow','input'],'hero_title'=>['Hero title','input'],'hero_text'=>['Hero description','textarea'],'intro_title'=>['Intro title','input'],'intro_text'=>['Intro text','textarea']]],
 'about'=>['title'=>'About Us','description'=>'Company story and the message that builds trust.','icon'=>'👷','fields'=>[
   'about_title'=>['About title','input'],'about_text'=>['About paragraph 1','textarea'],'about_text_2'=>['About paragraph 2','textarea'],'mission'=>['Mission','textarea'],'vision'=>['Vision','textarea']]],
 'areas'=>['title'=>'Service Areas','description'=>'Headline and supporting text for the coverage section.','icon'=>'📍','fields'=>[
   'areas_title'=>['Service Areas title','input'],'areas_text'=>['Service Areas text','textarea']]],
 'estimate'=>['title'=>'Free Estimate','description'=>'Text displayed above the customer estimate form.','icon'=>'📝','fields'=>[
   'estimate_title'=>['Estimate title','input'],'estimate_text'=>['Estimate text','textarea']]],
 'contact'=>['title'=>'Contact','description'=>'Main heading for the contact section. Contact details are managed in Settings.','icon'=>'☎️','fields'=>[
   'contact_title'=>['Contact title','input']]],
];
$fields=[]; foreach($groups as $group){ foreach($group['fields'] as $k=>$meta){ $fields[$k]=$meta[0]; } }
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$st=db()->prepare('INSERT INTO site_content(content_key,content_value) VALUES(?,?) ON DUPLICATE KEY UPDATE content_value=VALUES(content_value)');foreach($fields as $k=>$label)$st->execute([$k,trim((string)($_POST[$k]??''))]);flash('success','Website content saved.');header('Location: content.php');exit;}
$content=site_content();$pageTitle='Page Content';$active='content';require __DIR__.'/_header.php';?>
<div class="page-heading"><div><p class="eyebrow">PAGE CONTENT</p><h1>Edit landing page sections</h1><p class="muted">Content is organized the same way visitors see it on the public landing page.</p></div><a class="button secondary" href="../" target="_blank">Preview website</a></div>
<form method="post" class="cms-form"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
  <div class="content-grid">
  <?php foreach($groups as $group):?>
    <section class="content-card animate-in">
      <header><div class="panel-icon"><?=$group['icon']?></div><div><h2><?=h($group['title'])?></h2><p><?=h($group['description'])?></p></div></header>
      <div class="field-stack">
      <?php foreach($group['fields'] as $k=>$meta): $label=$meta[0]; $type=$meta[1]; ?>
        <label><?=h($label)?><?php if($type==='textarea'):?><textarea name="<?=h($k)?>"><?=h($content[$k]??'')?></textarea><?php else:?><input name="<?=h($k)?>" value="<?=h($content[$k]??'')?>"><?php endif;?></label>
      <?php endforeach;?>
      </div>
    </section>
  <?php endforeach;?>
  </div>
  <div class="savebar"><button type="submit">Save all page content</button></div>
</form>
<?php require __DIR__.'/_footer.php';
