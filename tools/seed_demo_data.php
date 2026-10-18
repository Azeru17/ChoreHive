<?php
/**
 * ChoreHive demo data seeder.
 *
 *   php tools/seed_demo_data.php            # add demo data (safe to re-run)
 *   php tools/seed_demo_data.php --reset    # remove previously seeded demo data first
 *   php tools/seed_demo_data.php --reset --force
 *
 * Produces a believable marketplace so every screen has something to show:
 * approved workers with badges and reviews, bookings covering every status in
 * the lifecycle, checklists, before/after photos, chat threads, notifications,
 * saved addresses and an admin contact inbox.
 *
 * All seeded accounts share one password and use @demo.chorehive.test emails,
 * so `--reset` can find and remove exactly what this script created.
 *
 * CLI only.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

mysqli_report(MYSQLI_REPORT_OFF);

require __DIR__ . '/../config.php';

const DEMO_DOMAIN = '@demo.chorehive.test';
const DEMO_PASSWORD = 'ChoreHive2026!';

$force = in_array('--force', $argv, true);
$reset = in_array('--reset', $argv, true);

$line = str_repeat('-', 66);

function out(string $msg = ''): void {
    echo $msg . "\n";
}

function q(string $sql): bool {
    global $conn;
    $ok = $conn->query($sql);
    if (!$ok) {
        fwrite(STDERR, "SQL ERROR: " . $conn->error . "\n  " . substr(preg_replace('/\s+/', ' ', $sql), 0, 160) . "\n");
        exit(1);
    }
    return true;
}

function qid(string $sql): int {
    q($sql);
    global $conn;
    return (int)$conn->insert_id;
}

function esc($v): string {
    global $conn;
    if ($v === null) return 'NULL';
    return "'" . $conn->real_escape_string((string)$v) . "'";
}

/** Store an image from assets/img through whichever media backend is configured. */
function seed_store_image(string $sourceFile, string $filename, string $visibility = 'private'): bool {
    global $conn;
    $src = __DIR__ . '/../assets/img/' . $sourceFile;
    if (!is_file($src)) {
        return false;
    }
    if (MEDIA_STORAGE === 'database') {
        $data = file_get_contents($src);
        if ($data === false) return false;
        $conn->query("INSERT INTO media_files (filename, mime_type, file_data, visibility) VALUES (" .
            esc($filename) . ", 'image/jpeg', " . esc($data) . ", " . esc($visibility) . ")");
        return true;
    }
    if (!is_dir(UPLOAD_DIR)) {
        @mkdir(UPLOAD_DIR, 0755, true);
    }
    return copy($src, UPLOAD_DIR . '/' . $filename);
}

function now_minus(int $days, int $hours = 0, int $minutes = 0): string {
    return date('Y-m-d H:i:s', strtotime("-" . $days . " days " . (($hours * 60) + $minutes) . " minutes"));
}

function plus_days(int $days, int $hour = 10): string {
    return date('Y-m-d H:i:s', strtotime("+" . $days . " days", strtotime(date('Y-m-d') . " {$hour}:00:00")));
}

/* ------------------------------------------------------------------ */
/* 1. Reset (optional)                                                 */
/* ------------------------------------------------------------------ */
if ($reset) {
    $count = $conn->query("SELECT COUNT(*) c FROM users WHERE email LIKE '%" . DEMO_DOMAIN . "'");
    $existing = (int)($count->fetch_assoc()['c']);

    if ($existing === 0) {
        out("Nothing to reset (no " . DEMO_DOMAIN . " accounts found).");
    } else {
        out("Found {$existing} existing demo account(s).");
        if (!$force) {
            out("\nThis will permanently delete demo users and every booking, message,");
            out("checklist, photo, review and notification attached to them.");
            out("Re-run with --force to confirm:");
            out("\n    php tools/seed_demo_data.php --reset --force\n");
            exit(1);
        }

        $ids = [];
        $r = $conn->query("SELECT id FROM users WHERE email LIKE '%" . DEMO_DOMAIN . "'");
        while ($row = $r->fetch_assoc()) {
            $ids[] = (int)$row['id'];
        }
        $list = implode(',', $ids);

        // Delete in dependency order so this works with or without FK enforcement.
        foreach ([
            "DELETE FROM feedback WHERE client_id IN ($list)",
            "DELETE f FROM feedback f JOIN bookings b ON b.id=f.booking_id WHERE b.client_id IN ($list) OR b.worker_id IN ($list)",
            "DELETE p FROM booking_photos p JOIN bookings b ON b.id=p.booking_id WHERE b.client_id IN ($list) OR b.worker_id IN ($list)",
            "DELETE c FROM checklist_items c JOIN bookings b ON b.id=c.booking_id WHERE b.client_id IN ($list) OR b.worker_id IN ($list)",
            "DELETE FROM bookings WHERE client_id IN ($list) OR worker_id IN ($list)",
            "DELETE m FROM chat_messages m JOIN chat_threads t ON t.id=m.thread_id WHERE t.client_id IN ($list) OR t.worker_id IN ($list)",
            "DELETE FROM chat_threads WHERE client_id IN ($list) OR worker_id IN ($list)",
            "DELETE FROM badges WHERE worker_id IN ($list)",
            "DELETE FROM notifications WHERE user_id IN ($list)",
            "DELETE FROM saved_addresses WHERE client_id IN ($list)",
            "DELETE FROM favorite_workers WHERE client_id IN ($list) OR worker_id IN ($list)",
            "DELETE FROM worker_portfolio WHERE worker_id IN ($list)",
            "DELETE FROM worker_presence WHERE worker_id IN ($list)",
            "DELETE FROM audit_log WHERE user_id IN ($list)",
            "DELETE FROM users WHERE id IN ($list)",
        ] as $sql) {
            $conn->query($sql);
        }
        // Free media belonging to the removed demo records.
        $conn->query("DELETE FROM media_files WHERE filename LIKE 'demo-%'");

        out("Removed {$existing} demo account(s) and their related records.");
    }
    out("");
}

mysqli_report(MYSQLI_REPORT_OFF);

/* ------------------------------------------------------------------ */
/* 2. Accounts                                                         */
/* ------------------------------------------------------------------ */
$pass = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);

function seed_user(string $name, string $email, string $role, array $extra = []): int {
    global $pass;
    global $conn;
    $r = $conn->query("SELECT id FROM users WHERE email = " . esc($email));
    if ($r && ($row = $r->fetch_assoc())) {
        $existing = (int)$row['id'];
        // Keep the documented demo password authoritative, so the credentials
        // printed at the end always work no matter when the seed was run.
        $conn->query("UPDATE users SET password = " . esc($pass) . ", role = " . esc($role) . " WHERE id = " . $existing);
        return $existing;
    }
    $id = get_next_role_id($conn, $role);
    if (!$id) {
        fwrite(STDERR, "Could not allocate an ID for {$role}\n");
        exit(1);
    }
    $cols = ['id', 'name', 'email', 'password', 'role'];
    $vals = [$id, esc($name), esc($email), esc($pass), esc($role)];
    foreach ($extra as $k => $v) {
        $cols[] = $k;
        $vals[] = $v;
    }
    q('INSERT INTO users (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')');
    return $id;
}

out($line);
out("ChoreHive demo data");
out($line);

// Always guarantee a demo admin whose credentials match the ones printed below.
// Re-using an arbitrary pre-existing admin would leave the documented login unusable.
$adminId = seed_user('Maria Santos', 'admin' . DEMO_DOMAIN, 'admin', [
    'phone' => esc('+63 917 555 0142'),
    'address' => esc('Cebu City Center, Cebu'),
    'city' => esc('Cebu City'),
    'worker_approved' => '1',
    'is_new' => '0',
]);
out("Demo admin ready (id {$adminId}).");

// --- Workers ---------------------------------------------------------
$workers = [
    [
        'email' => 'angelica' . DEMO_DOMAIN,
        'name'  => 'Angelica Ramos',
        'phone' => '+63 917 555 0110',
        'city'  => 'Cebu City',
        'address' => 'Banana Road, Cebu City',
        'bio'   => 'Five years of deep-cleaning experience across condos and family homes in Cebu. I bring my own eco-friendly supplies and a written checklist so nothing gets missed.',
        'badges' => ['Top Rated', 'Deep Cleaning Certified', '100 Jobs Completed'],
    ],
    [
        'email' => 'miguel' . DEMO_DOMAIN,
        'name'  => 'Miguel Santos',
        'phone' => '+63 917 555 0121',
        'city'  => 'Cebu City',
        'address' => 'Talamban, Cebu City',
        'bio'   => 'Kitchen and laundry specialist. Stovetops, range hoods and tiled walls are my comfort zone. Fast, tidy work with a clear before-and-after record.',
        'badges' => ['Kitchen Specialist', 'Fast Response'],
    ],
    [
        'email' => 'princess' . DEMO_DOMAIN,
        'name'  => 'Princess Lim',
        'phone' => '+63 917 555 0133',
        'city'  => 'Mandaue',
        'address' => 'Mandaue City, Cebu',
        'bio'   => 'Move-in/move-out and post-construction cleaning. I handle dusty units, paint residue and full disinfecting jobs for landlords and tenants.',
        'badges' => ['Disinfection Certified', 'Move-in / Move-out Specialist'],
    ],
    [
        'email' => 'paolo' . DEMO_DOMAIN,
        'name'  => 'Paolo Bautista',
        'phone' => '+63 917 555 0144',
        'city'  => 'Lapu-Lapu',
        'address' => 'Lapu-Lapu City, Cebu',
        'bio'   => 'Glass, mirrors, floors and tiles. If it streaks, I can fix it. Regular clients keep me booked, so book a few days ahead.',
        'badges' => ['Glass & Window Care', 'Floor & Tile Care'],
    ],
];

$badgeDescriptions = [
    'Top Rated' => 'Maintained a 4.8 or higher average across verified client reviews.',
    'Deep Cleaning Certified' => 'Completed advanced deep-cleaning training and assessment.',
    '100 Jobs Completed' => 'Finished one hundred verified bookings on ChoreHive.',
    'Kitchen Specialist' => 'Consistently strong results on kitchen deep cleans.',
    'Fast Response' => 'Usually replies to a booking request within one hour.',
    'Disinfection Certified' => 'Trained in hospital-grade disinfection procedures.',
    'Move-in / Move-out Specialist' => 'Regularly handles unit turnovers and tenancy cleanings.',
    'Glass & Window Care' => 'Verified skill streak-free glass, mirrors and frames.',
    'Floor & Tile Care' => 'Verified skill in mopping, scrubbing and tile restoration.',
];

$workerIds = [];
foreach ($workers as $w) {
    $id = seed_user($w['name'], $w['email'], 'worker', [
        'phone'           => esc($w['phone']),
        'address'         => esc($w['address']),
        'city'            => esc($w['city']),
        'bio'             => esc($w['bio']),
        'worker_approved' => '1',
        'is_new'          => '0',
    ]);
    $workerIds[$w['email']] = $id;

    foreach ($w['badges'] as $badge) {
        $dup = $conn->query("SELECT id FROM badges WHERE worker_id=" . (int)$id . " AND name=" . esc($badge));
        if (!$dup || !$dup->num_rows) {
            q("INSERT INTO badges (worker_id, name, description, earned_at) VALUES (" .
                (int)$id . ", " . esc($badge) . ", " . esc($badgeDescriptions[$badge] ?? 'Earned through consistent, verified performance.') .
                ", " . esc(now_minus(random_int(20, 200))) . ")");
        }
    }
    q("INSERT INTO worker_presence (worker_id, last_seen) VALUES (" . (int)$id . ", " . esc(date('Y-m-d H:i:s')) . ")
       ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen)");
}
out('Created/updated ' . count($workerIds) . ' approved workers with badges.');

// --- Clients ---------------------------------------------------------
$clients = [
    ['email' => 'denise' . DEMO_DOMAIN,  'name' => 'Denise Abella',    'phone' => '+63 917 555 0201', 'city' => 'Cebu City', 'address' => 'Mabolo, Cebu City'],
    ['email' => 'carlo' . DEMO_DOMAIN,   'name' => 'Carlo Villanueva', 'phone' => '+63 917 555 0202', 'city' => 'Mandaue',   'address' => 'Mandaue City, Cebu'],
    ['email' => 'bea' . DEMO_DOMAIN,     'name' => 'Bea Flores',       'phone' => '+63 917 555 0203', 'city' => 'Cebu City', 'address' => 'Cebu City, Cebu'],
];

$clientIds = [];
foreach ($clients as $c) {
    $id = seed_user($c['name'], $c['email'], 'client', [
        'phone'          => esc($c['phone']),
        'address'        => esc($c['address']),
        'city'           => esc($c['city']),
        'worker_approved'=> '0',
        'is_new'         => '0',
    ]);
    $clientIds[$c['email']] = $id;
}
out('Created/updated ' . count($clientIds) . ' client accounts.');

$W = [];
foreach ($workerIds as $email => $id) { $W[strstr($email, '@', true)] = $id; }  // 'angelica' => id
$C = [];
foreach ($clientIds as $email => $id) { $C[strstr($email, '@', true)] = $id; }    // 'denise'   => id

$serviceIds = [];
$r = $conn->query("SELECT id, name FROM services ORDER BY id");
while ($s = $r->fetch_assoc()) {
    $serviceIds[$s['name']] = (int)$s['id'];
}
$svc = $serviceIds ?: [];

/* ------------------------------------------------------------------ */
/* 3. Bookings across the whole lifecycle                              */
/* ------------------------------------------------------------------ */
$existingBookings = (int)($conn->query("SELECT COUNT(*) c FROM bookings")->fetch_assoc()['c']);

if ($existingBookings === 0) {

    $plan = [
        // client, worker, service, status, daysAgo, price, method
        ['denise', 'angelica', 'Deep Cleaning',          'completed',  22, 700,  'gcash', 5,   5],
        ['carlo',  'miguel',  'Kitchen Deep Clean',      'completed',  15, 700,  'cash',  5,   5],
        ['denise', 'paolo',    'Laundry & Ironing',      'completed',  9,  300,  'gcash', 4,   4],
        ['bea',    'princess', 'Move-in/Move-out Cleaning','completed', 5, 900,  'cash',  5,   5],
        ['carlo',  'angelica', 'Basic Home Cleaning',    'completed',  3,  350,  'gcash', 5,   4],

        ['denise', 'angelica', 'Deep Cleaning',          'awaiting_verification', 1, 700, 'gcash', 5, 5],
        ['bea',    'miguel',   'Kitchen Deep Clean',     'awaiting_verification', 0, 700, 'cash',  5, 4],

        ['carlo',  'paolo',    'Basic Home Cleaning',    'in_progress', 0, 350, 'gcash', 3, 1],
        ['denise', 'princess', 'Move-in/Move-out Cleaning','in_progress', 0, 900, 'cash',  4, 2],

        ['bea',    'angelica', 'Basic Home Cleaning',    'assigned',   0, 350, 'gcash', 4, 0],
        ['carlo',  'princess', 'Disinfection',           'assigned',   0, 500, 'cash',  3, 0],

        ['denise', null,       'Basic Home Cleaning',    'pending',    0, 350, 'cash',  3, 0],

        ['bea',    'paolo',    'Laundry & Ironing',      'cancelled',  6, 300, 'gcash', 2, 0],
    ];

    $serviceAliases = [
        'Kitchen Deep Clean' => 'Deep Cleaning',
        'Disinfection'       => 'Deep Cleaning',
    ];

    $created = 0;
    foreach ($plan as $row) {
        [$ck, $wk, $serviceName, $status, $daysAgo, $price, $method, $taskCount, $doneCount] = $row;

        $clientId = $C[$ck];
        $workerId = $wk ? $W[$wk] : null;

        $serviceName = $serviceAliases[$serviceName] ?? $serviceName;
        $serviceId = $svc[$serviceName] ?? null;
        if (!$serviceId) {
            $serviceId = $svc['Basic Home Cleaning'] ?? (int)$conn->query("SELECT id FROM services ORDER BY id LIMIT 1")->fetch_assoc()['id'];
        }

        $isFinished = in_array($status, ['completed', 'awaiting_verification'], true);
        $paymentStatus = $status === 'completed'
            ? 'paid'
            : ($isFinished ? 'paid' : 'unpaid');

        $clientRow = $conn->query("SELECT address, city FROM users WHERE id=" . (int)$clientId)->fetch_assoc();
        $address = $clientRow['address'] ?? 'Cebu City, Cebu';
        $city = $clientRow['city'] ?? 'Cebu City';

        $schedule = $status === 'completed' || $status === 'cancelled'
            ? now_minus($daysAgo, 3)
            : plus_days($daysAgo, 10);

        $notes = 'Please text before arriving. Building security will ask for your name.';
        $cancelReason = $status === 'cancelled' ? 'Client had to reschedule for the following week.' : null;

        $bookingId = qid(
            "INSERT INTO bookings (client_id, worker_id, service_id, schedule, address, notes, status, " .
            "payment_status, payment_method, payment_reference, paid_at, client_started_ack, cancel_reason, " .
            "address_lat, address_lng, total_price, created_at) VALUES (" .
            (int)$clientId . ', ' . ($workerId ? (int)$workerId : 'NULL') . ', ' . (int)$serviceId . ', ' .
            esc($schedule) . ', ' . esc($address) . ', ' . esc($notes) . ', ' . esc($status) . ', ' .
            esc($paymentStatus) . ', ' . esc($method) . ', ' .
            ($paymentStatus === 'paid' ? esc('PAY-' . strtoupper(bin2hex(random_bytes(3)))) : 'NULL') . ', ' .
            ($paymentStatus === 'paid' ? esc(now_minus(max(0, $daysAgo - 1))) : 'NULL') . ', ' .
            (in_array($status, ['in_progress', 'awaiting_verification', 'completed'], true) ? '1' : '0') . ', ' .
            esc($cancelReason) . ', ' . esc('10.3157') . ', ' . esc('123.8854') . ', ' .
            (float)$price . ', ' . esc(now_minus($daysAgo + 2)) . ')'
        );

        // Checklist
        $tasks = [
            'Dust and wipe all surfaces',
            'Mop floors in every room',
            'Clean the kitchen counters and sink',
            'Scrub the bathroom and toilets',
            'Take out the trash',
            'Vacuum carpets and mats',
            'Clean mirrors and glass surfaces',
            'Wipe down door handles and switches',
        ];
        foreach (array_slice($tasks, 0, $taskCount) as $i => $task) {
            q("INSERT INTO checklist_items (booking_id, task, done) VALUES (" .
                (int)$bookingId . ', ' . esc($task) . ', ' . ($i < $doneCount ? '1' : '0') . ')');
        }

        // Before / after photos
        if ($isFinished) {
            $beforeName = "demo-before-{$bookingId}.jpg";
            $afterName = "demo-after-{$bookingId}.jpg";
            if (seed_store_image('kitchen.jpg', $beforeName)) {
                q("INSERT INTO booking_photos (booking_id, stage, filepath, uploaded_at) VALUES (" .
                    (int)$bookingId . ", 'before', " . esc($beforeName) . ', ' . esc(now_minus($daysAgo, 2)) . ')');
            }
            if (seed_store_image('surface.jpg', $afterName)) {
                q("INSERT INTO booking_photos (booking_id, stage, filepath, uploaded_at) VALUES (" .
                    (int)$bookingId . ", 'after', " . esc($afterName) . ', ' . esc(now_minus($daysAgo, 1)) . ')');
            }
        }

        // Review for completed jobs
        if ($status === 'completed') {
            $rating = (int)$row[8];
            $comments = [
                5 => 'Arrived on time and finished ahead of schedule. The kitchen looks brand new.',
                4 => 'Very thorough work. Communication could have been a little faster, but the result was excellent.',
                5 => 'Used eco-friendly products as requested and left the unit spotless. Would hire again.',
                5 => 'Handled a full move-out cleaning without a single missed corner. Highly recommended.',
                4 => 'Solid deep clean. The balcony needed a second pass, which was sorted the next day.',
            ];
            q("INSERT INTO feedback (booking_id, client_id, rating, comment, created_at) VALUES (" .
                (int)$bookingId . ', ' . (int)$clientId . ', ' . $rating . ', ' .
                esc($comments[$rating] ?? 'Great service overall.') . ', ' . esc(now_minus(max(0, $daysAgo - 1), 2)) . ')');
        }

        // Chat thread between this pair
        if ($workerId) {
            $thread = $conn->query("SELECT id FROM chat_threads WHERE client_id=" . (int)$clientId . " AND worker_id=" . (int)$workerId);
            $threadId = $thread && ($t = $thread->fetch_assoc()) ? (int)$t['id'] : 0;
            if (!$threadId) {
                $threadId = qid("INSERT INTO chat_threads (client_id, worker_id, created_at, updated_at) VALUES (" .
                    (int)$clientId . ', ' . (int)$workerId . ', ' . esc(now_minus($daysAgo + 3)) . ', ' . esc(now_minus($daysAgo)) . ')');
                $script = [
                    [$clientId, 'Hi! Are you available this week for a cleaning?'],
                    [$workerId, 'Hi! Yes, I am available. Which day works best for you?'],
                    [$clientId, 'Would Saturday morning be okay? Around 9am.'],
                    [$workerId, 'Saturday 9am works. I will bring my own supplies.'],
                ];
                foreach ($script as [$sender, $msg]) {
                    q("INSERT INTO chat_messages (thread_id, sender_id, message, created_at) VALUES (" .
                        (int)$threadId . ', ' . (int)$sender . ', ' . esc($msg) . ', ' . esc(now_minus($daysAgo + 3)) . ')');
                }
            }
        }

        $created++;
    }
    out("Created {$created} bookings covering every lifecycle status.");
} else {
    out("Skipped bookings (database already has {$existingBookings}). Use --reset for a clean re-seed.");
}

/* ------------------------------------------------------------------ */
/* 4. Client-side extras                                               */
/* ------------------------------------------------------------------ */
$denise = $C['denise'];
$existingAddr = $conn->query("SELECT COUNT(*) c FROM saved_addresses WHERE client_id=" . (int)$denise);
if ($existingAddr && (int)($existingAddr->fetch_assoc()['c']) === 0) {
    $addresses = [
        ['Home', 'Mabolo, Cebu City', '10.3157', '123.8854'],
        ['Office', 'Cebu Business Park, Cebu City', '10.3120', '123.8900'],
    ];
    foreach ($addresses as [$label, $addr, $lat, $lng]) {
        q("INSERT INTO saved_addresses (client_id, label, address, address_lat, address_lng) VALUES (" .
            (int)$denise . ', ' . esc($label) . ', ' . esc($addr) . ', ' . (float)$lat . ', ' . (float)$lng . ')');
    }
    out('Added saved addresses for Denise.');
}

foreach ([['denise', 'angelica'], ['denise', 'miguel'], ['carlo', 'princess']] as [$ck, $wk]) {
    $cid = $C[$ck];
    $wid = $W[$wk];
    $dup = $conn->query("SELECT 1 FROM favorite_workers WHERE client_id=" . (int)$cid . " AND worker_id=" . (int)$wid);
    if ($dup && !$dup->num_rows) {
        q("INSERT INTO favorite_workers (client_id, worker_id) VALUES (" . (int)$cid . ', ' . (int)$wid . ')');
    }
}
out('Added favourite workers.');

foreach ($workerIds as $wid) {
    $dup = $conn->query("SELECT 1 FROM worker_portfolio WHERE worker_id=" . (int)$wid . " LIMIT 1");
    if ($dup && !$dup->num_rows) {
        $img = "demo-portfolio-{$wid}.jpg";
        if (seed_store_image('sanitize.jpg', $img, 'public')) {
            q("INSERT INTO worker_portfolio (worker_id, image, caption) VALUES (" .
                (int)$wid . ', ' . esc($img) . ', ' . esc('Deep clean of a two-bedroom family home.') . ')');
        }
    }
}
out('Added portfolio photos.');

/* ------------------------------------------------------------------ */
/* 5. Notifications, audit trail, contact inbox                        */
/* ------------------------------------------------------------------ */
$inbox = $conn->query("SELECT COUNT(*) c FROM contact_messages");
if ($inbox && (int)($inbox->fetch_assoc()['c']) === 0) {
    q("INSERT INTO contact_messages (name, email, message, is_read, created_at) VALUES (" .
        esc('Rebecca Trinidad') . ", 'rebecca.trinidad@example.com', " .
        esc('Hi, do you offer recurring weekly cleaning plans? We would need about three hours every Saturday morning. Thank you!') .
        ', 0, ' . esc(now_minus(1, 5)) . ')');
    q("INSERT INTO contact_messages (name, email, message, is_read, created_at, read_at) VALUES (" .
        esc('Jomar Enriquez') . ", 'jomar.enriquez@example.com', " .
        esc('Is it possible to book a deep clean for a 3-bedroom house in Lapu-Lapu? Would appreciate a quotation first.') .
        ', 1, ' . esc(now_minus(4)) . ', ' . esc(now_minus(3)) . ')');
    out('Added 2 contact messages to the admin inbox.');
}

$notif = $conn->query("SELECT COUNT(*) c FROM notifications WHERE user_id IN (" . implode(',', array_merge($workerIds, $clientIds)) . ")");
if ($notif && (int)($notif->fetch_assoc()['c']) < 5) {
    $messages = [
        [$W['angelica'], 'New booking request', 'Denise Abella requested a Deep Cleaning on ' . date('M j', strtotime(plus_days(2))) . '.'],
        [$W['miguel'],   'New booking request', 'Bea Flores requested a kitchen deep clean.'],
        [$C['denise'],   'Cleaner assigned',    'Angelica Ramos accepted your deep cleaning request.'],
        [$C['carlo'],    'Job in progress',     'Miguel Santos has started your kitchen deep clean.'],
        [$adminId,      'New contact message', 'Rebecca Trinidad sent a message about weekly cleaning plans.'],
    ];
    foreach ($messages as [$uid, $title, $msg]) {
        q("INSERT INTO notifications (user_id, title, message, action_url, is_read, created_at) VALUES (" .
            (int)$uid . ', ' . esc($title) . ', ' . esc($msg) . ', ' . esc('/dashboard.php') . ', 0, ' . esc(now_minus(0, 3)) . ')');
    }
    out('Added notifications.');
}

$audit = $conn->query("SELECT COUNT(*) c FROM audit_log");
if ($audit && (int)($audit->fetch_assoc()['c']) === 0) {
    $entries = [
        [null, 'System', 'demo_seeded', 'Demo dataset loaded for the deployment walkthrough.'],
        [$adminId, 'Maria Santos', 'user_registered', 'client account created for Denise Abella'],
        [$adminId, 'Maria Santos', 'worker_approved', 'worker account approved for Angelica Ramos'],
        [$adminId, 'Maria Santos', 'badge_awarded', 'Top Rated awarded to Angelica Ramos'],
    ];
    foreach ($entries as [$uid, $actor, $action, $details]) {
        q("INSERT INTO audit_log (user_id, actor_name, action, details, created_at) VALUES (" .
            ($uid ? (int)$uid : 'NULL') . ', ' . esc($actor) . ', ' . esc($action) . ', ' . esc($details) .
            ', ' . esc(now_minus(random_int(1, 6))) . ')');
    }
    out('Added audit trail entries.');
}

/* ------------------------------------------------------------------ */
/* 6. Keep ID counters ahead of what we just inserted                  */
/* ------------------------------------------------------------------ */
foreach (['client', 'worker', 'admin'] as $role) {
    q("INSERT INTO role_id_counters (role, next_id)
       SELECT " . esc($role) . ", COALESCE(MAX(id), 0) + 1 FROM users WHERE role = " . esc($role) . "
       ON DUPLICATE KEY UPDATE next_id = GREATEST(next_id, VALUES(next_id))");
}

/* ------------------------------------------------------------------ */
/* 7. Summary                                                          */
/* ------------------------------------------------------------------ */
out('');
out($line);
out("Summary");
out($line);
$rows = [];
foreach ([
    'users', 'bookings', 'checklist_items', 'booking_photos', 'feedback',
    'badges', 'chat_threads', 'chat_messages', 'notifications',
    'saved_addresses', 'favorite_workers', 'contact_messages',
] as $t) {
    $r = $conn->query("SELECT COUNT(*) c FROM `$t`");
    $rows[] = sprintf('  %-20s %d', $t, $r ? (int)($r->fetch_assoc()['c']) : 0);
}
out(implode("\n", $rows));

out('');
out("Booking status spread:");
$r = $conn->query("SELECT status, COUNT(*) c FROM bookings GROUP BY status ORDER BY status");
while ($s = $r->fetch_assoc()) {
    out(sprintf('  %-24s %d', $s['status'], (int)$s['c']));
}

out('');
out($line);
out("Demo sign-in details");
out($line);
out('  Password for every account: ' . DEMO_PASSWORD);
out('');
out(sprintf('  %-34s %s', 'Admin', $adminId));
out(sprintf('  %-34s %s', 'admin' . DEMO_DOMAIN, '(admin console)'));
foreach ($workers as $w) {
    out(sprintf('  %-34s %s', $w['email'], $w['name']));
}
foreach ($clients as $c) {
    out(sprintf('  %-34s %s', $c['email'], $c['name']));
}
out('');