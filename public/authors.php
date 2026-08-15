<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

$rows = [];
try {
    if (db_table_exists('authors') && db_table_exists('item_authors')) {
        $authorsSql = db_column_exists('item_authors', 'item_id')
            ? 'SELECT a.id, a.dmm_id, a.name, a.ruby
             FROM authors a
             WHERE a.name IS NOT NULL
               AND a.name <> ""
               AND EXISTS (
                 SELECT 1
                 FROM item_authors ia
                 INNER JOIN items i ON i.id = ia.item_id
                 WHERE ia.dmm_id = a.dmm_id
                   AND ' . items_product_source_where('i') . '
             )
             ORDER BY COALESCE(NULLIF(a.ruby, ""), a.name) ASC, a.id ASC'
            : 'SELECT a.id, a.dmm_id, a.name, a.ruby
             FROM authors a
             WHERE a.name IS NOT NULL
               AND a.name <> ""
               AND EXISTS (
                 SELECT 1
                 FROM item_authors ia
                 INNER JOIN items i ON i.content_id = ia.content_id
                 WHERE ia.author_id = a.id
                   AND ' . items_product_source_where('i') . '
               )
             ORDER BY COALESCE(NULLIF(a.ruby, ""), a.name) ASC, a.id ASC';
        $stmt = db()->query($authorsSql);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
} catch (Throwable) {
    $rows = [];
}

$displayRows = [];
$seen = [];
foreach ($rows as $row) {
    $name = trim((string)($row['name'] ?? ''));
    if ($name === '' || pcf_is_noise_name($name)) {
        continue;
    }
    $signature = mb_strtolower($name, 'UTF-8');
    if (isset($seen[$signature])) {
        continue;
    }
    $seen[$signature] = true;
    $displayRows[] = $row;
}

$kanaOrder = ['あ', 'か', 'さ', 'た', 'な', 'は', 'ま', 'や', 'ら', 'わ'];
$groups = array_fill_keys($kanaOrder, []);
$groups['A-Z'] = [];
$groups['その他'] = [];

foreach ($displayRows as $row) {
    $indexText = trim((string)($row['ruby'] ?? ''));
    if ($indexText === '') {
        $indexText = trim((string)($row['name'] ?? ''));
    }
    $first = mb_substr(mb_convert_kana($indexText, 'c', 'UTF-8'), 0, 1);
    $group = match (true) {
        preg_match('/^[ぁ-お]/u', $first) === 1 => 'あ',
        preg_match('/^[か-ご]/u', $first) === 1 => 'か',
        preg_match('/^[さ-ぞ]/u', $first) === 1 => 'さ',
        preg_match('/^[た-ど]/u', $first) === 1 => 'た',
        preg_match('/^[な-の]/u', $first) === 1 => 'な',
        preg_match('/^[は-ぽ]/u', $first) === 1 => 'は',
        preg_match('/^[ま-も]/u', $first) === 1 => 'ま',
        preg_match('/^[や-よ]/u', $first) === 1 => 'や',
        preg_match('/^[ら-ろ]/u', $first) === 1 => 'ら',
        preg_match('/^[わ-ん]/u', $first) === 1 => 'わ',
        preg_match('/^[A-Za-z]/', $first) === 1 => 'A-Z',
        default => 'その他',
    };
    $groups[$group][] = $row;
}

$title = '作者一覧';
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero('作者一覧', '作者名を選ぶと関連作品を表示します。'); ?>

<?php if ($displayRows !== []): ?>
  <div class="pcf-kana-directory">
    <?php foreach ($groups as $group => $groupRows): ?>
      <?php if ($groupRows === []): continue; endif; ?>
      <section class="pcf-index-block" style="content-visibility:auto;contain-intrinsic-size:500px;">
        <h2 class="pcf-section-title"><?= e($group === 'A-Z' || $group === 'その他' ? $group : $group . '行') ?></h2>
        <div class="pcf-list-card__meta pcf-chip-list">
          <?php foreach ($groupRows as $row): ?>
            <a class="pcf-chip" href="<?= e(public_url('author.php?id=' . (int)($row['id'] ?? 0))) ?>"><?= e((string)($row['name'] ?? '')) ?></a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <?php pcf_render_empty('作者情報がありません。商品APIの同期後、作者情報がある作品だけ自動で追加されます。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
