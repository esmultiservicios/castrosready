<?php require __DIR__.'/bootstrap.php'; require_login();
$counts=[
 'services'=>(int)db()->query('SELECT COUNT(*) FROM services WHERE active=1')->fetchColumn(),
 'gallery'=>(int)db()->query('SELECT COUNT(*) FROM gallery WHERE active=1')->fetchColumn(),
 'new'=>(int)db()->query("SELECT COUNT(*) FROM estimate_requests WHERE status='new'")->fetchColumn(),
 'total'=>(int)db()->query('SELECT COUNT(*) FROM estimate_requests')->fetchColumn(),
];
$recent=db()->query('SELECT id,full_name,service_needed,status,created_at FROM estimate_requests ORDER BY id DESC LIMIT 6')->fetchAll();
$pageTitle="Dashboard — Castro's Ready";$active='dashboard'; require __DIR__.'/_header.php';?>
<div class="page-heading dashboard-heading">
  <div><p class="eyebrow">DASHBOARD</p><h1>Website administration</h1><p class="muted">Everything needed to manage Castro's Ready without touching code.</p></div>
  <a class="button" href="content.php">Edit website content</a>
</div>

<div class="stat-grid">
  <div class="stat"><span>Active services</span><strong><?=$counts['services']?></strong><small>Published on the site</small></div>
  <div class="stat"><span>Gallery items</span><strong><?=$counts['gallery']?></strong><small>Active project photos</small></div>
  <div class="stat"><span>New estimates</span><strong><?=$counts['new']?></strong><small>Need your attention</small></div>
  <div class="stat"><span>Total estimates</span><strong><?=$counts['total']?></strong><small>Requests received</small></div>
</div>

<section class="dashboard-section">
  <div class="section-heading"><div><p class="eyebrow">CONTENT MANAGER</p><h2>Manage the landing page</h2></div><p>Choose the part of the website you want to update.</p></div>
  <div class="manage-grid">
    <a class="manage-card" href="content.php"><span class="manage-icon">✏️</span><div><strong>Page sections</strong><small>Hero, About, Mission, Vision, Estimate and Contact.</small></div><span class="manage-arrow">→</span></a>
    <a class="manage-card" href="services.php"><span class="manage-icon">🛠️</span><div><strong>Services</strong><small>Add, edit, order or hide services.</small></div><span class="manage-arrow">→</span></a>
    <a class="manage-card" href="gallery.php"><span class="manage-icon">🖼️</span><div><strong>Gallery</strong><small>Upload and manage project images.</small></div><span class="manage-arrow">→</span></a>
    <a class="manage-card" href="areas.php"><span class="manage-icon">📍</span><div><strong>Service areas</strong><small>Manage cities, ZIP codes and coverage.</small></div><span class="manage-arrow">→</span></a>
    <a class="manage-card" href="tips.php"><span class="manage-icon">💡</span><div><strong>Home tips</strong><small>Manage educational content and links.</small></div><span class="manage-arrow">→</span></a>
    <a class="manage-card" href="settings.php"><span class="manage-icon">⚙️</span><div><strong>Contact & social</strong><small>Phone, email, hours and social networks.</small></div><span class="manage-arrow">→</span></a>
  </div>
</section>

<section class="dashboard-section">
  <div class="section-heading"><div><p class="eyebrow">CUSTOMER REQUESTS</p><h2>Recent free estimates</h2></div><a class="text-link" href="estimates.php">View all requests →</a></div>
  <?php if(!$recent):?><div class="empty-state"><span>📭</span><strong>No requests yet</strong><p>New estimate requests will appear here automatically.</p></div><?php else:?><div class="request-grid"><?php foreach($recent as $r):?><article class="request-card"><div class="request-top"><div><strong><?=h($r['full_name'])?></strong><small><?=h($r['created_at'])?></small></div><span class="badge <?=h($r['status'])?>"><?=h($r['status'])?></span></div><p><?=h($r['service_needed'] ?: 'General project')?></p><a href="estimates.php">Open request →</a></article><?php endforeach;?></div><?php endif;?>
</section>

<section class="handoff-card"><div><span class="manage-icon">🔐</span><div><strong>Preparing the site for client handoff?</strong><p>You can securely reset administrator access without deleting any website content.</p></div></div><a class="button secondary" href="account.php">Administrator access</a></section>
<?php require __DIR__.'/_footer.php';
