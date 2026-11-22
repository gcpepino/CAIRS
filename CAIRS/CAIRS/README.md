# CAIRS - Community Assistance and Incident Reporting System

A PHP + MySQL web app for a barangay: residents report incidents (with a map pin and photo) or send an
emergency **SOS**, admins assign volunteers and track progress, and everyone gets notifications.

## Roles
- **Resident** - report an incident, send an SOS, follow progress, notifications, profile.
- **Volunteer** - sees assigned reports, posts updates, marks them resolved.
- **Admin** - dashboard with live SOS board, reports (filter, CSV export, print), residents, volunteers,
  announcements, categories, analytics + incident map, settings (hotlines, SMS, map center).

## Setup (Laragon or XAMPP)
1. Install [Laragon](https://laragon.org) or XAMPP (PHP 8+, MySQL/MariaDB). Start Apache and MySQL.
2. Put this project in the web root in a folder named exactly **`CAIRS`**
   (`C:\laragon\www\CAIRS` or `C:\xampp\htdocs\CAIRS`). If you use another folder name,
   change `BASE_URL` at the top of `config/database.php`.
3. Import `database/cairs.sql` (phpMyAdmin > Import). It creates the `cairs` database and demo accounts.
   The newer tables/columns (announcements, SOS, login attempts) are created automatically on first load.
4. Database login is `root` with an empty password. Change it in `config/database.php` if yours differs.
5. Open <http://localhost/CAIRS>.

## Demo accounts
| Role      | Email                  | Password     |
|-----------|------------------------|--------------|
| Admin     | admin@cairs.local      | admin123     |
| Volunteer | volunteer@cairs.local  | volunteer123 |
| Resident  | resident@cairs.local   | resident123  |

Change these before using the system for real.

## Notes
- The SOS page and "Use my current location" need GPS permission. Browsers only allow that on
  `localhost` or HTTPS; on plain `http://192.168.x.x` it falls back to the resident's home address.
- `assets/uploads/` must be writable by the web server. Uploaded photos are not committed to git.
- SMS alerts are optional (Semaphore API key, set in Admin > Settings).
- Five wrong logins from the same email or IP lock logging in for 10 minutes.
