<?php
/**
 * PhotoKY - Admin Comments Moderation
 * Review, search, and delete inappropriate public comments and replies
 */

$adminPageTitle = 'Moderate Comments';
require_once __DIR__ . '/header.php';

$db = getDB();
$csrfToken = getCsrfToken();

$flashSuccess = '';
$flashError = '';

// Bulk delete comments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_delete') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $flashError = 'Invalid security token.';
    } else {
        $ids = $_POST['comment_ids'] ?? [];
        if (is_array($ids) && count($ids) > 0) {
            $delCount = 0;
            $delStmt = $db->prepare("DELETE FROM comments WHERE id = :id");
            foreach ($ids as $rawId) {
                $cid = (int)$rawId;
                if ($cid > 0) {
                    $delStmt->execute([':id' => $cid]);
                    $delCount += $delStmt->rowCount();
                }
            }
            if ($delCount > 0) {
                logSystemEvent('admin_bulk_delete_comments', "Deleted {$delCount} comment(s)");
                $flashSuccess = "Deleted {$delCount} comment(s).";
            }
        }
    }
}

$search = trim($_GET['q'] ?? '');
$photoIdFilter = (int)($_GET['photo_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = "(c.author_name LIKE :q OR c.content LIKE :q OR c.ip_address LIKE :q OR p.title LIKE :q)";
    $params[':q'] = "%{$search}%";
}
if ($photoIdFilter > 0) {
    $where[] = "c.photo_id = :pid";
    $params[':pid'] = $photoIdFilter;
}

$whereSql = implode(' AND ', $where);

$totalComments = 0;
$totalPages = 1;
$comments = [];

if ($db) {
    try {
        $countStmt = $db->prepare("SELECT COUNT(*) FROM comments c INNER JOIN photos p ON c.photo_id = p.id WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalComments = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalComments / $perPage));

        $sql = "
            SELECT c.*, p.uuid as photo_uuid, p.title as photo_title, p.filename as photo_filename, p.original_name as photo_orig
            FROM comments c
            INNER JOIN photos p ON c.photo_id = p.id
            WHERE {$whereSql}
            ORDER BY c.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $comments = $stmt->fetchAll();
    } catch (\Throwable $e) {}
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">จัดการความคิดเห็น</h1>
        <p class="text-muted small mb-0">
            ทั้งหมด <?= number_format($totalComments) ?> ความคิดเห็น <?= $photoIdFilter > 0 ? "สำหรับรูปภาพ #{$photoIdFilter}" : 'จากรูปภาพทั้งหมด' ?>
        </p>
    </div>
    <?php if ($photoIdFilter > 0): ?>
        <a href="<?= BASE_URL ?>/admin/comments.php" class="btn-pill-secondary btn-sm">แสดงความคิดเห็นของทุกรูปภาพ</a>
    <?php endif; ?>
</div>

<?php if ($flashSuccess): ?>
    <div class="alert alert-success py-2 px-3 small rounded-3 mb-3"><?= e($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
    <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3"><?= e($flashError) ?></div>
<?php endif; ?>

<!-- Search Bar -->
<div class="admin-card mb-4">
    <form method="GET" action="<?= BASE_URL ?>/admin/comments.php" class="row g-2 align-items-center">
        <?php if ($photoIdFilter > 0): ?>
            <input type="hidden" name="photo_id" value="<?= $photoIdFilter ?>">
        <?php endif; ?>
        <div class="col-md-6">
            <input type="text" name="q" class="form-control form-control-sm rounded-pill px-3" placeholder="ค้นหาจากชื่อผู้แสดงความคิดเห็น, ข้อความ, IP, หรือชื่อรูปภาพ..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-auto">
            <button type="submit" class="btn-pill-primary btn-sm py-1 px-3">ค้นหา</button>
            <?php if ($search !== ''): ?>
                <a href="<?= BASE_URL ?>/admin/comments.php<?= $photoIdFilter > 0 ? '?photo_id=' . $photoIdFilter : '' ?>" class="btn-pill-secondary btn-sm py-1 px-3">ล้างค่า</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<form method="POST" action="<?= BASE_URL ?>/admin/comments.php" id="commentsBulkForm">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="action" value="bulk_delete">

    <div class="admin-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
                <input type="checkbox" id="selectAllComments" class="form-check-input">
                <label for="selectAllComments" class="small text-muted mb-0">เลือกทั้งหมด</label>
                <button type="button" id="bulkDelCommentsBtn" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-0 ms-2 d-none" onclick="confirmBulkCommentsDelete()">
                    ลบที่เลือก (<span id="selectedCommentsCount">0</span>)
                </button>
            </div>
            <span class="small text-muted">หน้า <?= $page ?> จาก <?= $totalPages ?></span>
        </div>

        <?php if (empty($comments)): ?>
            <div class="text-center text-muted py-5">ไม่พบความคิดเห็น</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 32px;"></th>
                            <th>ผู้แสดงความคิดเห็น</th>
                            <th>ข้อความ</th>
                            <th>รูปภาพ</th>
                            <th>วันที่ / IP</th>
                            <th class="text-end">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comments as $c): ?>
                        <tr id="comment_row_<?= (int)$c['id'] ?>">
                            <td>
                                <input type="checkbox" name="comment_ids[]" value="<?= (int)$c['id'] ?>" class="form-check-input comment-cb">
                            </td>
                            <td class="text-nowrap">
                                <div class="fw-semibold"><?= e($c['author_name']) ?></div>
                                <?php if (!empty($c['parent_id'])): ?>
                                    <span class="badge bg-secondary-subtle text-light border border-secondary border-opacity-25 rounded-pill" style="font-size: 0.7rem;">Reply to #<?= (int)$c['parent_id'] ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-light border border-secondary border-opacity-25 rounded-pill" style="font-size: 0.7rem;">Comment #<?= (int)$c['id'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="max-width: 380px;">
                                <div style="white-space: pre-wrap; word-break: break-word;"><?= e($c['content']) ?></div>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>/photo.php?uuid=<?= e($c['photo_uuid']) ?>#commentsSection" target="_blank" class="d-flex align-items-center gap-2 text-decoration-none text-light">
                                    <img src="<?= BASE_URL ?>/uploads/thumbnails/<?= e($c['photo_filename']) ?>" class="rounded border" style="width: 38px; height: 38px; object-fit: cover;" alt="">
                                    <span class="text-truncate" style="max-width: 150px;">
                                        <?= e($c['photo_title'] ?: $c['photo_orig']) ?>
                                    </span>
                                </a>
                            </td>
                            <td class="text-muted text-nowrap">
                                <div><?= date('d M Y, H:i', strtotime($c['created_at'])) ?></div>
                                <div style="font-size: 0.73rem;"><?= e($c['ip_address'] ?: '-') ?></div>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-0" onclick="deleteSingleComment(<?= (int)$c['id'] ?>)">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-center gap-2 mt-4">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&photo_id=<?= $photoIdFilter ?>" class="btn-pill-secondary btn-sm">Previous</a>
                <?php endif; ?>
                <span class="d-flex align-items-center px-3 small text-muted">Page <?= $page ?> / <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&photo_id=<?= $photoIdFilter ?>" class="btn-pill-secondary btn-sm">Next</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</form>

<script>
const COMMENT_CSRF = "<?= e($csrfToken) ?>";
const selAll = document.getElementById('selectAllComments');
const cbs = document.querySelectorAll('.comment-cb');
const bulkDelBtn = document.getElementById('bulkDelCommentsBtn');
const selCount = document.getElementById('selectedCommentsCount');

function updateCommentBulk() {
    const n = document.querySelectorAll('.comment-cb:checked').length;
    if (selCount) selCount.innerText = n;
    if (bulkDelBtn) bulkDelBtn.classList.toggle('d-none', n === 0);
}

selAll?.addEventListener('change', () => {
    cbs.forEach(c => c.checked = selAll.checked);
    updateCommentBulk();
});
cbs.forEach(c => c.addEventListener('change', updateCommentBulk));

function confirmBulkCommentsDelete() {
    const n = document.querySelectorAll('.comment-cb:checked').length;
    if (n === 0) return;
    Swal.fire({
        title: `Delete ${n} comment(s)?`,
        text: 'Any replies nested under deleted comments will also be removed.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete Selected',
        customClass: { confirmButton: 'btn-pill-danger me-2', cancelButton: 'btn-pill-secondary' },
        buttonsStyling: false
    }).then(r => {
        if (r.isConfirmed) document.getElementById('commentsBulkForm').submit();
    });
}

async function deleteSingleComment(id) {
    const r = await Swal.fire({
        title: 'Delete Comment?',
        text: 'This permanently deletes the comment and any replies under it.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        customClass: { confirmButton: 'btn-pill-danger me-2', cancelButton: 'btn-pill-secondary' },
        buttonsStyling: false
    });
    if (!r.isConfirmed) return;

    const res = await fetch(window.BASE_URL + '/api/comments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', comment_id: id, csrf_token: COMMENT_CSRF })
    });
    const data = await res.json();
    if (data.success) {
        document.getElementById('comment_row_' + id)?.remove();
        window.showToast('Comment deleted');
    } else {
        Swal.fire('Error', data.error || 'Delete failed', 'error');
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
