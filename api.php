<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$cacheFile = sys_get_temp_dir() . '/family_cal_' . md5(CALENDAR_ICS_URL) . '.json';

// Serve from cache if still fresh
if (
    CALENDAR_ICS_URL !== 'YOUR_GOOGLE_CALENDAR_ICS_URL_HERE' &&
    file_exists($cacheFile) &&
    (time() - filemtime($cacheFile)) < CACHE_DURATION
) {
    echo file_get_contents($cacheFile);
    exit;
}

if (CALENDAR_ICS_URL === 'YOUR_GOOGLE_CALENDAR_ICS_URL_HERE') {
    echo json_encode(['busyDays' => [], 'error' => 'not_configured']);
    exit;
}

$ics = @file_get_contents(CALENDAR_ICS_URL);
if ($ics === false) {
    // Return stale cache if available rather than an error
    if (file_exists($cacheFile)) {
        echo file_get_contents($cacheFile);
    } else {
        echo json_encode(['busyDays' => [], 'error' => 'fetch_failed']);
    }
    exit;
}

$busyDays = parseICS($ics);
$payload  = json_encode(['busyDays' => $busyDays, 'updated' => date('c')]);

@file_put_contents($cacheFile, $payload);
echo $payload;

// ── ICS parser ────────────────────────────────────────────────────

function parseICS(string $content): array
{
    $busy = [];

    // Unfold long lines (ICS spec: CRLF + whitespace = continuation)
    $content = preg_replace("/\r\n[ \t]/", '', $content);
    $content = preg_replace("/\n[ \t]/",   '', $content);

    preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $content, $events);

    foreach ($events[1] as $event) {
        // Skip cancelled or transparent (free) events
        if (preg_match('/\nSTATUS:CANCELLED/i',   $event)) continue;
        if (preg_match('/\nTRANSP:TRANSPARENT/i', $event)) continue;

        $start = extractDate($event, 'DTSTART');
        $end   = extractDate($event, 'DTEND');

        if (!$start) continue;
        if (!$end || $end < $start) $end = clone $start;

        // Mark every day in the event's range as busy
        $day = clone $start;
        while ($day <= $end) {
            $busy[$day->format('Y-m-d')] = true;
            $day->modify('+1 day');
        }
    }

    return array_keys($busy);
}

function extractDate(string $event, string $field): ?DateTime
{
    // All-day:  DTSTART;VALUE=DATE:20260607
    if (preg_match('/' . $field . ';VALUE=DATE:(\d{4})(\d{2})(\d{2})/i', $event, $m)) {
        $dt = new DateTime("{$m[1]}-{$m[2]}-{$m[3]}", new DateTimeZone('UTC'));
        // ICS DTEND for all-day is exclusive — step back one day
        if ($field === 'DTEND') $dt->modify('-1 day');
        return $dt;
    }

    // Timed:  DTSTART;TZID=Europe/Prague:20260607T100000
    //    or   DTSTART:20260607T100000Z
    if (preg_match('/' . $field . '(?:;[^:]+)?:(\d{4})(\d{2})(\d{2})T/i', $event, $m)) {
        return new DateTime("{$m[1]}-{$m[2]}-{$m[3]}", new DateTimeZone('UTC'));
    }

    return null;
}
