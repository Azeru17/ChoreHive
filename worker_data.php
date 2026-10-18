<?php
/**
 * Shared helper for public-facing worker showcase pages.
 * Every worker rendered on the site comes straight from the `users` table,
 * so the homepage and the full directory can never drift from real accounts.
 */

function ch_initials(string $name): string
{
    $out = '';
    foreach (preg_split('/\s+/', trim($name)) as $part) {
        if ($part !== '') {
            $out .= mb_strtoupper(mb_substr($part, 0, 1));
        }
    }
    return mb_substr($out, 0, 2) ?: '?';
}

/**
 * Admin-approved workers keyed by id, each with completed-job count,
 * rating, review count and their earned badges.
 */
function ch_workers($conn): array
{
    $res = $conn->query(
        "SELECT u.id, u.name, u.bio, u.avatar, u.address, u.city,
                (SELECT COUNT(*) FROM bookings b
                  WHERE b.worker_id = u.id AND b.status = 'completed') AS jobs,
                (SELECT COUNT(*) FROM feedback f
                   JOIN bookings b2 ON b2.id = f.booking_id
                  WHERE b2.worker_id = u.id) AS reviews,
                (SELECT ROUND(AVG(f.rating), 1) FROM feedback f
                   JOIN bookings b2 ON b2.id = f.booking_id
                  WHERE b2.worker_id = u.id) AS rating
           FROM users u
          WHERE u.role = 'worker' AND u.worker_approved = 1
          ORDER BY jobs DESC, u.name"
    );

    $workers = [];
    while ($w = $res->fetch_assoc()) {
        $w['id']      = (int)$w['id'];
        $w['jobs']    = (int)$w['jobs'];
        $w['reviews'] = (int)$w['reviews'];
        $w['badges']  = [];
        $workers[$w['id']] = $w;
    }

    if ($workers) {
        $ids = implode(',', array_map('intval', array_keys($workers)));
        $badges = $conn->query(
            "SELECT worker_id, name, description FROM badges
              WHERE worker_id IN ($ids)
              ORDER BY earned_at"
        );
        while ($b = $badges->fetch_assoc()) {
            $workers[(int)$b['worker_id']]['badges'][] = $b;
        }
    }

    return $workers;
}

/** Reputation line that never invents a rating for an unrated worker. */
function ch_worker_status(array $w): string
{
    if ($w['reviews'] > 0) {
        return '⭐ ' . number_format((float)$w['rating'], 1) . ' · ' .
               $w['reviews'] . ' review' . ($w['reviews'] === 1 ? '' : 's');
    }
    return 'New on ChoreHive · profile reviewed';
}