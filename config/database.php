<?php
/**
 * PhotoKY - Database Connection Manager
 * - All sensitive database & admin credentials are read strictly from .env
 * - Automatically drops unused legacy tables (folders) & columns (folder_id)
 */

require_once __DIR__ . '/app.php';

class Database {
    private static ?PDO $instance = null;
    private static ?string $lastError = null;
    private static ?array $activeConfig = null;
    private static bool $hasAttempted = false;

    public static function getConnection(bool $throwIfFailed = false): ?PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        if (self::$hasAttempted && self::$instance === null) {
            if ($throwIfFailed) {
                throw new PDOException(self::$lastError ?: 'Database not connected.');
            }
            return null;
        }

        self::$hasAttempted = true;
        self::$instance = self::tryConnect();

        if (self::$instance !== null) {
            self::ensureSchemaUpToDate(self::$instance, self::$activeConfig['dbname'] ?? envVal('DB_NAME', 'photoky'));
            return self::$instance;
        }

        if ($throwIfFailed) {
            throw new PDOException(self::$lastError ?: 'Unable to connect to database.');
        }

        return null;
    }

    public static function isConnected(): bool {
        return self::getConnection(false) !== null;
    }

    public static function getLastError(): ?string {
        return self::$lastError;
    }

    public static function getActiveConfig(): ?array {
        self::getConnection(false);
        return self::$activeConfig;
    }

    /**
     * Fast TCP port pre-check (0.3s max) so PHP never blocks on unreachable hosts
     */
    private static function isHostReachable(string $host, int $port, float $timeoutSec = 0.3): bool {
        if ($host === 'db' && !file_exists('/.dockerenv')) {
            return false;
        }
        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = @gethostbyname($host);
            if ($ip === $host || empty($ip)) {
                return false;
            }
            $host = $ip;
        }
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeoutSec);
        if ($fp) {
            @fclose($fp);
            return true;
        }
        return false;
    }

    private static function tryConnect(): ?PDO {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true,
            PDO::ATTR_TIMEOUT            => 2,
        ];
        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
        }

        // 0. Fast-Path: Reuse verified session config
        if (!empty($_SESSION['photoky_active_db_cfg']) && is_array($_SESSION['photoky_active_db_cfg'])) {
            $cached = $_SESSION['photoky_active_db_cfg'];
            if (($cached['type'] ?? '') === 'mysql' && extension_loaded('pdo_mysql')) {
                try {
                    $dsn = "mysql:host={$cached['host']};port={$cached['port']};dbname={$cached['dbname']};charset=utf8mb4";
                    $pdo = new PDO($dsn, $cached['username'], $cached['password'], $options);
                    self::$activeConfig = $cached;
                    return $pdo;
                } catch (\Throwable $e) {
                    unset($_SESSION['photoky_active_db_cfg']);
                }
            }
        }

        $customConfigFile = CONFIG_PATH . '/db_custom.php';
        $customConfig = file_exists($customConfigFile) ? @include($customConfigFile) : null;

        $candidates = [];

        // 1. Custom saved config
        if (is_array($customConfig) && !empty($customConfig['host'])) {
            $candidates[] = [
                'type'     => 'mysql',
                'host'     => $customConfig['host'],
                'port'     => (string)($customConfig['port'] ?? envVal('DB_PORT', '3306')),
                'dbname'   => $customConfig['dbname'] ?? envVal('DB_NAME', 'photoky'),
                'username' => $customConfig['username'] ?? '',
                'password' => $customConfig['password'] ?? '',
                'label'    => 'Custom Config (' . $customConfig['host'] . ')'
            ];
        }

        // 2. Strictly read credentials from .env
        $envHost = envVal('DB_HOST', 'db');
        $envPort = envVal('DB_PORT', '3306');
        $envName = envVal('DB_NAME', 'photoky');
        $envUser = envVal('DB_USER');
        $envPass = envVal('DB_PASS');
        $envRootPass = envVal('MYSQL_ROOT_PASSWORD');

        if ($envUser !== '') {
            $candidates[] = [
                'type'     => 'mysql',
                'host'     => $envHost,
                'port'     => $envPort,
                'dbname'   => $envName,
                'username' => $envUser,
                'password' => $envPass,
                'label'    => ".env ({$envHost}:{$envPort})"
            ];

            if ($envHost !== '127.0.0.1') {
                $candidates[] = [
                    'type'     => 'mysql',
                    'host'     => '127.0.0.1',
                    'port'     => $envPort,
                    'dbname'   => $envName,
                    'username' => $envUser,
                    'password' => $envPass,
                    'label'    => ".env Local (127.0.0.1:{$envPort})"
                ];
            }
        }

        if ($envRootPass !== '') {
            $candidates[] = [
                'type'     => 'mysql',
                'host'     => '127.0.0.1',
                'port'     => $envPort,
                'dbname'   => $envName,
                'username' => 'root',
                'password' => $envRootPass,
                'label'    => ".env Root (127.0.0.1:{$envPort})"
            ];
        }

        // 3. Local XAMPP default (root with empty password)
        $candidates[] = [
            'type'     => 'mysql',
            'host'     => '127.0.0.1',
            'port'     => $envPort,
            'dbname'   => $envName,
            'username' => 'root',
            'password' => '',
            'label'    => "Local MySQL (127.0.0.1:{$envPort})"
        ];

        $errorsCollected = [];
        $unreachableHosts = [];

        if (extension_loaded('pdo_mysql')) {
            foreach ($candidates as $c) {
                $hostKey = $c['host'] . ':' . $c['port'];
                if (isset($unreachableHosts[$hostKey])) {
                    continue;
                }

                if (!self::isHostReachable($c['host'], (int)$c['port'], 0.3)) {
                    $unreachableHosts[$hostKey] = true;
                    $errorsCollected[] = "[{$c['label']}] Host {$hostKey} is not reachable.";
                    continue;
                }

                try {
                    $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['dbname']};charset=utf8mb4";
                    $pdo = new PDO($dsn, $c['username'], $c['password'], $options);
                    self::$activeConfig = $c;
                    $_SESSION['photoky_active_db_cfg'] = $c;
                    return $pdo;
                } catch (PDOException $e) {
                    $msg = $e->getMessage();
                    $errorsCollected[] = "[{$c['label']}] " . $msg;

                    if (str_contains($msg, 'Unknown database') || str_contains($msg, '1049')) {
                        $createdPdo = self::tryCreateDatabase($c, $options);
                        if ($createdPdo) {
                            self::$activeConfig = $c;
                            $_SESSION['photoky_active_db_cfg'] = $c;
                            return $createdPdo;
                        }
                    }
                }
            }
        }

        // 4. Embedded SQLite Fallback
        if (extension_loaded('pdo_sqlite')) {
            try {
                $sqliteDir = ROOT_PATH . '/sql';
                if (!is_dir($sqliteDir)) {
                    @mkdir($sqliteDir, 0775, true);
                }
                $sqliteFile = $sqliteDir . '/photoky.sqlite';
                $isNew = !file_exists($sqliteFile);
                $pdo = new PDO("sqlite:{$sqliteFile}", null, null, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                $pdo->exec("PRAGMA foreign_keys = ON;");

                if ($isNew) {
                    self::initializeSqliteSchema($pdo);
                }

                self::$activeConfig = [
                    'type'  => 'sqlite',
                    'file'  => $sqliteFile,
                    'label' => 'ฐานข้อมูลในตัวระบบ (SQLite Embedded)'
                ];
                return $pdo;
            } catch (\Throwable $sqe) {
                $errorsCollected[] = '[SQLite Fallback] ' . $sqe->getMessage();
            }
        }

        self::$lastError = implode("\n", array_slice($errorsCollected, 0, 4));
        return null;
    }

    private static function tryCreateDatabase(array $c, array $options): ?PDO {
        try {
            $rootDsn = "mysql:host={$c['host']};port={$c['port']};charset=utf8mb4";
            $tmpPdo = new PDO($rootDsn, $c['username'], $c['password'], $options);
            $dbname = preg_replace('/[^a-zA-Z0-9_]/', '', $c['dbname']);
            $tmpPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $targetDsn = "mysql:host={$c['host']};port={$c['port']};dbname={$dbname};charset=utf8mb4";
            $pdo = new PDO($targetDsn, $c['username'], $c['password'], $options);

            $initSqlFile = ROOT_PATH . '/sql/init.sql';
            if (file_exists($initSqlFile)) {
                $initSql = file_get_contents($initSqlFile);
                $pdo->exec($initSql);
            }

            return $pdo;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function initializeSqliteSchema(PDO $pdo): void {
        $pdo->exec("
            DROP TABLE IF EXISTS folders;
            CREATE TABLE IF NOT EXISTS photos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT NOT NULL UNIQUE,
                filename TEXT NOT NULL UNIQUE,
                file_path TEXT NOT NULL DEFAULT '',
                original_name TEXT NOT NULL,
                file_size INTEGER NOT NULL,
                mime_type TEXT NOT NULL,
                file_ext TEXT NOT NULL,
                width INTEGER DEFAULT 0,
                height INTEGER DEFAULT 0,
                delete_token TEXT NOT NULL UNIQUE,
                title TEXT,
                description TEXT,
                owner_name TEXT,
                owner_phone TEXT,
                extra_info TEXT,
                is_public INTEGER NOT NULL DEFAULT 1,
                is_approved INTEGER NOT NULL DEFAULT 0,
                views INTEGER NOT NULL DEFAULT 0,
                downloads INTEGER NOT NULL DEFAULT 0,
                ip_address TEXT,
                user_agent TEXT,
                exif_data TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                photo_id INTEGER NOT NULL,
                parent_id INTEGER DEFAULT NULL,
                author_name TEXT NOT NULL,
                content TEXT NOT NULL,
                ip_address TEXT,
                user_agent TEXT,
                is_approved INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                recovery_code_hash TEXT,
                role TEXT NOT NULL DEFAULT 'admin',
                last_login DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS admin_password_resets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                admin_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                used_at DATETIME DEFAULT NULL,
                ip_address TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS settings (
                setting_key TEXT PRIMARY KEY,
                setting_value TEXT,
                description TEXT
            );
            CREATE TABLE IF NOT EXISTS system_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event TEXT NOT NULL,
                details TEXT,
                ip_address TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        if (ENV_ADMIN_USER !== '' && ENV_ADMIN_PASS !== '') {
            $defaultPassHash = password_hash(ENV_ADMIN_PASS, PASSWORD_BCRYPT);
            $defaultRecHash  = password_hash(ENV_ADMIN_RECOVERY, PASSWORD_BCRYPT);
            $stmtSeed = $pdo->prepare("
                INSERT OR IGNORE INTO admins (username, email, password_hash, recovery_code_hash, role)
                VALUES (:u, :e, :p, :r, 'superadmin')
            ");
            $stmtSeed->execute([
                ':u' => ENV_ADMIN_USER,
                ':e' => ENV_ADMIN_EMAIL,
                ':p' => $defaultPassHash,
                ':r' => $defaultRecHash
            ]);
        }
    }

    /**
     * One-time schema & admin credential sync + drop unused tables (folders)
     */
    private static function ensureSchemaUpToDate(PDO $pdo, string $dbname): void {
        if (!empty($_SESSION['photoky_schema_v7_ready'])) {
            return;
        }

        // 1. Sync admin account from .env
        if (ENV_ADMIN_USER !== '' && ENV_ADMIN_PASS !== '') {
            try {
                $adminStmt = $pdo->prepare("SELECT id, username, password_hash FROM admins WHERE username = :u LIMIT 1");
                $adminStmt->execute([':u' => ENV_ADMIN_USER]);
                $adminRow = $adminStmt->fetch();

                $pHash = password_hash(ENV_ADMIN_PASS, PASSWORD_BCRYPT);
                $rHash = password_hash(ENV_ADMIN_RECOVERY, PASSWORD_BCRYPT);

                if (!$adminRow) {
                    $pdo->prepare("INSERT INTO admins (username, email, password_hash, recovery_code_hash, role) VALUES (:u, :e, :p, :r, 'superadmin')")
                        ->execute([':u' => ENV_ADMIN_USER, ':e' => ENV_ADMIN_EMAIL, ':p' => $pHash, ':r' => $rHash]);
                } elseif (!password_verify(ENV_ADMIN_PASS, $adminRow['password_hash'])) {
                    $pdo->prepare("UPDATE admins SET password_hash = :p, recovery_code_hash = :r WHERE id = :id")
                        ->execute([':p' => $pHash, ':r' => $rHash, ':id' => $adminRow['id']]);
                }
            } catch (\Throwable $healEx) {}
        }

        // 2. Drop unused `folders` table & `folder_id` column cleanly
        if ((self::$activeConfig['type'] ?? '') === 'sqlite') {
            try {
                $pdo->exec("DROP TABLE IF EXISTS folders");
            } catch (\Throwable $e) {}
            $_SESSION['photoky_schema_v7_ready'] = true;
            return;
        }

        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            try {
                $pdo->exec("ALTER TABLE `photos` DROP FOREIGN KEY `fk_photos_folder`");
            } catch (\Throwable $fkEx) {}
            try {
                $pdo->exec("ALTER TABLE `photos` DROP COLUMN `folder_id`");
            } catch (\Throwable $colEx) {}
            $pdo->exec("DROP TABLE IF EXISTS `folders`");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        } catch (\Throwable $dropEx) {}

        $_SESSION['photoky_schema_v7_ready'] = true;
    }

    /**
     * Simplified connection test: requires only Username & Password (auto-detects host/port/dbname from .env)
     */
    public static function testAndSaveConfig(array $cfg): array {
        $user = trim($cfg['username'] ?? envVal('DB_USER'));
        $pass = (string)($cfg['password'] ?? '');
        $port = trim($cfg['port'] ?? envVal('DB_PORT', '3306'));
        $dbname = trim($cfg['dbname'] ?? envVal('DB_NAME', 'photoky'));

        if (!extension_loaded('pdo_mysql')) {
            return ['success' => false, 'error' => 'ส่วนเสริม PHP pdo_mysql ยังไม่ได้เปิดใช้งาน'];
        }

        $requestedHost = trim($cfg['host'] ?? '');
        $hostsToTry = [];
        if ($requestedHost !== '' && strtolower($requestedHost) !== 'localhost') {
            $hostsToTry[] = $requestedHost;
        }
        if (file_exists('/.dockerenv')) {
            $hostsToTry[] = envVal('DB_HOST', 'db');
        }
        $hostsToTry[] = '127.0.0.1';
        $hostsToTry = array_unique($hostsToTry);

        $reachableHost = null;
        foreach ($hostsToTry as $h) {
            if (self::isHostReachable($h, (int)$port, 1.0)) {
                $reachableHost = $h;
                break;
            }
        }

        if (!$reachableHost) {
            return [
                'success' => false,
                'error'   => 'ไม่พบเซิร์ฟเวอร์ฐานข้อมูล MySQL ที่เปิดทำงานอยู่ กรุณาตรวจสอบว่าเปิด MySQL ใน Docker หรือ XAMPP แล้ว'
            ];
        }

        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true,
            PDO::ATTR_TIMEOUT            => 3
        ];

        try {
            $dsn = "mysql:host={$reachableHost};port={$port};dbname={$dbname};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, $opts);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Unknown database') || str_contains($e->getMessage(), '1049')) {
                try {
                    $rootDsn = "mysql:host={$reachableHost};port={$port};charset=utf8mb4";
                    $rootPdo = new PDO($rootDsn, $user, $pass, $opts);
                    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo = new PDO($dsn, $user, $pass, $opts);
                    $initFile = ROOT_PATH . '/sql/init.sql';
                    if (file_exists($initFile)) {
                        $pdo->exec(file_get_contents($initFile));
                    }
                } catch (\Throwable $subEx) {
                    return ['success' => false, 'error' => 'เชื่อมต่อ MySQL ได้ แต่ไม่สามารถสร้างฐานข้อมูลได้: ' . $subEx->getMessage()];
                }
            } else {
                return ['success' => false, 'error' => 'ชื่อผู้ใช้หรือรหัสผ่านฐานข้อมูลไม่ถูกต้อง'];
            }
        }

        $savedCfg = [
            'type'     => 'mysql',
            'host'     => $reachableHost,
            'port'     => $port,
            'dbname'   => $dbname,
            'username' => $user,
            'password' => $pass,
            'label'    => "MySQL ({$reachableHost}:{$port} / {$dbname})",
            'saved_at' => date('Y-m-d H:i:s')
        ];

        $exportData = "<?php\n// PhotoKY Custom Database Configuration\nreturn " . var_export($savedCfg, true) . ";\n";
        @file_put_contents(CONFIG_PATH . '/db_custom.php', $exportData);

        self::$instance = $pdo;
        self::$activeConfig = $savedCfg;
        $_SESSION['photoky_active_db_cfg'] = $savedCfg;
        unset($_SESSION['photoky_schema_v7_ready']);
        self::ensureSchemaUpToDate($pdo, $dbname);

        return ['success' => true, 'message' => 'เชื่อมต่อฐานข้อมูลสำเร็จและพร้อมใช้งาน'];
    }
}

function getDB(): ?PDO {
    return Database::getConnection(false);
}

function getDBOrNull(): ?PDO {
    return Database::getConnection(false);
}

function isDbConnected(): bool {
    return Database::isConnected();
}
