<?php
$pageTitle=$pageTitle??"Castro's Ready Admin";$active=$active??'';$flash=take_flash();$admin=current_admin();$set=settings();$brand=$set['admin_brand_name']??"Castro's Ready Admin";$brandLogo=$set['admin_logo_path']??'assets/logo.jpg';$newEst=(int)db()->query("SELECT COUNT(*) FROM estimate_requests WHERE status='new'")->fetchColumn();$unreadNotes=(int)db()->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read=0")->fetchColumn();$maintenance=($set['maintenance_mode']??'0')==='1';$avatar=$admin['avatar_path']??'';$favicon=$set['favicon_path']??($set['admin_logo_path']??'assets/logo.jpg');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><title><?=h($pageTitle)?></title><link rel="icon" href="../<?=h($favicon)?>"><link rel="shortcut icon" href="../<?=h($favicon)?>"><link rel="stylesheet" href="../assets/vendor/sweetalert2/sweetalert2.min.css"><link rel="stylesheet" href="../assets/vendor/show-notify/showNotify.css"><link rel="stylesheet" href="admin.css"></head><body>
<header class="admin-header"><div class="admin-head-inner">
  <div class="head-left"><button class="icon-btn mobile-menu" type="button" aria-label="Open menu" aria-expanded="false" data-sidebar-toggle><span class="mobile-menu-glyph" aria-hidden="true">☰</span></button><a class="admin-brand" href="dashboard.php"><img src="../<?=h($brandLogo)?>" alt=""><span><strong><?=h($brand)?></strong><small>Content Management System</small></span></a></div>
  <div class="top-shortcuts">
    <a class="quick-link" href="dashboard.php" title="Dashboard"><?=icon('dashboard')?><span>Dashboard</span></a>
    <a class="quick-link header-extra" href="content.php" title="Page content"><?=icon('edit')?><span>Content</span></a>
    <a class="quick-link header-extra" href="gallery.php" title="Gallery"><?=icon('image')?><span>Gallery</span></a>
    <a class="quick-link" href="estimates.php" title="Estimate requests"><?=icon('mail')?><span>Requests</span><?php if($newEst):?><b><?=$newEst?></b><?php endif;?></a>
    <a class="quick-link status-link <?=$maintenance?'maintenance':''?>" href="settings.php#site-status" title="Website status"><span class="status-dot"></span><span><?=$maintenance?'Maintenance':'Live'?></span></a>
    <a class="quick-link public-view-link" href="../" target="_blank" rel="noopener" title="View public website status"><?=icon('eye')?><span>View site</span></a>
  </div>
  <div class="mobile-header-actions" aria-label="Quick website controls">
    <a class="mobile-status <?= $maintenance?'maintenance':'' ?>" href="settings.php#site-status" title="Website status"><span class="status-dot"></span><b><?= $maintenance?'Maintenance':'Live' ?></b></a>
    <a class="mobile-view" href="../" target="_blank" rel="noopener" title="View website" aria-label="View website"><span class="mobile-view-glyph" aria-hidden="true">↗</span><b>Site</b></a>
  </div>
  <details class="profile-menu"><summary><?php if($avatar):?><img src="../<?=h($avatar)?>" alt="Profile"><?php else:?><span class="avatar-fallback"><?=strtoupper(substr($admin['full_name']?:$admin['username'],0,1))?></span><?php endif;?><span class="profile-copy"><strong><?=h($admin['full_name']?:$admin['username'])?></strong><small><?=h($admin['email']?:'Administrator')?></small></span><span class="chev">⌄</span></summary><div class="profile-dropdown"><div class="profile-head"><strong><?=h($admin['full_name']?:$admin['username'])?></strong><span><?=h($admin['email']?:$admin['username'])?></span></div><a href="profile.php"><?=icon('user')?>Profile & security</a><a href="email.php"><?=icon('mail')?>Email configuration</a><a href="settings.php"><?=icon('gear')?>Site settings</a><a href="notifications.php"><?=icon('mail')?>Notifications<?php if($unreadNotes):?><b class="menu-count"><?=$unreadNotes?></b><?php endif;?></a><hr><a class="logout-link" href="logout.php" data-logout-confirm>Log out</a></div></details>
</div></header>
<div class="admin-shell"><aside class="admin-sidebar" data-sidebar><div class="sidebar-label">MANAGE WEBSITE</div>
<a class="<?= $active==='dashboard'?'active':'' ?>" href="dashboard.php"><?=icon('dashboard')?><span>Dashboard</span></a>
<a class="<?= $active==='content'?'active':'' ?>" href="content.php"><?=icon('edit')?><span>Page content</span></a>
<a class="<?= $active==='sections'?'active':'' ?>" href="sections.php"><?=icon('dashboard')?><span>Section manager</span></a>
<a class="<?= $active==='media'?'active':'' ?>" href="media.php"><?=icon('image')?><span>Media Library</span></a>
<a class="<?= $active==='services'?'active':'' ?>" href="services.php"><?=icon('tools')?><span>Services</span></a>
<a class="<?= $active==='gallery'?'active':'' ?>" href="gallery.php"><?=icon('image')?><span>Gallery</span></a>
<a class="<?= $active==='areas'?'active':'' ?>" href="areas.php"><?=icon('pin')?><span>Service areas</span></a>
<a class="<?= $active==='tips'?'active':'' ?>" href="tips.php"><?=icon('bulb')?><span>Home tips</span></a>
<div class="sidebar-label">BUSINESS</div>
<a class="<?= $active==='estimates'?'active':'' ?>" href="estimates.php"><?=icon('mail')?><span>Estimate requests</span><?php if($newEst):?><em><?=$newEst?></em><?php endif;?></a>
<a class="<?= $active==='email'?'active':'' ?>" href="email.php"><?=icon('mail')?><span>Email</span></a>
<a class="<?= $active==='integrations'?'active':'' ?>" href="integrations.php"><?=icon('api')?><span>Integrations & APIs</span></a>
<div class="sidebar-label">OPTIMIZE</div>
<a class="<?= $active==='seo'?'active':'' ?>" href="seo.php"><?=icon('eye')?><span>SEO Manager</span></a>
<a class="<?= $active==='health'?'active':'' ?>" href="health.php"><?=icon('gear')?><span>Website Health</span></a>
<a class="<?= $active==='notifications'?'active':'' ?>" href="notifications.php"><?=icon('mail')?><span>Activity Center</span><?php if($unreadNotes):?><em><?=$unreadNotes?></em><?php endif;?></a>
<a class="<?= $active==='backups'?'active':'' ?>" href="backups.php"><?=icon('dashboard')?><span>Backup & Restore</span></a>
<div class="sidebar-label">SYSTEM</div>
<a class="<?= $active==='settings'?'active':'' ?>" href="settings.php"><?=icon('gear')?><span>Settings</span></a>
<a class="<?= $active==='profile'?'active':'' ?>" href="profile.php"><?=icon('user')?><span>Profile & security</span></a>
</aside><div class="sidebar-backdrop" data-sidebar-backdrop></div><main class="admin-main admin-content">
<?php if($flash): ?><div hidden data-flash-message="<?=h($flash['message'])?>" data-flash-type="<?=h($flash['type'])?>"></div><?php endif; ?>
