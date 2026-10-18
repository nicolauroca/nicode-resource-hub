<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Disposable Windows lab only. No services, remote hosts or existing databases.
if (PHP_SAPI !== 'cli' || $argc !== 3) {
    fwrite(STDERR, "Usage: php install-local.php LAB_DIRECTORY MARIADB_BIN_DIRECTORY\n");
    exit(2);
}

function command(array $arguments, string $input = ''): void
{
    $process = proc_open($arguments, [0 => ['pipe', 'r'], 1 => ['file', 'NUL', 'w'],
        2 => ['file', 'NUL', 'w']], $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start local command.');
    }
    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    if (proc_close($process) !== 0) {
        // Do not forward installer errors: they can contain credentials.
        throw new RuntimeException('Local command failed: ' . basename($arguments[0]));
    }
}

$process = null;
$database = null;
$stage = 'preflight';
try {
    $lab = realpath($argv[1]);
    $bin = realpath($argv[2]);
    if (!$lab || !$bin || !is_file($lab . '/joomla/installation/joomla.php')
        || !is_file($bin . '/mariadbd.exe') || !is_file($bin . '/mariadb-install-db.exe')) {
        throw new RuntimeException('Invalid prerequisite directories.');
    }
    $site = $lab . '/joomla';
    $data = $lab . '/database-data';
    $ini = $lab . '/database.ini';
    if (file_exists($site . '/configuration.php') || file_exists($data) || file_exists($ini)) {
        throw new RuntimeException('Existing installation or database: nothing changed. Use a fresh lab.');
    }
    foreach (['mysqli', 'pdo_mysql', 'mbstring', 'gd', 'zlib', 'simplexml'] as $extension) {
        if (!extension_loaded($extension)) {
            throw new RuntimeException('Missing extension: ' . $extension);
        }
    }
    $socket = @stream_socket_server('tcp://127.0.0.1:33079', $error, $message);
    if (!$socket) {
        throw new RuntimeException('Port 33079 occupied: nothing changed.');
    }
    fclose($socket);
    $password = rtrim(stream_get_contents(STDIN), "\r\n");
    if (strlen($password) < 16 || strlen($password) > 128 || preg_match('/[\x00-\x1f]/', $password)) {
        throw new RuntimeException('Provide a 16 to 128 byte administrator password on stdin.');
    }
    $rootPassword = bin2hex(random_bytes(32));
    $appPassword = bin2hex(random_bytes(32));
    $stage = 'initialize database';
    command([$bin . '/mariadb-install-db.exe', '--datadir=' . $data, '--silent']);
    $config = "[mysqld]\nbasedir=" . str_replace('\\', '/', dirname($bin))
        . "\ndatadir=" . str_replace('\\', '/', $data)
        . "\nport=33079\nbind-address=127.0.0.1\nskip-name-resolve\n"
        . "character-set-server=utf8mb4\ncollation-server=utf8mb4_unicode_ci\n"
        . "innodb-buffer-pool-size=64M\n";
    file_put_contents($ini, $config);
    // Bootstrap has no network listener. Remove default accounts and secure root
    // BEFORE starting TCP. Password hashes, not reusable plaintext, go to stdin.
    $stage = 'secure offline database';
    $hash = '*' . strtoupper(sha1(sha1($rootPassword, true)));
    $sql = "USE mysql;\nDELETE FROM global_priv WHERE User <> 'root' OR Host <> 'localhost';\n"
        . "UPDATE global_priv SET Host='127.0.0.1', Priv=JSON_SET(JSON_REMOVE(Priv,'$.auth_or'),"
        . "'$.plugin','mysql_native_password','$.authentication_string','$hash') WHERE User='root';\n";
    command([$bin . '/mariadbd.exe', '--defaults-file=' . $ini, '--bootstrap'], $sql);
    $stage = 'start private database';
    $process = proc_open([$bin . '/mariadbd.exe', '--defaults-file=' . $ini],
        [0 => ['file', 'NUL', 'r'], 1 => ['file', 'NUL', 'w'], 2 => ['file', 'NUL', 'w']],
        $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('Database process did not start.');
    }
    for ($attempt = 0; $attempt < 80; $attempt++) {
        try {
            $database = new PDO('mysql:host=127.0.0.1;port=33079;charset=utf8mb4', 'root', $rootPassword,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            break;
        } catch (PDOException $error) {
            if (!proc_get_status($process)['running']) {
                throw new RuntimeException('Database exited before readiness.');
            }
            usleep(250000);
        }
    }
    if (!$database) {
        throw new RuntimeException('Database readiness timeout.');
    }
    $database->exec('CREATE DATABASE nrh_course CHARACTER SET utf8mb4');
    $database->exec("CREATE USER 'nrh_learner'@'127.0.0.1' IDENTIFIED BY " . $database->quote($appPassword));
    $database->exec("GRANT ALL ON nrh_course.* TO 'nrh_learner'@'127.0.0.1'");
    $stage = 'native Joomla installation';
    $arguments = ['joomla.php', 'install', '--no-interaction', '--site-name=Nicode Learning Lab',
        '--admin-user=Course Learner', '--admin-username=learner', '--admin-password=' . $password,
        '--admin-email=learner@example.invalid', '--db-type=mysqli', '--db-host=127.0.0.1:33079',
        '--db-user=nrh_learner', '--db-pass=' . $appPassword, '--db-name=nrh_course',
        '--db-prefix=nrh_', '--db-encryption=0'];
    command([PHP_BINARY, '-c', php_ini_loaded_file(), __DIR__ . '/install-site.php', $site], json_encode($arguments));
    if (!is_file($site . '/configuration.php')) {
        throw new RuntimeException('Native installation did not create configuration.php.');
    }
    command([PHP_BINARY, '-c', php_ini_loaded_file(), $site . '/cli/joomla.php', 'config:set',
        'mailonline=false', 'live_site=http://127.0.0.1:8769']);
    $database->exec("UPDATE nrh_course.nrh_extensions SET enabled=0 WHERE type='plugin' AND folder='behaviour' AND element='compat'");
    $users = (int) $database->query('SELECT COUNT(*) FROM nrh_course.nrh_users')->fetchColumn();
    if ($users !== 1) {
        throw new RuntimeException('Unexpected administrator count.');
    }
    echo "PASS native installation; one administrator; mail disabled; compatibility plugin disabled.\n";
    echo "READY: installed site, database stopped on exit. Administrator username: learner.\n";
} catch (Throwable $error) {
    // Only preflight messages are safe to print; database/installer errors may
    // contain SQL or credentials. Keep incomplete folders for inspection.
    fwrite(STDERR, 'FAIL ' . $stage . ($stage === 'preflight' ? ': ' . $error->getMessage() : '') . "\n");
    $failed = true;
} finally {
    if ($database instanceof PDO) {
        try {
            $database->exec('SHUTDOWN');
        } catch (PDOException $error) {
            // Confirm the owned process stopped below, even on disconnect.
        }
    }
    if (is_resource($process)) {
        for ($attempt = 0; $attempt < 40 && proc_get_status($process)['running']; $attempt++) {
            usleep(250000);
        }
        if (proc_get_status($process)['running']) {
            proc_terminate($process);
            $failed = true;
        }
        proc_close($process);
    }
}
exit(isset($failed) ? 1 : 0);
