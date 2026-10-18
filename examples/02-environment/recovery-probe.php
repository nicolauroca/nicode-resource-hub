<?php
// SPDX-License-Identifier: GPL-2.0-or-later
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || $argc !== 3 || !in_array($argv[2], ['seed', 'change', 'check'], true)) {
    exit(2);
}
try {
    require $argv[1] . '/configuration.php';
    $config = new JConfig();
    if ($config->db !== 'nrh_course' || $config->dbprefix !== 'nrh_' || $config->host !== '127.0.0.1:33079') {
        throw new RuntimeException('Not the declared disposable lab.');
    }
    $db = new PDO('mysql:host=127.0.0.1;port=33079;dbname=nrh_course', $config->user, $config->password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if ($argv[2] === 'seed') {
        // No IF NOT EXISTS: accidentally repeating setup must fail, not replace evidence.
        $db->exec('CREATE TABLE nrh_course_recovery_probe (id INT PRIMARY KEY, marker VARCHAR(40) NOT NULL) ENGINE=InnoDB');
        $db->exec("INSERT INTO nrh_course_recovery_probe VALUES (1, 'before-checkpoint')");
    } elseif ($argv[2] === 'change') {
        $db->exec("UPDATE nrh_course_recovery_probe SET marker = 'after-checkpoint' WHERE id = 1");
    } else {
        if ($db->query('SELECT marker FROM nrh_course_recovery_probe WHERE id = 1')->fetchColumn() !== 'before-checkpoint') {
            throw new RuntimeException('Saved marker not restored.');
        }
    }
    echo 'PASS recovery probe ' . $argv[2] . PHP_EOL;
} catch (Throwable $error) {
    // Driver exceptions can contain connection details: do not print them.
    fwrite(STDERR, "FAIL recovery probe; check the lab, database and expected exercise stage privately.\n");
    exit(1);
}
