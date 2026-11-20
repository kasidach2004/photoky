<?php
/**
 * PhotoKY - Admin Photos & Owner Metadata Management
 * Add, search, edit owner/photo details (without re-uploading), and single/bulk delete
 */

$adminPageTitle = 'Manage Photos';
require_once __DIR__ . '/header.php';

$db = getDB();
$csrfToken = getCsrfToken();

// Handle Bulk Actions POST
$flashSuccess = '';
$flashError = '';
$bulkAction = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($bulkAction, ['bulk_delete', 'bulk_approve', 'bulk_reject'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $flashError = 'Invalid security token.';
    } else {
        $selectedIds = $_POST['photo_ids'] ?? [];
        if (is_array($selectedIds) && count($selectedIds) > 0) {
            $cleanIds = array_values(array_filter(array_map('intval', $selectedIds), fn($id) => $id > 0));
            if (!empty($cleanIds)) {
                if ($bulkAction === 'bulk_delete') {
                    $deletedCount = 0;
                    foreach ($cleanIds as $pid) {
                        $s = $db->prepare("SELECT id, filename FROM photos WHERE id = :id LIMIT 1");
                        $s->execute([':id' => $pid]);
                        $row = $s->fetch();
                        if ($row) {
                            @unlink(ORIGINALS_PATH . '/' . $row['filename']);
                            @unlink(THUMBNAILS_PATH . '/' . $row['filename']);
                            $db->prepare("DELETE FROM photos WHERE id = :id")->execute([':id' => $pid]);
                            $deletedCount++;
                        }
                    }
                    if ($deletedCount > 0) {
                        logSystemEvent('admin_bulk_delete_photos', "Deleted {$deletedCount} photo(s)");
                        $flashSuccess = "Successfully deleted {$deletedCount} photo(s).";
                    }
                } elseif ($bulkAction === 'bulk_approve') {
                    $ph = implode(',', array_fill(0, count($cleanIds), '?'));
                    $db->prepare("UPDATE photos SET is_approved = 1 WHERE id IN ({$ph})")->execute($cleanIds);
                    logSystemEvent('admin_bulk_approve_photos', "Approved " . count($cleanIds) . " photo(s)");
                    $flashSuccess = "Successfully approved " . count($cleanIds) . " photo(s).";
                } elseif ($bulkAction === 'bulk_reject') {
                    $ph = implode(',', array_fill(0, count($cleanIds), '?'));
                    $db->prepare("UPDATE photos SET is_approved = 2 WHERE id IN ({$ph})")->execute($cleanIds);
                    logSystemEvent('admin_bulk_reject_photos', "Rejected " . count($cleanIds) . " photo(s)");
                    $flashSuccess = "Successfully rejected " . count($cleanIds) . " photo(s).";
                }
            }
        }
    }
}

// Filters
$search = trim($_GET['q'] ?? '');
$extFilter = trim($_GET['ext'] ?? '');
$folderFilter = (int)($_GET['folder'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = "(p.title LIKE :q OR p.original_name LIKE :q OR p.uuid LIKE :q OR p.owner_name LIKE :q OR p.owner_phone LIKE :q OR p.extra_info LIKE :q OR p.ip_address LIKE :q)";
    $params[':q'] = "%{$search}%";
}
if ($extFilter !== '') {
    $where[] = "p.file_ext = :ext";
    $params[':ext'] = $extFilter;
}
if ($folderFilter > 0) {
    $where[] = "p.folder_id = :fid";
    $params[':fid'] = $folderFilter;
}
if ($statusFilter === 'pending') {
    $where[] = "p.is_approved = 0";
} elseif ($statusFilter === 'approved') {
    $where[] = "p.is_approved = 1";
} elseif ($statusFilter === 'rejected') {
    $where[] = "p.is_approved = 2";
}

$whereSql = implode(' AND ', $where);

$totalPhotos = 0;
$totalPages = 1;
$photos = [];
$folders = [];
$autoEditPhoto = null;

if ($db) {
    try {
        $countStmt = $db->prepare("SELECT COUNT(*) FROM photos p WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalPhotos = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalPhotos / $perPage));

        $sql = "
            SELECT p.*,
                   (SELECT COUNT(*) FROM comments c WHERE c.photo_id = p.id) as comment_count
            FROM photos p
            WHERE {$whereSql}
            ORDER BY p.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $photos = $stmt->fetchAll();

        // Status counts
        $statusCounts = ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
        try {
            $statusCounts['all'] = (int)$db->query("SELECT COUNT(*) FROM photos")->fetchColumn();
            $statusCounts['pending'] = (int)$db->query("SELECT COUNT(*) FROM photos WHERE is_approved = 0")->fetchColumn();
            $statusCounts['approved'] = (int)$db->query("SELECT COUNT(*) FROM photos WHERE is_approved = 1")->fetchColumn();
            $statusCounts['rejected'] = (int)$db->query("SELECT COUNT(*) FROM photos WHERE is_approved = 2")->fetchColumn();
        } catch (\Throwable $scEx) {}

        // Auto-open edit modal if ?edit=ID
        if (!empty($_GET['edit'])) {
            $es = $db->prepare("SELECT * FROM photos WHERE id = :id LIMIT 1");
            $es->execute([':id' => (int)$_GET['edit']]);
            $autoEditPhoto = $es->fetch() ?: null;
        }
    } catch (\Throwable $e) {}
} else {
    $statusCounts = ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">จัดการและอนุมัติรูปภาพ</h1>
        <p class="text-muted small mb-0">แสดง <?= number_format($totalPhotos) ?> รูป • รอการตรวจสอบ <?= number_format($statusCounts['pending']) ?> รูป</p>
    </div>
    <button type="button" class="btn-pill-primary btn-sm" data-bs-toggle="modal" data-bs-target="#adminUploadModal">
        + อัปโหลดรูปภาพใหม่
    </button>
</div>

<?php if ($flashSuccess): ?>
    <div class="alert alert-success py-2 px-3 small rounded-3 mb-3"><?= e($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
    <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3"><?= e($flashError) ?></div>
<?php endif; ?>

<!-- Status Filter Pills -->
<ul class="nav nav-pills mb-3 gap-2">
    <li class="nav-item">
        <a class="nav-link rounded-pill px-3 py-1 small <?= empty($statusFilter) ? 'active bg-dark text-white' : 'bg-white text-secondary border' ?>" href="<?= BASE_URL ?>/admin/photos.php<?= !empty($search) ? '?q=' . urlencode($search) : '' ?>">
            รูปภาพทั้งหมด <span class="badge bg-secondary rounded-pill ms-1"><?= number_format($statusCounts['all']) ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link rounded-pill px-3 py-1 small <?= $statusFilter === 'pending' ? 'active bg-warning text-dark fw-bold shadow-sm' : 'bg-white text-dark border border-warning' ?>" href="<?= BASE_URL ?>/admin/photos.php?status=pending<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>">
            ⏳ รอการอนุมัติ <span class="badge bg-warning text-dark rounded-pill ms-1"><?= number_format($statusCounts['pending']) ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link rounded-pill px-3 py-1 small <?= $statusFilter === 'approved' ? 'active bg-success text-white' : 'bg-white text-secondary border' ?>" href="<?= BASE_URL ?>/admin/photos.php?status=approved<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>">
            อนุมัติแล้ว <span class="badge bg-secondary rounded-pill ms-1"><?= number_format($statusCounts['approved']) ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link rounded-pill px-3 py-1 small <?= $statusFilter === 'rejected' ? 'active bg-danger text-white' : 'bg-white text-secondary border' ?>" href="<?= BASE_URL ?>/admin/photos.php?status=rejected<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>">
            ปฏิเสธแล้ว <span class="badge bg-secondary rounded-pill ms-1"><?= number_format($statusCounts['rejected']) ?></span>
        </a>
    </li>
</ul>

<!-- Search & Filter Toolbar -->
<div class="admin-card mb-4">
    <form method="GET" action="<?= BASE_URL ?>/admin/photos.php" class="row g-2 align-items-center">
        <?php if (!empty($statusFilter)): ?>
            <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
        <?php endif; ?>
        <div class="col-md-7">
            <input type="text" name="q" class="form-control form-control-sm rounded-pill px-3" placeholder="ค้นหาจากชื่อภาพ, เจ้าของภาพ, เบอร์โทร, UUID, IP..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-3">
            <select name="ext" class="form-select form-select-sm rounded-pill">
                <option value="">ทุกนามสกุลไฟล์</option>
                <?php foreach (['jpg', 'png', 'webp', 'gif'] as $extOpt): ?>
                    <option value="<?= $extOpt ?>" <?= $extFilter === $extOpt ? 'selected' : '' ?>><?= strtoupper($extOpt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn-pill-primary btn-sm py-1 px-3 flex-grow-1">กรอง</button>
            <?php if ($search !== '' || $extFilter !== '' || $statusFilter !== ''): ?>
                <a href="<?= BASE_URL ?>/admin/photos.php" class="btn-pill-secondary btn-sm py-1 px-2">ล้าง</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Photos Table with Bulk Actions -->
<form method="POST" action="<?= BASE_URL ?>/admin/photos.php" id="bulkForm">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="action" id="bulkActionInput" value="bulk_delete">

    <div class="admin-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
                <input type="checkbox" id="selectAllCheckbox" class="form-check-input">
                <label for="selectAllCheckbox" class="small text-muted mb-0">เลือกทั้งหมด</label>
                <div id="bulkActionsGroup" class="d-none d-flex gap-1 ms-2">
                    <button type="button" class="btn btn-sm btn-success rounded-pill px-3 py-0" onclick="submitBulkAction('bulk_approve')">
                        อนุมัติที่เลือก (<span class="selectedCountText">0</span>)
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3 py-0" onclick="submitBulkAction('bulk_reject')">
                        ปฏิเสธที่เลือก (<span class="selectedCountText">0</span>)
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-0" onclick="submitBulkAction('bulk_delete')">
                        ลบที่เลือก (<span class="selectedCountText">0</span>)
                    </button>
                </div>
            </div>
            <span class="small text-muted">หน้า <?= $page ?> จาก <?= $totalPages ?></span>
        </div>

        <?php if (empty($photos)): ?>
            <div class="text-center text-muted py-5">ไม่พบรูปภาพที่ตรงกับเงื่อนไขการค้นหา</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 32px;"></th>
                            <th>รหัส / รูปภาพ</th>
                            <th>สถานะ</th>
                            <th>เจ้าของภาพ & ข้อมูลติดต่อ</th>
                            <th>การมองเห็น</th>
                            <th>ข้อมูลไฟล์</th>
                            <th>ความคิดเห็น</th>
                            <th>วันที่อัปโหลด</th>
                            <th class="text-end">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($photos as $p): ?>
                        <?php
                            $photoJson = htmlspecialchars(json_encode([
                                'id'          => (int)$p['id'],
                                'uuid'        => $p['uuid'],
                                'title'       => $p['title'] ?? '',
                                'description' => $p['description'] ?? '',
                                'owner_name'  => $p['owner_name'] ?? '',
                                'owner_phone' => $p['owner_phone'] ?? '',
                                'extra_info'  => $p['extra_info'] ?? '',
                                'folder_id'   => (int)($p['folder_id'] ?? 0),
                                'is_public'   => (int)$p['is_public']
                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr id="photo_<?= e($p['uuid']) ?>">
                            <td>
                                <input type="checkbox" name="photo_ids[]" value="<?= (int)$p['id'] ?>" class="form-check-input photo-checkbox">
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <a href="<?= BASE_URL ?>/photo.php?uuid=<?= e($p['uuid']) ?>" target="_blank">
                                        <img src="<?= BASE_URL ?>/uploads/thumbnails/<?= e($p['filename']) ?>" class="rounded border" style="width: 52px; height: 52px; object-fit: cover;" alt="">
                                    </a>
                                    <div class="min-w-0">
                                        <a href="<?= BASE_URL ?>/photo.php?uuid=<?= e($p['uuid']) ?>" target="_blank" class="fw-semibold text-white text-decoration-none d-block text-truncate" style="max-width: 190px;">
                                            <?= e($p['title'] ?: $p['original_name']) ?>
                                        </a>
                                        <div class="text-muted" style="font-size: 0.74rem;">
                                            ID #<?= (int)$p['id'] ?> • <code><?= e($p['file_path'] ?: 'uploads/originals/' . $p['filename']) ?></code>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (($p['is_approved'] ?? 1) == 1): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0" style="font-size: 0.72rem;">อนุมัติแล้ว</span>
                                <?php elseif (($p['is_approved'] ?? 1) == 2): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-0" style="font-size: 0.72rem;">ถูกปฏิเสธ</span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-0" style="font-size: 0.72rem;">รออนุมัติ</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($p['owner_name']) || !empty($p['owner_phone']) || !empty($p['extra_info'])): ?>
                                    <div class="fw-semibold text-white"><?= e($p['owner_name'] ?: '—') ?></div>
                                    <?php if (!empty($p['owner_phone'])): ?>
                                        <div class="text-muted" style="font-size: 0.76rem;"><?= e($p['owner_phone']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($p['extra_info'])): ?>
                                        <div class="text-muted text-truncate" style="max-width: 170px; font-size: 0.74rem;" title="<?= e($p['extra_info']) ?>">
                                            <?= e($p['extra_info']) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">No owner info</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $p['is_public'] ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> rounded-pill">
                                    <?= $p['is_public'] ? 'Public' : 'Unlisted' ?>
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <div><?= formatBytes((int)$p['file_size']) ?></div>
                                <div class="text-muted" style="font-size: 0.74rem;"><?= (int)$p['width'] ?>×<?= (int)$p['height'] ?> • <?= strtoupper(e($p['file_ext'])) ?></div>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>/admin/comments.php?photo_id=<?= (int)$p['id'] ?>" class="text-decoration-none fw-semibold">
                                    <?= (int)$p['comment_count'] ?>
                                </a>
                            </td>
                            <td class="text-muted text-nowrap">
                                <div><?= date('d M Y', strtotime($p['created_at'])) ?></div>
                                <div style="font-size: 0.73rem;"><?= e($p['ip_address'] ?: '-') ?></div>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if (($p['is_approved'] ?? 1) == 0): ?>
                                    <button type="button" class="btn btn-sm btn-success rounded-pill px-2 py-0 me-1" onclick="quickRowApprove(<?= (int)$p['id'] ?>, 'approve')">อนุมัติ</button>
                                    <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-2 py-0 me-1" onclick="quickRowApprove(<?= (int)$p['id'] ?>, 'reject')">ปฏิเสธ</button>
                                <?php elseif (($p['is_approved'] ?? 1) == 1): ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-2 py-0 me-1" onclick="quickRowApprove(<?= (int)$p['id'] ?>, 'reject')">ปฏิเสธ</button>
                                <?php elseif (($p['is_approved'] ?? 1) == 2): ?>
                                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-2 py-0 me-1" onclick="quickRowApprove(<?= (int)$p['id'] ?>, 'approve')">อนุมัติ</button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0" onclick='openEditPhotoModal(<?= $photoJson ?>)'>แก้ไข</button>
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-0 ms-1" onclick="window.confirmDeletePhoto('<?= e($p['uuid']) ?>')">ลบ</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-center gap-2 mt-4">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&ext=<?= urlencode($extFilter) ?>&folder=<?= $folderFilter ?>" class="btn-pill-secondary btn-sm">Previous</a>
                <?php endif; ?>
                <span class="d-flex align-items-center px-3 small text-muted">Page <?= $page ?> / <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&ext=<?= urlencode($extFilter) ?>&folder=<?= $folderFilter ?>" class="btn-pill-secondary btn-sm">Next</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</form>

<!-- Modal: Admin Upload Photo with Owner Info -->
<div class="modal fade" id="adminUploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-bottom px-4 py-3">
                <h5 class="modal-title fw-bold">Upload New Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="adminUploadForm" enctype="multipart/form-data">
                <div class="modal-body px-4 py-3">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Image(s) *</label>
                        <input type="file" name="photos[]" id="adminFileInput" class="form-control rounded-3" accept="image/jpeg,image/png,image/webp,image/gif" multiple required>
                        <div class="form-text small">JPG, JPEG, PNG, WEBP, GIF — สูงสุดไม่เกิน 15 MB ต่อรูป (เปลี่ยนชื่อเป็น UUID อัตโนมัติ)</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Title (Optional)</label>
                        <input type="text" name="title" class="form-control rounded-3" placeholder="Defaults to original filename">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">เจ้าของภาพ :</label>
                            <input type="text" name="owner_name" class="form-control rounded-3" placeholder="ชื่อเจ้าของภาพ / ช่างภาพ">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">หมายเลขโทรศัพท์ :</label>
                            <input type="tel" name="owner_phone" class="form-control rounded-3" placeholder="08x-xxx-xxxx">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">ข้อมูลเพิ่มเติม :</label>
                        <textarea name="extra_info" class="form-control rounded-3" rows="2" placeholder="ระบุรายละเอียดเพิ่มเติม..."></textarea>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_public" value="1" id="admUploadPublic" checked>
                        <label class="form-check-label small" for="admUploadPublic">แสดงในคลังภาพสาธารณะ (Public)</label>
                    </div>
                </div>
                <div class="modal-footer border-top px-4 py-3">
                    <button type="button" class="btn-pill-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="adminUploadSubmitBtn" class="btn-pill-primary btn-sm">Upload Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Photo & Owner Details (No Re-upload) -->
<div class="modal fade" id="adminEditPhotoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-bottom px-4 py-3">
                <h5 class="modal-title fw-bold">Edit Photo & Owner Details <span class="text-muted small fw-normal" id="editModalIdLabel"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="adminEditPhotoForm">
                <input type="hidden" id="admEditUuid">
                <div class="modal-body px-4 py-3">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Photo Title</label>
                        <input type="text" id="admEditTitle" class="form-control rounded-3" maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea id="admEditDesc" class="form-control rounded-3" rows="2"></textarea>
                    </div>
                    <hr class="my-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-bold text-uppercase text-muted">Owner Information</span>
                        <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none p-0" onclick="adminClearOwnerInfo()">Clear Owner Info</button>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Owner Name</label>
                            <input type="text" id="admEditOwnerName" class="form-control rounded-3" maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Phone Number</label>
                            <input type="tel" id="admEditOwnerPhone" class="form-control rounded-3" maxlength="50">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Additional Details</label>
                        <textarea id="admEditExtraInfo" class="form-control rounded-3" rows="2"></textarea>
                    </div>
                    <hr class="my-3">
                    <input type="hidden" id="admEditFolderId" value="0">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="admEditIsPublic">
                        <label class="form-check-label small" for="admEditIsPublic">แสดงในคลังภาพสาธารณะ (Public in Feed)</label>
                    </div>
                </div>
                <div class="modal-footer border-top px-4 py-3">
                    <button type="button" class="btn-pill-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-pill-primary btn-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const selectAll = document.getElementById('selectAllCheckbox');
const checkboxes = document.querySelectorAll('.photo-checkbox');
const bulkGroup = document.getElementById('bulkActionsGroup');
const countTexts = document.querySelectorAll('.selectedCountText');

function updateBulkUI() {
    const count = document.querySelectorAll('.photo-checkbox:checked').length;
    countTexts.forEach(el => el.innerText = count);
    if (bulkGroup) bulkGroup.classList.toggle('d-none', count === 0);
}

selectAll?.addEventListener('change', () => {
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateBulkUI();
});
checkboxes.forEach(cb => cb.addEventListener('change', updateBulkUI));

function submitBulkAction(action) {
    const count = document.querySelectorAll('.photo-checkbox:checked').length;
    if (count === 0) return;

    let title = '';
    let text = '';
    let confirmBtnText = '';
    let btnClass = 'btn-pill-primary me-2';

    if (action === 'bulk_approve') {
        title = `อนุมัติภาพ ${count} รายการ?`;
        text = 'ภาพที่เลือกจะถูกอนุมัติและนำมาแสดงผลสู่สาธารณะทันที';
        confirmBtnText = 'อนุมัติทั้งหมด';
    } else if (action === 'bulk_reject') {
        title = `ปฏิเสธภาพ ${count} รายการ?`;
        text = 'ภาพที่เลือกจะไม่ถูกนำไปแสดงในคลังภาพสาธารณะ';
        confirmBtnText = 'ปฏิเสธทั้งหมด';
        btnClass = 'btn btn-warning rounded-pill me-2';
    } else if (action === 'bulk_delete') {
        title = `Delete ${count} selected photo(s)?`;
        text = 'Files and linked comments will be permanently removed.';
        confirmBtnText = 'Yes, Delete All';
        btnClass = 'btn-pill-danger me-2';
    }

    Swal.fire({
        title: title,
        text: text,
        icon: action === 'bulk_delete' ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonText: confirmBtnText,
        cancelButtonText: 'Cancel',
        customClass: { confirmButton: btnClass, cancelButton: 'btn-pill-secondary' },
        buttonsStyling: false
    }).then(r => {
        if (r.isConfirmed) {
            document.getElementById('bulkActionInput').value = action;
            document.getElementById('bulkForm').submit();
        }
    });
}

async function quickRowApprove(photoId, action) {
    try {
        const fd = new FormData();
        fd.append('photo_id', photoId);
        fd.append('action', action);
        fd.append('csrf_token', "<?= e($csrfToken) ?>");

        const res = await fetch((window.BASE_URL || '') + '/api/photo-approve.php', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();
        if (data.success) {
            window.showToast(data.message || 'Status updated');
            setTimeout(() => location.reload(), 600);
        } else {
            Swal.fire('Error', data.error || 'Failed to update photo status', 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Network or server error', 'error');
    }
}

function openEditPhotoModal(photo) {
    document.getElementById('editModalIdLabel').innerText = `#${photo.id}`;
    document.getElementById('admEditUuid').value = photo.uuid;
    document.getElementById('admEditTitle').value = photo.title || '';
    document.getElementById('admEditDesc').value = photo.description || '';
    document.getElementById('admEditOwnerName').value = photo.owner_name || '';
    document.getElementById('admEditOwnerPhone').value = photo.owner_phone || '';
    document.getElementById('admEditExtraInfo').value = photo.extra_info || '';
    document.getElementById('admEditFolderId').value = String(photo.folder_id || 0);
    document.getElementById('admEditIsPublic').checked = !!photo.is_public;
    new bootstrap.Modal(document.getElementById('adminEditPhotoModal')).show();
}

document.getElementById('adminEditPhotoForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const res = await fetch(window.BASE_URL + '/api/photo-update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            uuid: document.getElementById('admEditUuid').value,
            title: document.getElementById('admEditTitle').value.trim(),
            description: document.getElementById('admEditDesc').value.trim(),
            owner_name: document.getElementById('admEditOwnerName').value.trim(),
            owner_phone: document.getElementById('admEditOwnerPhone').value.trim(),
            extra_info: document.getElementById('admEditExtraInfo').value.trim(),
            folder_id: document.getElementById('admEditFolderId').value,
            is_public: document.getElementById('admEditIsPublic').checked ? 1 : 0
        })
    });
    const data = await res.json();
    if (data.success) {
        window.showToast('Photo details updated');
        setTimeout(() => location.href = window.BASE_URL + '/admin/photos.php', 600);
    } else {
        Swal.fire('Error', data.error || 'Failed to save changes', 'error');
    }
});

async function adminClearOwnerInfo() {
    const uuid = document.getElementById('admEditUuid').value;
    if (!uuid) return;
    const res = await fetch(window.BASE_URL + '/api/photo-update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ uuid: uuid, action: 'clear_owner_info' })
    });
    const data = await res.json();
    if (data.success) {
        document.getElementById('admEditOwnerName').value = '';
        document.getElementById('admEditOwnerPhone').value = '';
        document.getElementById('admEditExtraInfo').value = '';
        window.showToast('Owner info cleared');
    }
}

document.getElementById('adminUploadForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('adminUploadSubmitBtn');
    btn.disabled = true;
    btn.innerText = 'Uploading...';

    const fd = new FormData(e.target);
    if (!document.getElementById('admUploadPublic').checked) {
        fd.set('is_public', '0');
    }

    try {
        const res = await fetch(window.BASE_URL + '/api/upload.php', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();
        if (data.success) {
            window.showToast(data.message || 'Uploaded successfully');
            setTimeout(() => location.reload(), 700);
        } else {
            const errMsg = (data.errors && data.errors[0] && data.errors[0].error) || data.error || 'Upload failed';
            Swal.fire('Upload Error', errMsg, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Network error during upload', 'error');
    } finally {
        btn.disabled = false;
        btn.innerText = 'Upload Now';
    }
});

<?php if ($autoEditPhoto): ?>
document.addEventListener('DOMContentLoaded', () => {
    openEditPhotoModal(<?= json_encode([
        'id'          => (int)$autoEditPhoto['id'],
        'uuid'        => $autoEditPhoto['uuid'],
        'title'       => $autoEditPhoto['title'] ?? '',
        'description' => $autoEditPhoto['description'] ?? '',
        'owner_name'  => $autoEditPhoto['owner_name'] ?? '',
        'owner_phone' => $autoEditPhoto['owner_phone'] ?? '',
        'extra_info'  => $autoEditPhoto['extra_info'] ?? '',
        'folder_id'   => (int)($autoEditPhoto['folder_id'] ?? 0),
        'is_public'   => (int)$autoEditPhoto['is_public']
    ], JSON_UNESCAPED_UNICODE) ?>);
});
<?php endif; ?>
<?php if (($_GET['action'] ?? '') === 'new'): ?>
document.addEventListener('DOMContentLoaded', () => {
    new bootstrap.Modal(document.getElementById('adminUploadModal')).show();
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
