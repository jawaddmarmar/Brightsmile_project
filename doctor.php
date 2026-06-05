<?php
require_once __DIR__ . '/config.php';

$dbError = '';
try {
    $pdo = db();
} catch (PDOException $exception) {
    $pdo = null;
    $dbError = 'Database is not connected. Please start MySQL from XAMPP, then refresh this page.';
}

$loginError = '';
$message = '';
$doctorUser = 'admin';
$doctorPass = '123456';

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: doctor.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = trim((string)($_POST['password'] ?? ''));
    if ($username === $doctorUser && $password === $doctorPass) {
        $_SESSION['doctor_logged_in'] = true;
        header('Location: doctor.php');
        exit;
    }
    $loginError = 'Wrong username or password.';
}

if (is_doctor_logged_in() && $pdo && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'availability') {
    try {
        $date = trim((string)($_POST['available_date'] ?? ''));
        $start = trim((string)($_POST['start_time'] ?? ''));
        $end = trim((string)($_POST['end_time'] ?? ''));
        $slotMinutes = max(15, min(120, (int)($_POST['slot_minutes'] ?? 30)));
        $doctorName = trim((string)($_POST['doctor_name'] ?? 'Dr. Admin')) ?: 'Dr. Admin';
        $settings = $pdo->prepare("UPDATE doctor_settings SET doctor_name = ?, slot_minutes = ? WHERE id = 1");
        $settings->execute([$doctorName, $slotMinutes]);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $start === '' || $end === '' || $start >= $end) {
            $message = 'Choose a valid date and time range.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO appointment_days (available_date, start_time, end_time)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$date, $start . ':00', $end . ':00']);
            $message = 'Time block added to doctor calendar.';
        }
    } catch (Throwable $exception) {
        $message = 'Could not save hours. Please try again.';
    }
}

if (is_doctor_logged_in() && $pdo && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'close_block') {
    $blockId = (int)($_POST['block_id'] ?? 0);
    if ($blockId > 0) {
        $stmt = $pdo->prepare("DELETE FROM appointment_days WHERE id = ?");
        $stmt->execute([$blockId]);
        $message = 'Time block closed for booking.';
    }
}

if (is_doctor_logged_in() && $pdo && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'status') {
    $status = (string)($_POST['status'] ?? 'pending');
    $id = (int)($_POST['booking_id'] ?? 0);
    if (in_array($status, ['pending', 'confirmed', 'cancelled'], true) && $id > 0) {
        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        $message = 'Booking status updated.';
    }
}

if (is_doctor_logged_in() && $pdo && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_booking') {
    $id = (int)($_POST['booking_id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'Appointment deleted.';
    }
}

$settings = $pdo ? $pdo->query("SELECT * FROM doctor_settings WHERE id = 1")->fetch() : ['doctor_name' => 'Dr. Admin', 'slot_minutes' => 30];
$calendarDays = $pdo ? $pdo->query("SELECT * FROM appointment_days WHERE available_date >= CURDATE() ORDER BY available_date ASC LIMIT 40")->fetchAll() : [];

$bookings = [];
if (is_doctor_logged_in() && $pdo) {
    $bookings = $pdo->query("
        SELECT * FROM bookings
        ORDER BY appointment_date ASC, appointment_time ASC, created_at DESC
    ")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="panel-body">
    <main class="panel-shell">
        <?php if (!is_doctor_logged_in()): ?>
            <section class="login-card">
                <a class="back-link" href="index.php">← Website</a>
                <h1>Doctor Login</h1>
                <p>Authorized doctor access only.</p>
                <?php if ($loginError !== ''): ?>
                    <div class="alert error"><?= e($loginError) ?></div>
                <?php endif; ?>
                <form method="post" class="login-form">
                    <input type="hidden" name="action" value="login">
                    <label>Username<input type="text" name="username" required autofocus></label>
                    <label>Password<input type="password" name="password" required></label>
                    <button class="btn primary" type="submit">Login</button>
                </form>
            </section>
        <?php else: ?>
            <section class="panel-top">
                <div>
                    <a class="back-link" href="index.php">← Website</a>
                    <h1>Doctor Dashboard</h1>
                    <p>Manage appointments and working hours.</p>
                </div>
                <a class="btn ghost" href="doctor.php?logout=1">Logout</a>
            </section>

            <?php if ($message !== ''): ?>
                <div class="alert success"><?= e($message) ?></div>
            <?php endif; ?>
            <?php if ($dbError !== ''): ?>
                <div class="alert error"><?= e($dbError) ?></div>
            <?php endif; ?>

            <section class="dashboard-grid">
                <section class="panel-card">
                    <h2>Doctor Calendar</h2>
                    <form class="calendar-form" method="post">
                        <input type="hidden" name="action" value="availability">
                        <label>Doctor name<input type="text" name="doctor_name" value="<?= e($settings['doctor_name'] ?? 'Dr. Admin') ?>"></label>
                        <label>Appointment length<input type="number" name="slot_minutes" min="15" max="120" step="15" value="<?= e((string)($settings['slot_minutes'] ?? 30)) ?>"></label>
                        <div class="calendar-editor">
                            <label>Date<input type="date" name="available_date" min="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d')) ?>" required></label>
                            <label>From<input type="time" name="start_time" value="09:00" required></label>
                            <label>To<input type="time" name="end_time" value="17:00" required></label>
                        </div>
                        <button class="btn primary" type="submit">Add time block</button>
                    </form>

                    <div class="calendar-list">
                        <h3>Open Booking Days</h3>
                        <?php if (count($calendarDays) === 0): ?>
                            <p>No open dates yet.</p>
                        <?php endif; ?>
                        <?php foreach ($calendarDays as $day): ?>
                            <div class="calendar-day">
                                <span>
                                    <strong><?= e(date('D, M d', strtotime($day['available_date']))) ?></strong>
                                    <?= e(substr($day['start_time'], 0, 5)) ?>-<?= e(substr($day['end_time'], 0, 5)) ?>
                                </span>
                                <form method="post">
                                    <input type="hidden" name="action" value="close_block">
                                    <input type="hidden" name="block_id" value="<?= (int)$day['id'] ?>">
                                    <button type="submit">Close</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="panel-card bookings-card">
                    <h2>Appointments</h2>
                    <div class="booking-table">
                        <?php if (count($bookings) === 0): ?>
                            <p>No appointments yet.</p>
                        <?php endif; ?>
                        <?php foreach ($bookings as $booking): ?>
                            <article class="booking-item">
                                <div>
                                    <strong><?= e($booking['patient_name']) ?></strong>
                                    <span><?= e($booking['service']) ?></span>
                                    <small><?= e($booking['appointment_date']) ?> at <?= e(substr($booking['appointment_time'], 0, 5)) ?></small>
                                    <small><?= e($booking['phone']) ?></small>
                                    <?php if ($booking['notes']): ?>
                                        <p><?= e($booking['notes']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="appointment-actions">
                                    <form method="post" class="delete-form">
                                        <input type="hidden" name="action" value="delete_booking">
                                        <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                                        <button type="submit" aria-label="Delete appointment" title="Delete appointment">&times;</button>
                                    </form>
                                    <form method="post" class="status-form">
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                                        <select name="status">
                                            <?php foreach (['pending', 'confirmed', 'cancelled'] as $status): ?>
                                                <option value="<?= e($status) ?>" <?= $booking['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit">Update</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
