<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viewer360 - Setup Test</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .test-item {
            padding: 10px;
            margin: 10px 0;
            border-left: 4px solid #ccc;
            padding-left: 15px;
        }
        .test-pass {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .test-fail {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        .test-warning {
            border-left-color: #ffc107;
            background: #fff3cd;
        }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 30px; }
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-warning { background: #ffc107; color: #333; }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="test-card">
        <h1>🔧 Viewer360 Setup Test</h1>
        <p>This page tests if your installation is configured correctly.</p>
    </div>

    <?php
    require_once __DIR__ . '/autoload.php';
    use App\Config;
    use App\Database;

    $allPassed = true;
    ?>

    <!-- PHP Version Test -->
    <div class="test-card">
        <h2>PHP Configuration</h2>
        
        <div class="test-item <?= version_compare(PHP_VERSION, '8.2.0', '>=') ? 'test-pass' : 'test-fail' ?>">
            <strong>PHP Version:</strong> <?= PHP_VERSION ?>
            <?php if (version_compare(PHP_VERSION, '8.2.0', '>=')): ?>
                <span class="badge badge-success">PASS</span>
            <?php else: ?>
                <span class="badge badge-danger">FAIL</span>
                <br><small>Required: PHP 8.2 or higher</small>
                <?php $allPassed = false; ?>
            <?php endif; ?>
        </div>

        <div class="test-item <?= extension_loaded('pdo') ? 'test-pass' : 'test-fail' ?>">
            <strong>PDO Extension:</strong> <?= extension_loaded('pdo') ? 'Loaded' : 'Not Loaded' ?>
            <?= extension_loaded('pdo') ? '<span class="badge badge-success">PASS</span>' : '<span class="badge badge-danger">FAIL</span>' ?>
            <?php if (!extension_loaded('pdo')) $allPassed = false; ?>
        </div>

        <div class="test-item <?= extension_loaded('pdo_mysql') ? 'test-pass' : 'test-fail' ?>">
            <strong>PDO MySQL Extension:</strong> <?= extension_loaded('pdo_mysql') ? 'Loaded' : 'Not Loaded' ?>
            <?= extension_loaded('pdo_mysql') ? '<span class="badge badge-success">PASS</span>' : '<span class="badge badge-danger">FAIL</span>' ?>
            <?php if (!extension_loaded('pdo_mysql')) $allPassed = false; ?>
        </div>

        <div class="test-item <?= extension_loaded('gd') ? 'test-pass' : 'test-fail' ?>">
            <strong>GD Extension:</strong> <?= extension_loaded('gd') ? 'Loaded' : 'Not Loaded' ?>
            <?= extension_loaded('gd') ? '<span class="badge badge-success">PASS</span>' : '<span class="badge badge-danger">FAIL</span>' ?>
            <?php if (!extension_loaded('gd')) $allPassed = false; ?>
        </div>
    </div>

    <!-- URL Configuration Test -->
    <div class="test-card">
        <h2>URL Configuration</h2>
        
        <div class="test-item test-pass">
            <strong>Base URL:</strong> <code><?= Config::get('app.base_url') ?></code>
            <span class="badge badge-success">AUTO-DETECTED</span>
        </div>

        <div class="test-item test-pass">
            <strong>Sample URLs:</strong><br>
            Home: <code><?= Config::url() ?></code><br>
            Dashboard: <code><?= Config::url('dashboard.php') ?></code><br>
            Explore: <code><?= Config::url('explore.php') ?></code>
        </div>
    </div>

    <!-- Database Connection Test -->
    <div class="test-card">
        <h2>Database Connection</h2>
        
        <?php
        $dbConfig = [
            'host' => Config::get('db.host'),
            'name' => Config::get('db.name'),
            'user' => Config::get('db.user')
        ];
        
        try {
            $pdo = Database::getConnection();
            $dbConnected = true;
        } catch (Exception $e) {
            $dbConnected = false;
            $dbError = $e->getMessage();
        }
        ?>

        <div class="test-item test-pass">
            <strong>Database Host:</strong> <code><?= htmlspecialchars($dbConfig['host']) ?></code>
        </div>

        <div class="test-item test-pass">
            <strong>Database Name:</strong> <code><?= htmlspecialchars($dbConfig['name']) ?></code>
        </div>

        <div class="test-item <?= $dbConnected ? 'test-pass' : 'test-fail' ?>">
            <strong>Connection Status:</strong> <?= $dbConnected ? 'Connected' : 'Failed' ?>
            <?php if ($dbConnected): ?>
                <span class="badge badge-success">PASS</span>
            <?php else: ?>
                <span class="badge badge-danger">FAIL</span>
                <br><small><?= htmlspecialchars($dbError) ?></small>
                <?php $allPassed = false; ?>
            <?php endif; ?>
        </div>

        <?php if ($dbConnected): ?>
            <?php
            // Check if tables exist
            $tables = ['users', 'panoramas', 'markers', 'votes'];
            $existingTables = [];
            foreach ($tables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    $existingTables[] = $table;
                }
            }
            $allTablesExist = count($existingTables) === count($tables);
            ?>
            
            <div class="test-item <?= $allTablesExist ? 'test-pass' : 'test-warning' ?>">
                <strong>Database Tables:</strong> <?= count($existingTables) ?> / <?= count($tables) ?> found
                <?php if ($allTablesExist): ?>
                    <span class="badge badge-success">PASS</span>
                <?php else: ?>
                    <span class="badge badge-warning">WARNING</span>
                    <br><small>Missing tables: <?= implode(', ', array_diff($tables, $existingTables)) ?></small>
                    <br><small>Run the SQL from <code>docker/init.sql</code> to create them.</small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- File Permissions Test -->
    <div class="test-card">
        <h2>File Permissions</h2>
        
        <?php
        $uploadDir = __DIR__ . '/uploads/';
        $audioDir = __DIR__ . '/uploads/audio/';
        $uploadsWritable = is_writable($uploadDir);
        $audioWritable = is_writable($audioDir);
        ?>

        <div class="test-item <?= $uploadsWritable ? 'test-pass' : 'test-fail' ?>">
            <strong>Uploads Directory:</strong> <?= $uploadsWritable ? 'Writable' : 'Not Writable' ?>
            <?php if ($uploadsWritable): ?>
                <span class="badge badge-success">PASS</span>
            <?php else: ?>
                <span class="badge badge-danger">FAIL</span>
                <br><small>Run: <code>chmod -R 777 <?= $uploadDir ?></code></small>
                <?php $allPassed = false; ?>
            <?php endif; ?>
        </div>

        <div class="test-item <?= $audioWritable ? 'test-pass' : 'test-fail' ?>">
            <strong>Audio Directory:</strong> <?= $audioWritable ? 'Writable' : 'Not Writable' ?>
            <?php if ($audioWritable): ?>
                <span class="badge badge-success">PASS</span>
            <?php else: ?>
                <span class="badge badge-danger">FAIL</span>
                <br><small>Run: <code>chmod -R 777 <?= $audioDir ?></code></small>
                <?php $allPassed = false; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Final Result -->
    <div class="test-card" style="background: <?= $allPassed ? '#d4edda' : '#f8d7da' ?>; border: 2px solid <?= $allPassed ? '#28a745' : '#dc3545' ?>">
        <h2 style="margin-top: 0;">Overall Status</h2>
        <?php if ($allPassed): ?>
            <p style="font-size: 18px; margin: 0;">
                ✅ <strong>All tests passed!</strong> Your installation is ready to use.
            </p>
            <p style="margin-top: 20px;">
                <a href="<?= Config::url('register.php') ?>" style="display: inline-block; background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                    Create an Account
                </a>
                <a href="<?= Config::url('explore.php') ?>" style="display: inline-block; background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; margin-left: 10px;">
                    Explore Gallery
                </a>
            </p>
        <?php else: ?>
            <p style="font-size: 18px; margin: 0;">
                ❌ <strong>Some tests failed.</strong> Please fix the issues above before proceeding.
            </p>
            <p style="margin-top: 20px;">
                Refer to <a href="../XAMPP_SETUP.md">XAMPP_SETUP.md</a> for detailed setup instructions.
            </p>
        <?php endif; ?>
    </div>

    <div class="test-card" style="background: #e7f3ff; text-align: center;">
        <p style="margin: 0; color: #666;">
            📖 Need help? Check <a href="../XAMPP_SETUP.md">XAMPP_SETUP.md</a> | 
            🔧 <a href="<?= Config::url() ?>">Go to Homepage</a>
        </p>
    </div>
</body>
</html>
