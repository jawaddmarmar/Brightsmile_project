<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('Asia/Beirut');

$currentHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocalHost = $currentHost === ''
    || str_starts_with($currentHost, 'localhost')
    || str_starts_with($currentHost, '127.0.0.1');

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'dentistry_db';

$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;
    $dbHost = $localConfig['db_host'] ?? $dbHost;
    $dbUser = $localConfig['db_user'] ?? $dbUser;
    $dbPass = $localConfig['db_pass'] ?? $dbPass;
    $dbName = $localConfig['db_name'] ?? $dbName;
} elseif (!$isLocalHost) {
    throw new PDOException('Production database config is missing. Create config.local.php on the server.');
}

function db(): PDO
{
    static $pdo = null;
    global $dbHost, $dbUser, $dbPass, $dbName;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $exception) {
        $bootstrap = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $bootstrap->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    initialize_database($pdo);
    return $pdo;
}

function initialize_database(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS doctor_settings (
            id INT PRIMARY KEY,
            doctor_name VARCHAR(120) NOT NULL DEFAULT 'Dr. Admin',
            slot_minutes INT NOT NULL DEFAULT 30
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS availability (
            id INT AUTO_INCREMENT PRIMARY KEY,
            day_of_week TINYINT NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            UNIQUE KEY unique_day_time (day_of_week, start_time, end_time)
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS appointment_days (
            id INT AUTO_INCREMENT PRIMARY KEY,
            available_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            KEY index_available_date (available_date)
        )
    ");
    try {
        $pdo->exec("ALTER TABLE appointment_days DROP INDEX unique_available_date");
    } catch (PDOException $exception) {
        // Older installs may not have the one-date-only index.
    }
    try {
        $pdo->exec("ALTER TABLE appointment_days ADD INDEX index_available_date (available_date)");
    } catch (PDOException $exception) {
        // Index already exists.
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bookings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_name VARCHAR(120) NOT NULL,
            phone VARCHAR(40) NOT NULL,
            service VARCHAR(120) NOT NULL,
            appointment_date DATE NOT NULL,
            appointment_time TIME NOT NULL,
            notes TEXT NULL,
            status ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_slot (appointment_date, appointment_time)
        )
    ");

    $pdo->exec("INSERT IGNORE INTO doctor_settings (id, doctor_name, slot_minutes) VALUES (1, 'Dr. Admin', 30)");

    $count = (int)$pdo->query("SELECT COUNT(*) FROM availability")->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare("INSERT INTO availability (day_of_week, start_time, end_time) VALUES (?, ?, ?)");
        foreach ([1, 2, 3, 4, 5] as $day) {
            $stmt->execute([$day, '09:00:00', '17:00:00']);
        }
        $stmt->execute([6, '10:00:00', '14:00:00']);
    }

    $dateCount = (int)$pdo->query("SELECT COUNT(*) FROM appointment_days")->fetchColumn();
    if ($dateCount === 0) {
        $stmt = $pdo->prepare("INSERT INTO appointment_days (available_date, start_time, end_time) VALUES (?, ?, ?)");
        for ($i = 0; $i < 21; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} days"));
            $day = (int)date('w', strtotime($date));
            if ($day >= 1 && $day <= 5) {
                $stmt->execute([$date, '09:00:00', '17:00:00']);
            } elseif ($day === 6) {
                $stmt->execute([$date, '10:00:00', '14:00:00']);
            }
        }
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function is_doctor_logged_in(): bool
{
    return isset($_SESSION['doctor_logged_in']) && $_SESSION['doctor_logged_in'] === true;
}

function require_doctor(): void
{
    if (!is_doctor_logged_in()) {
        header('Location: doctor.php');
        exit;
    }
}

function day_name(int $day): string
{
    $days = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    return $days[$day] ?? 'Unknown';
}

function appointment_is_available(PDO $pdo, string $date, string $time): bool
{
    $timestamp = strtotime($date . ' ' . $time);
    if ($timestamp === false || $timestamp <= time()) {
        return false;
    }

    $timeValue = date('H:i:s', $timestamp);

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM appointment_days
        WHERE available_date = ?
        AND ? >= start_time
        AND ? < end_time
    ");
    $stmt->execute([$date, $timeValue, $timeValue]);
    if ((int)$stmt->fetchColumn() === 0) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM bookings
        WHERE appointment_date = ?
        AND appointment_time = ?
        AND status <> 'cancelled'
    ");
    $stmt->execute([$date, $timeValue]);

    return (int)$stmt->fetchColumn() === 0;
}

function available_slots(PDO $pdo, string $date): array
{
    $day = $pdo->prepare("SELECT * FROM appointment_days WHERE available_date = ? ORDER BY start_time");
    $day->execute([$date]);
    $availabilityBlocks = $day->fetchAll();
    if (count($availabilityBlocks) === 0) {
        return [];
    }

    $settings = $pdo->query("SELECT slot_minutes FROM doctor_settings WHERE id = 1")->fetch();
    $slotMinutes = max(15, (int)($settings['slot_minutes'] ?? 30));
    $booked = $pdo->prepare("
        SELECT appointment_time FROM bookings
        WHERE appointment_date = ?
        AND status <> 'cancelled'
    ");
    $booked->execute([$date]);
    $bookedTimes = array_flip(array_map(static function (array $row): string {
        return substr($row['appointment_time'], 0, 5);
    }, $booked->fetchAll()));

    $slots = [];
    foreach ($availabilityBlocks as $availability) {
        $start = strtotime($date . ' ' . $availability['start_time']);
        $end = strtotime($date . ' ' . $availability['end_time']);
        if ($start === false || $end === false || $start >= $end) {
            continue;
        }

        for ($time = $start; $time < $end; $time += $slotMinutes * 60) {
            if ($date === date('Y-m-d') && $time <= time()) {
                continue;
            }
            $label = date('H:i', $time);
            if (!isset($bookedTimes[$label])) {
                $slots[$label] = $label;
            }
        }
    }

    ksort($slots);
    return array_values($slots);
}
?>
