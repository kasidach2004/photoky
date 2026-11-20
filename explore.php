<?php
/**
 * PhotoKY - Explore Gallery (Pexels-Style Masonry & Search)
 */

$pageTitle = 'สำรวจรูปภาพทั้งหมด';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'latest';
$extFilter = strtolower(trim($_GET['ext'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;
$offset = ($page - 1) * $perPage;

$photos = [];
$totalPhotos = 0;
$totalPages = 1;

if ($db) {
    $where = "WHERE p.is_public = 1 AND p.is_approved = 1";
    $params = [];

    if (!empty($search)) {
        $where .= " AND (p.title LIKE :q OR p.original_name LIKE :q OR p.description LIKE :q OR p.owner_name LIKE :q OR p.extra_info LIKE :q)";
        $params[':q'] = "%{$search}%";
    }

    if (!empty($extFilter) && in_array($extFilter, ['jpg', 'png', 'webp', 'gif'], true)) {
        $where .= " AND p.file_ext = :ext";
        $params[':ext'] = $extFilter;
    }

    switch ($sort) {
        case 'popular':
            $orderSql = " ORDER BY p.views DESC, p.id DESC";
            break;
        case 'size':
            $orderSql = " ORDER BY p.file_size DESC";
            break;
        case 'oldest':
            $orderSql = " ORDER BY p.id ASC";
            break;
        case 'latest':
        default:
            $orderSql = " ORDER BY p.id DESC";
            break;
    }

    $countStmt = $db->prepare("SELECT COUNT(*) FROM photos p {$where}");
    $countStmt->execute($params);
    $totalPhotos = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalPhotos / $perPage));

    $stmt = $db->prepare("SELECT p.* FROM photos p {$where} {$orderSql} LIMIT {$perPage} OFFSET {$offset}");
    $stmt->execute($params);
    $photos = $stmt->fetchAll();
}
?>

<div class="container py-5">
    <!-- Header & Controls -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h2 fw-bold mb-1">สำรวจคลังรูปภาพ</h1>
            <p class="text-muted small mb-0">พบ <?= number_format($totalPhotos) ?> รูปภาพที่ผ่านการตรวจสอบและอนุมัติแล้ว</p>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="card border rounded-4 p-3 mb-4 shadow-sm">
        <form action="<?= BASE_URL ?>/explore.php" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="text" name="q" class="form-control rounded-pill px-3" placeholder="ค้นหาจากชื่อภาพ, เจ้าของภาพ, หรือรายละเอียด..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="sort" class="form-select rounded-pill" onchange="this.form.submit()">
                    <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>มาใหม่ล่าสุด</option>
                    <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>ยอดเข้าชมสูงสุด</option>
                    <option value="size" <?= $sort === 'size' ? 'selected' : '' ?>>ความละเอียดสูงสุด</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>เก่าสุดก่อน</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="ext" class="form-select rounded-pill" onchange="this.form.submit()">
                    <option value="">ทุกประเภทไฟล์</option>
                    <?php foreach (['jpg', 'png', 'webp', 'gif'] as $extOpt): ?>
                        <option value="<?= $extOpt ?>" <?= $extFilter === $extOpt ? 'selected' : '' ?>><?= strtoupper($extOpt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn-pill-primary btn-sm flex-grow-1">ค้นหา</button>
                <?php if ($search !== '' || $extFilter !== ''): ?>
                    <a href="<?= BASE_URL ?>/explore.php" class="btn-pill-secondary btn-sm px-2">ล้าง</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if (empty($photos)): ?>
        <div class="text-center py-5 border rounded-4 bg-light">
            <div class="text-muted mb-3">ไม่พบรูปภาพที่ตรงกับคำค้นหาของคุณ</div>
            <a href="<?= BASE_URL ?>/explore.php" class="btn-pill-secondary">ดูรูปภาพทั้งหมด</a>
        </div>
    <?php else: ?>
        <!-- Pexels Masonry Grid -->
        <div class="pexels-masonry">
            <?php foreach ($photos as $photo): ?>
                <?php 
                    $thumbPath = BASE_URL . '/uploads/thumbnails/' . $photo['filename'];
                    $viewUrl = BASE_URL . '/photo.php?uuid=' . $photo['uuid'];
                    $downloadUrl = BASE_URL . '/photo.php?uuid=' . $photo['uuid'] . '&download=1';
                    $photoTitleDisplay = !empty($photo['title']) ? $photo['title'] : $photo['original_name'];
                    $ownerDisplay = !empty($photo['owner_name']) ? $photo['owner_name'] : '';
                    $avatarChar = mb_substr($ownerDisplay !== '' ? $ownerDisplay : $photoTitleDisplay, 0, 1);
                ?>
                <div class="pexels-item" id="photo_<?= e($photo['uuid']) ?>">
                    <a href="<?= $viewUrl ?>" class="d-block">
                        <img src="<?= $thumbPath ?>" alt="<?= e($photoTitleDisplay) ?>" class="pexels-item-img" loading="lazy" decoding="async">
                    </a>
                    <div class="pexels-item-overlay" onclick="if(event.target === this) window.location.href='<?= $viewUrl ?>';" style="cursor: pointer;">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <span class="badge bg-dark bg-opacity-75 text-white rounded-pill px-3 py-1 text-truncate" style="max-width: 70%; font-size: 0.78rem; backdrop-filter: blur(4px);">
                                <?= e($photoTitleDisplay) ?>
                            </span>
                            <span class="badge bg-dark bg-opacity-50 text-white rounded-pill px-2 py-1" style="font-size: 0.72rem; backdrop-filter: blur(4px);">
                                <?= (int)$photo['width'] ?>×<?= (int)$photo['height'] ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <a href="<?= $viewUrl ?>" class="pexels-author-badge text-truncate">
                                <span class="pexels-author-avatar"><?= e($avatarChar) ?></span>
                                <div class="text-truncate">
                                    <div class="fw-bold text-truncate" style="line-height: 1.2;"><?= e($photoTitleDisplay) ?></div>
                                    <?php if ($ownerDisplay !== ''): ?>
                                        <div class="small opacity-75 text-truncate" style="font-size: 0.74rem;">โดย <?= e($ownerDisplay) ?></div>
                                    <?php endif; ?>
                                </div>
                            </a>
                            <a href="<?= $downloadUrl ?>" class="pexels-download-btn" title="ดาวน์โหลดภาพต้นฉบับ">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="7 10 12 15 17 10"></polyline>
                                    <line x1="12" y1="15" x2="12" y2="3"></line>
                                </svg>
                                <span>ดาวน์โหลด</span>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-center gap-2 mt-5">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>&ext=<?= urlencode($extFilter) ?>" class="btn-pill-secondary btn-sm">ก่อนหน้า</a>
                <?php endif; ?>
                
                <span class="d-flex align-items-center px-3 small text-muted">หน้า <?= $page ?> จาก <?= $totalPages ?></span>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>&ext=<?= urlencode($extFilter) ?>" class="btn-pill-secondary btn-sm">ถัดไป</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
