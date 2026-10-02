<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
require_once __DIR__ . '/../lib/fixed_pages.php';
auth_require_admin();
$title = '固定ページ一覧';
$message = null;

try {
    fixed_pages_initialize();
} catch (Throwable $e) {
    $message = '固定ページ初期化でエラーが発生しました: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail((string)post('_csrf', ''));
    $id = (int)post('id', 0);
    db()->prepare('UPDATE fixed_pages SET title=:title, body=:body, seo_title=:seo_title, seo_description=:seo_description, is_published=:is_published, updated_at=NOW() WHERE id=:id')->execute([
        ':title' => trim((string)post('title', '')),
        ':body' => trim((string)post('body', '')),
        ':seo_title' => trim((string)post('seo_title', '')),
        ':seo_description' => trim((string)post('seo_description', '')),
        ':is_published' => post('is_published', '0') === '1' ? 1 : 0,
        ':id' => $id,
    ]);
    $savedPage = db()->prepare('SELECT slug FROM fixed_pages WHERE id = ? LIMIT 1');
    $savedPage->execute([$id]);
    $savedSlug = $savedPage->fetchColumn();
    require_once __DIR__ . '/../lib/search_lifecycle.php';
    if (is_string($savedSlug)) {
        pcf_indexnow_enqueue(public_url('page.php') . '?slug=' . rawurlencode($savedSlug));
    }
    pcf_search_cache_invalidate();
    $message = '固定ページを更新しました。';
}

$rows = [];
$edit = null;
try {
    $rows = db()->query('SELECT id,slug,title,body,seo_title,seo_description,is_published,updated_at FROM fixed_pages ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $editId = (int)($_GET['edit'] ?? 0);
    foreach ($rows as $row) {
        if ((int)$row['id'] === $editId) {
            $edit = $row;
            break;
        }
    }
} catch (Throwable $e) {
    if ($message === null) {
        $message = '固定ページの取得に失敗しました: ' . $e->getMessage();
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="admin-card admin-card--form">
  <h1>固定ページ一覧</h1>
  <?php if ($message !== null): ?><p><?= e($message) ?></p><?php endif; ?>
  <table class="admin-table">
    <tr><th>ID</th><th>スラッグ</th><th>タイトル</th><th>公開</th><th>更新日時</th><th>操作</th></tr>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td><?= e((string)$row['id']) ?></td>
        <td><?= e((string)$row['slug']) ?></td>
        <td><?= e((string)$row['title']) ?></td>
        <td><?= (int)$row['is_published'] === 1 ? '公開' : '非公開' ?></td>
        <td><?= e((string)$row['updated_at']) ?></td>
        <td><a href="<?= e(admin_url('pages.php?edit=' . (string)$row['id'])) ?>">編集</a></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <?php if (is_array($edit)): ?>
  <h2>固定ページ編集: <?= e((string)$edit['slug']) ?></h2>
  <form method="post">
    <?= csrf_input() ?>
    <input type="hidden" name="id" value="<?= e((string)$edit['id']) ?>">
    <label>タイトル<input name="title" value="<?= e((string)$edit['title']) ?>" required></label>
    <label>本文<textarea name="body" rows="12" required><?= e((string)$edit['body']) ?></textarea></label>
    <label>SEOタイトル<input name="seo_title" value="<?= e((string)($edit['seo_title'] ?? '')) ?>"></label>
    <label>SEO説明<textarea name="seo_description" rows="3"><?= e((string)($edit['seo_description'] ?? '')) ?></textarea></label>
    <label><input type="checkbox" name="is_published" value="1" <?= ((int)$edit['is_published'] === 1) ? 'checked' : '' ?>> 公開する</label>
    <div class="admin-actions"><button type="submit">更新</button></div>
  </form>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
