<?php
require_once __DIR__ . '/config.php';

$dbError = '';
try {
    $pdo = db();
} catch (PDOException $exception) {
    $pdo = null;
    $dbError = 'Database is not connected. Please start MySQL from XAMPP, then refresh this page.';
}

$success = '';
$error = '';

$services = [
    'Dental Checkup',
    'Teeth Whitening',
    'Root Canal',
    'Dental Implants',
    'Orthodontics',
    'Emergency Care',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'book') {
    $name = trim((string)($_POST['patient_name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $service = trim((string)($_POST['service'] ?? ''));
    $date = trim((string)($_POST['appointment_date'] ?? ''));
    $time = trim((string)($_POST['appointment_time'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));

    if (!$pdo) {
        $error = $dbError;
    } elseif ($name === '' || $phone === '' || $service === '' || $date === '' || $time === '') {
        $error = 'Please fill in all required booking fields.';
    } elseif (!in_array($service, $services, true)) {
        $error = 'Please choose a valid service.';
    } elseif (!appointment_is_available($pdo, $date, $time)) {
        $error = 'This time is outside doctor hours or already booked. Please choose another slot.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO bookings (patient_name, phone, service, appointment_date, appointment_time, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $phone, $service, $date, date('H:i:s', strtotime($time)), $notes ?: null]);
            $success = 'Your appointment request was sent successfully.';
        } catch (PDOException $exception) {
            $error = 'That slot was just taken. Please choose a different appointment time.';
        }
    }
}

$availability = $pdo ? $pdo->query("SELECT * FROM appointment_days WHERE available_date >= CURDATE() ORDER BY available_date LIMIT 10")->fetchAll() : [];
$settings = $pdo ? $pdo->query("SELECT * FROM doctor_settings WHERE id = 1")->fetch() : ['doctor_name' => 'Dr. Admin'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BrightSmile Dentistry</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <a class="doctor-fab" href="doctor.php" aria-label="Doctor login" title="Doctor login">
        <span></span>
    </a>

    <header class="site-header">
        <nav class="nav">
            <a class="brand" href="#home">
                <span class="brand-mark">+</span>
                <span>BrightSmile</span>
            </a>
            <button class="nav-toggle" type="button" aria-label="Open menu">
                <span></span><span></span><span></span>
            </button>
            <div class="nav-links">
                <a href="#services">Services</a>
                <a href="#doctor">Doctor</a>
                <a href="#booking">Booking</a>
                <a href="#contact">Contact</a>
            </div>
        </nav>
    </header>

    <main>
        <section class="hero" id="home">
            <div class="hero-bg"></div>
            <div class="hero-content reveal">
                <p class="eyebrow">Modern Dental Care</p>
                <h1>Confident smiles, calm visits, expert care.</h1>
                <p class="hero-copy">A polished dentistry experience with preventive care, cosmetic treatments, emergency support, and easy online booking.</p>
                <div class="hero-actions">
                    <a class="btn primary" href="#booking">Book appointment</a>
                    <a class="btn ghost" href="#services">View services</a>
                </div>
            </div>
            <div class="hero-card reveal delay">
                <span class="pulse"></span>
                <strong>Open for appointments</strong>
                <p><?= e($settings['doctor_name'] ?? 'Dr. Admin') ?> manages available hours from the doctor panel.</p>
            </div>
        </section>

        <section class="stats band">
            <div><strong>12+</strong><span>Years Experience</span></div>
            <div><strong>8k</strong><span>Smiles Treated</span></div>
            <div><strong>24h</strong><span>Emergency Reply</span></div>
        </section>

        <section class="section" id="services">
            <div class="section-heading reveal">
                <p class="eyebrow">Services</p>
                <h2>Everything your smile needs.</h2>
            </div>
            <div class="service-grid">
                <?php foreach ($services as $index => $service): ?>
                    <article class="service-card reveal">
                        <div class="service-icon"><?= sprintf('%02d', $index + 1) ?></div>
                        <h3><?= e($service) ?></h3>
                        <p><?= e(service_description($service)) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="doctor-band" id="doctor">
            <div class="doctor-visual reveal">
                <div class="orbit"></div>
                <div class="tooth-shape"></div>
            </div>
            <div class="doctor-copy reveal">
                <p class="eyebrow">Doctor Schedule</p>
                <h2>Clear hours, cleaner booking.</h2>
                <p>The doctor controls working days and appointment windows from a private dashboard. Patients can only request a slot that fits those hours.</p>
                <div class="hours-list">
                    <?php foreach ($availability as $slot): ?>
                        <span><?= e(date('M d', strtotime($slot['available_date']))) ?> <?= e(substr($slot['start_time'], 0, 5)) ?>-<?= e(substr($slot['end_time'], 0, 5)) ?></span>
                    <?php endforeach; ?>
                    <?php if (count($availability) === 0): ?>
                        <span>No open days yet</span>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="section booking-section" id="booking">
            <div class="section-heading reveal">
                <p class="eyebrow">Booking</p>
                <h2>Reserve your visit.</h2>
            </div>

            <?php if ($success !== ''): ?>
                <div class="alert success"><?= e($success) ?></div>
            <?php endif; ?>
            <?php if ($dbError !== '' && $error === ''): ?>
                <div class="alert error"><?= e($dbError) ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert error"><?= e($error) ?></div>
            <?php endif; ?>

            <form class="booking-form reveal" method="post">
                <input type="hidden" name="action" value="book">
                <label>
                    Full name
                    <input type="text" name="patient_name" required placeholder="Your name">
                </label>
                <label>
                    Phone
                    <input type="tel" name="phone" required placeholder="+961 ...">
                </label>
                <label>
                    Service
                    <select name="service" required>
                        <option value="">Choose service</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?= e($service) ?>"><?= e($service) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Date
                    <input type="date" name="appointment_date" required min="<?= e(date('Y-m-d')) ?>">
                </label>
                <label>
                    Time
                    <select name="appointment_time" required data-slots-url="available_slots.php">
                        <option value="">Choose date first</option>
                    </select>
                </label>
                <label class="wide">
                    Notes
                    <textarea name="notes" rows="4" placeholder="Tell us anything useful before the visit"></textarea>
                </label>
                <button class="btn primary wide" type="submit">Send booking request</button>
            </form>
        </section>
    </main>

    <footer class="footer" id="contact">
        <div>
            <strong>BrightSmile Dentistry</strong>
            <p>Beirut, Lebanon · +961 71869498 ·brightsmile@example.com</p>
        </div>
        <a href="#home">Back to top</a>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>
<?php
function service_description(string $service): string
{
    switch ($service) {
        case 'Dental Checkup':
            return 'Routine exams, cleaning, and prevention plans for long-term oral health.';
        case 'Teeth Whitening':
            return 'Bright, natural-looking whitening with safe professional treatment.';
        case 'Root Canal':
            return 'Pain-relieving treatment designed to save and protect your natural tooth.';
        case 'Dental Implants':
            return 'Durable tooth replacement planned carefully for comfort and function.';
        case 'Orthodontics':
            return 'Alignment options that improve bite, smile shape, and daily confidence.';
        default:
            return 'Fast support for pain, swelling, broken teeth, and urgent dental concerns.';
    }
}
?>
