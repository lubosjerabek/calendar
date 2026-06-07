<?php
require_once __DIR__ . '/config.php';
$familyName = htmlspecialchars(FAMILY_NAME, ENT_QUOTES, 'UTF-8');
$configured = CALENDAR_ICS_URL !== 'YOUR_GOOGLE_CALENDAR_ICS_URL_HERE';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $familyName ?></title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<main class="card">
    <div class="lang-switch" role="group" aria-label="Language">
        <button class="lang-btn" data-lang="en">EN</button>
        <span class="lang-divider" aria-hidden="true">|</span>
        <button class="lang-btn" data-lang="cs">CS</button>
    </div>

    <h1 class="family-name"><?= $familyName ?></h1>
    <p class="subtitle" data-i18n="subtitle">When can you come for a visit?</p>

    <div class="calendar">
        <div class="cal-header">
            <button id="prev" aria-label="Previous month">&#8249;</button>
            <span id="month-year"></span>
            <button id="next" aria-label="Next month">&#8250;</button>
        </div>

        <div id="weekdays" class="weekdays" aria-hidden="true"></div>

        <div id="days" class="days" role="grid" aria-live="polite"></div>
    </div>

    <div class="legend" aria-label="Legend">
        <span class="legend-item"><span class="dot available" aria-hidden="true"></span><span data-i18n="available">Available</span></span>
        <span class="legend-item"><span class="dot busy"      aria-hidden="true"></span><span data-i18n="unavailable">Unavailable</span></span>
    </div>

    <?php if (!$configured): ?>
    <p class="notice">
        ⚙️ Open <strong>config.php</strong> and paste your Google Calendar ICS URL to get started.
    </p>
    <?php endif; ?>
</main>

<script>
(function () {
    'use strict';

    // ── Translations ────────────────────────────────────────────────

    const T = {
        en: {
            subtitle:    'When can you come for a visit?',
            available:   'Available',
            unavailable: 'Unavailable',
            months:      ['January','February','March','April','May','June',
                          'July','August','September','October','November','December'],
            days:        ['Mo','Tu','We','Th','Fr','Sa','Su'],
        },
        cs: {
            subtitle:    'Kdy nás můžete přijít navštívit?',
            available:   'Volno',
            unavailable: 'Obsazeno',
            months:      ['Leden','Únor','Březen','Duben','Květen','Červen',
                          'Červenec','Srpen','Září','Říjen','Listopad','Prosinec'],
            days:        ['Po','Út','St','Čt','Pá','So','Ne'],
        },
    };

    // ── Language detection & switching ──────────────────────────────

    function detectLang() {
        const saved = localStorage.getItem('lang');
        if (saved && T[saved]) return saved;
        const code = (navigator.language || 'en').slice(0, 2).toLowerCase();
        return T[code] ? code : 'en';
    }

    let lang = detectLang();

    function setLang(code) {
        lang = code;
        localStorage.setItem('lang', code);
        applyLang();
        renderCalendar(!loaded);
    }

    function applyLang() {
        const t = T[lang];
        document.documentElement.lang = lang;
        document.querySelector('[data-i18n="subtitle"]').textContent    = t.subtitle;
        document.querySelector('[data-i18n="available"]').textContent   = t.available;
        document.querySelector('[data-i18n="unavailable"]').textContent = t.unavailable;
        document.getElementById('weekdays').innerHTML = t.days.map(d => `<span>${d}</span>`).join('');
        document.querySelectorAll('.lang-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.lang === lang);
        });
    }

    document.querySelectorAll('.lang-btn').forEach(btn => {
        btn.addEventListener('click', () => setLang(btn.dataset.lang));
    });

    // ── State ───────────────────────────────────────────────────────

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    let viewYear  = today.getFullYear();
    let viewMonth = today.getMonth();
    let busyDays  = new Set();
    let loaded    = false;

    // ── Boot ────────────────────────────────────────────────────────

    applyLang();
    renderCalendar(true);
    loadData();

    // ── Data fetching ───────────────────────────────────────────────

    async function loadData() {
        try {
            const res  = await fetch('api.php');
            const data = await res.json();
            if (Array.isArray(data.busyDays)) busyDays = new Set(data.busyDays);
        } catch (_) {
            // silent — show everything as available if fetch fails
        }
        loaded = true;
        renderCalendar();
    }

    // ── Calendar rendering ──────────────────────────────────────────

    function renderCalendar(skeleton = false) {
        const t = T[lang];
        document.getElementById('month-year').textContent = t.months[viewMonth] + ' ' + viewYear;

        const firstDayOfMonth = new Date(viewYear, viewMonth, 1);
        // Monday-first offset: Sun→6, Mon→0, Tue→1 …
        const offset      = (firstDayOfMonth.getDay() + 6) % 7;
        const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();

        let html = '';

        // Always 6 rows × 7 cols = 42 cells so the card height never shifts
        for (let i = 0; i < offset; i++) {
            html += '<div class="day" role="gridcell" aria-hidden="true"></div>';
        }

        for (let d = 1; d <= daysInMonth; d++) {
            const date    = new Date(viewYear, viewMonth, d);
            const dateStr = localDateStr(date);
            const isPast  = date < today;
            const isToday = date.getTime() === today.getTime();

            let cls   = 'day';
            let label = String(d);

            if (skeleton) {
                cls += isPast ? ' past' : ' loading';
            } else if (isPast) {
                cls += ' past';
            } else {
                const isBusy = busyDays.has(dateStr);
                cls  += isToday ? ' today' : '';
                cls  += isBusy  ? ' busy'  : ' available';
                label = isBusy
                    ? `<span aria-label="${dateStr} ${t.unavailable}">${d}</span>`
                    : `<span aria-label="${dateStr} ${t.available}">${d}</span>`;
            }

            html += `<div class="${cls}" role="gridcell">${label}</div>`;
        }

        const trailing = 42 - offset - daysInMonth;
        for (let i = 0; i < trailing; i++) {
            html += '<div class="day" role="gridcell" aria-hidden="true"></div>';
        }

        document.getElementById('days').innerHTML = html;
    }

    // ── Navigation ──────────────────────────────────────────────────

    document.getElementById('prev').addEventListener('click', () => {
        viewMonth--;
        if (viewMonth < 0) { viewMonth = 11; viewYear--; }
        renderCalendar(!loaded);
    });

    document.getElementById('next').addEventListener('click', () => {
        viewMonth++;
        if (viewMonth > 11) { viewMonth = 0; viewYear++; }
        renderCalendar(!loaded);
    });

    // ── Helpers ─────────────────────────────────────────────────────

    function localDateStr(date) {
        return date.getFullYear() + '-'
            + String(date.getMonth() + 1).padStart(2, '0') + '-'
            + String(date.getDate()).padStart(2, '0');
    }

}());
</script>

</body>
</html>
