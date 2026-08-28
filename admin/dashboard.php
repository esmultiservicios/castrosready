<?php require __DIR__.'/bootstrap.php'; require_login();
$counts=[
 'services'=>(int)db()->query('SELECT COUNT(*) FROM services WHERE active=1')->fetchColumn(),
 'gallery'=>(int)db()->query('SELECT COUNT(*) FROM gallery WHERE active=1')->fetchColumn(),
 'new'=>(int)db()->query("SELECT COUNT(*) FROM estimate_requests WHERE status='new'")->fetchColumn(),
 'total'=>(int)db()->query('SELECT COUNT(*) FROM estimate_requests')->fetchColumn(),
];
$recent=db()->query('SELECT id,full_name,service_needed,status,created_at FROM estimate_requests ORDER BY id DESC LIMIT 6')->fetchAll();
$pageTitle="Dashboard — Castro's Ready";$active='dashboard'; require __DIR__.'/_header.php';?>
<p class="eyebrow">DASHBOARD</p><h1>Website administration</h1><p class="muted">Edit the public landing page without touching code.</p><div class="stat-grid"><div class="stat"><span>Active services</span><strong><?=$counts['services']?></strong></div><div class="stat"><span>Gallery items</span><strong><?=$counts['gallery']?></strong></div><div class="stat"><span>New estimates</span><strong><?=$counts['new']?></strong></div><div class="stat"><span>Total estimates</span><strong><?=$counts['total']?></strong></div></div><section class="panel wide"><h2>Recent estimate requests</h2><?php if(!$recent):?><p class="muted">No requests yet.</p><?php else:?><div class="table-wrap"><table class="admin-table"><thead><tr><th>Name</th><th>Service</th><th>Status</th><th>Date</th></tr></thead><tbody><?php foreach($recent as $r):?><tr><td><?=h($r['full_name'])?></td><td><?=h($r['service_needed'])?></td><td><span class="badge <?=h($r['status'])?>"><?=h($r['status'])?></span></td><td><?=h($r['created_at'])?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
<?php require __DIR__.'/_footer.php';
