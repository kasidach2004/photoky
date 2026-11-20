<?php
/**
 * PhotoKY - Public Comments & Replies API
 * Supports guest commenting with display name, nested replies, Admin moderation,
 * and strict Anti-Spam, CSRF, XSS, and SQL Injection protection.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Helper to resolve photo row from id or uuid
 */
function resolvePhoto(PDO $db, $photoId, $uuid): ?array {
    if (!empty($photoId) && (int)$photoId > 0) {
        $stmt = $db->prepare("SELECT id, uuid FROM photos WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => (int)$photoId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
    if (!empty($uuid)) {
        $stmt = $db->prepare("SELECT id, uuid FROM photos WHERE uuid = :u LIMIT 1");
        $stmt->execute([':u' => trim((string)$uuid)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
    return null;
}

// =========================================================================
// GET: Fetch comments & replies for a photo
// =========================================================================
if ($method === 'GET') {
    $photo = resolvePhoto($db, $_GET['photo_id'] ?? null, $_GET['uuid'] ?? null);
    if (!$photo) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Photo not found.']);
        exit;
    }

    $stmt = $db->prepare("
        SELECT id, photo_id, parent_id, author_name, content, created_at
        FROM comments
        WHERE photo_id = :pid AND is_approved = 1
        ORDER BY created_at ASC, id ASC
    ");
    $stmt->execute([':pid' => $photo['id']]);
    $rows = $stmt->fetchAll();

    // Build nested tree (top-level comments newest first, replies chronological)
    $byId = [];
    foreach ($rows as $r) {
        $byId[$r['id']] = [
            'id'           => (int)$r['id'],
            'photo_id'     => (int)$r['photo_id'],
            'parent_id'    => $r['parent_id'] ? (int)$r['parent_id'] : null,
            'author_name'  => $r['author_name'],
            'content'      => $r['content'],
            'created_at'   => $r['created_at'],
            'formatted_dt' => date('d M Y, H:i', strtotime($r['created_at'])),
            'time_ago'     => timeAgo($r['created_at']),
            'replies'      => []
        ];
    }

    $tree = [];
    foreach ($byId as $id => &$node) {
        if ($node['parent_id'] !== null && isset($byId[$node['parent_id']])) {
            $byId[$node['parent_id']]['replies'][] = &$node;
        } else {
            $tree[] = &$node;
        }
    }
    unset($node);

    // Show newest top-level comments first
    $tree = array_reverse($tree);

    echo json_encode([
        'success'  => true,
        'photo_id' => (int)$photo['id'],
        'total'    => count($rows),
        'is_admin' => isAdminLoggedIn(),
        'comments' => $tree
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// =========================================================================
// POST: Create comment/reply OR Delete comment (Admin)
// =========================================================================
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = trim((string)($input['action'] ?? 'create'));

    // --- ADMIN DELETE ACTION ---
    if ($action === 'delete') {
        if (!isAdminLoggedIn()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin authentication required.']);
            exit;
        }

        $csrf = $input['csrf_token'] ?? '';
        if (!verifyCsrfToken($csrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid security token. Please reload the page.']);
            exit;
        }

        $commentId = (int)($input['comment_id'] ?? 0);
        if ($commentId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid comment ID.']);
            exit;
        }

        $delStmt = $db->prepare("DELETE FROM comments WHERE id = :id");
        $delStmt->execute([':id' => $commentId]);

        logSystemEvent('comment_deleted', "Comment ID: {$commentId} deleted by Admin");

        echo json_encode([
            'success' => true,
            'message' => 'Comment deleted successfully.'
        ]);
        exit;
    }

    // --- CREATE PUBLIC COMMENT / REPLY ---
    // 1. CSRF validation
    $csrf = $input['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Security check failed. Please refresh the page and try again.']);
        exit;
    }

    // 2. Honeypot Anti-Spam Check
    $honeypot = trim((string)($input['website'] ?? ''));
    if ($honeypot !== '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Spam detected. Request blocked.']);
        exit;
    }

    // 3. Resolve photo by Image ID or UUID
    $photo = resolvePhoto($db, $input['photo_id'] ?? null, $input['uuid'] ?? null);
    if (!$photo) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Photo not found.']);
        exit;
    }

    // 4. Validate author_name & content
    $authorName = preg_replace('/[\x00-\x1F\x7F]/u', '', trim((string)($input['author_name'] ?? '')));
    $content = trim((string)($input['content'] ?? ''));
    $parentId = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;

    if (mb_strlen($authorName) < 1 || mb_strlen($authorName) > 80) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Please enter a display name (1–80 characters).']);
        exit;
    }

    if (mb_strlen($content) < 1 || mb_strlen($content) > 2000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Please enter a comment between 1 and 2,000 characters.']);
        exit;
    }

    // Anti-spam: block excessive links (> 3 links)
    preg_match_all('/https?:\/\/|www\./i', $content, $linkMatches);
    if (count($linkMatches[0]) > 3) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Too many links detected. Please remove extra URLs.']);
        exit;
    }

    // 5. Verify parent_id belongs to this photo if replying
    if ($parentId !== null) {
        $pStmt = $db->prepare("SELECT id, parent_id FROM comments WHERE id = :cid AND photo_id = :pid LIMIT 1");
        $pStmt->execute([':cid' => $parentId, ':pid' => $photo['id']]);
        $parentRow = $pStmt->fetch();
        if (!$parentRow) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Parent comment not found.']);
            exit;
        }
        // Keep nesting clean at 2 levels max (if replying to a reply, attach under its top-level parent)
        if (!empty($parentRow['parent_id'])) {
            $parentId = (int)$parentRow['parent_id'];
        }
    }

    // 6. IP Rate Limiting (bypass for logged-in admin)
    $clientIp = getClientIp();
    if (!isAdminLoggedIn()) {
        // Cooldown: 8 seconds between posts from same IP
        $recentStmt = $db->prepare("
            SELECT COUNT(*) FROM comments 
            WHERE ip_address = :ip AND created_at >= DATE_SUB(NOW(), INTERVAL 8 SECOND)
        ");
        $recentStmt->execute([':ip' => $clientIp]);
        if ((int)$recentStmt->fetchColumn() > 0) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'You are posting too fast. Please wait a few seconds.']);
            exit;
        }

        // Burst limit: max 15 comments per 10 minutes
        $burstStmt = $db->prepare("
            SELECT COUNT(*) FROM comments 
            WHERE ip_address = :ip AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ");
        $burstStmt->execute([':ip' => $clientIp]);
        if ((int)$burstStmt->fetchColumn() >= 15) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Comment rate limit reached. Please try again later.']);
            exit;
        }

        // Duplicate comment check within 5 minutes
        $dupStmt = $db->prepare("
            SELECT COUNT(*) FROM comments
            WHERE photo_id = :pid AND ip_address = :ip AND content = :content
              AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        $dupStmt->execute([
            ':pid'     => $photo['id'],
            ':ip'      => $clientIp,
            ':content' => $content
        ]);
        if ((int)$dupStmt->fetchColumn() > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Duplicate comment detected.']);
            exit;
        }
    }

    // 7. Insert Comment
    try {
        $ins = $db->prepare("
            INSERT INTO comments (photo_id, parent_id, author_name, content, ip_address, user_agent, is_approved)
            VALUES (:pid, :parent_id, :author, :content, :ip, :ua, 1)
        ");
        $ins->execute([
            ':pid'       => $photo['id'],
            ':parent_id' => $parentId,
            ':author'    => $authorName,
            ':content'   => $content,
            ':ip'        => $clientIp,
            ':ua'        => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        ]);

        $newId = (int)$db->lastInsertId();
        $now = date('Y-m-d H:i:s');

        logSystemEvent('comment_posted', "Photo ID: {$photo['id']}, Comment ID: {$newId}, Author: {$authorName}");

        echo json_encode([
            'success' => true,
            'message' => $parentId ? 'Reply posted.' : 'Comment posted.',
            'comment' => [
                'id'           => $newId,
                'photo_id'     => (int)$photo['id'],
                'parent_id'    => $parentId,
                'author_name'  => $authorName,
                'content'      => $content,
                'created_at'   => $now,
                'formatted_dt' => date('d M Y, H:i', strtotime($now)),
                'time_ago'     => 'Just now',
                'replies'      => []
            ]
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Unable to save comment.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
