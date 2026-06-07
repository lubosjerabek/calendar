# Family Calendar

A single-page app for shared PHP hosting that shows guests when they can come for a visit — green days are free, red days are busy.

Data is pulled from a Google Calendar ICS feed. No database, no framework, no build step.

## How it works

- `api.php` fetches your Google Calendar's public ICS feed and caches the result for 1 hour
- `index.php` renders a month calendar in the browser using vanilla JS
- Busy days = days that have any event on your calendar
- Free days = days with no events

## Setup

### 1. Make your Google Calendar public

Google Calendar → **⚙ Settings** → click your calendar → **"Integrate calendar"** → tick **"Make available to public"** → copy the **"Public address in iCal format"** URL.

### 2. Add a family photo

Place a `photo.jpg` in the project root. Landscape orientation works best.

### 3. Configure GitHub Secrets

Go to your repo → **Settings → Secrets and variables → Actions** and add:

| Secret | Example |
|---|---|
| `CALENDAR_ICS_URL` | `https://calendar.google.com/calendar/ical/…/basic.ics` |
| `FAMILY_NAME` | `The Jeřábeks` |
| `FTP_HOST` | `ftp.yourdomain.com` |
| `FTP_USER` | `yourftpuser` |
| `FTP_PASS` | `yourftppassword` |
| `FTP_SERVER_DIR` | `/public_html/calendar/` (must end with `/`) |

### 4. Deploy

Push to `main` — GitHub Actions uploads everything to your host via FTP automatically. You can also trigger a deploy manually from the **Actions** tab.

## Local development

Edit `config.php` directly with your ICS URL and family name for local testing. This file is only used locally; CI overwrites it from secrets before deploying.
