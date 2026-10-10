<<<<<<< HEAD
# NewMyHospital

## Overview
- PHP 8+ (tested on 8.1) with PDO + MySQLi-compatible database.
- Frontend located under `assets/` and `includes/`; admin tools in `admin/`.

## Database setup
- Host: `localhost`, User: `sa`, Password: `sa`, Database: `myhospitaldb`.
- The PDO helper at `config/db_connect.php` will auto-create `myhospitaldb` (utf8mb4 / unicode_ci) before reconnecting. Keep the file tracked so the creation logic and settings stay versioned.
- Adjust the credentials in `config/db_connect.php` if you deploy elsewhere, then restart Apache.
- Run `admin/update_db.php` to seed tables after any schema change.

## Important habits
- Uploads for popup management are stored under `assets/images/popup/`; `.gitignore` keeps large or regenerated assets out of Git.
- Keep session files and temporary logs under `/tmp` to avoid tracking environment-specific data.
- The homepage banner slider uses `banners` rows (table auto-created when you visit `admin/banner.php`). Upload 1600×600 art there, optionally add a link, and toggle `Active` to display the slide ahead of the hero section. Each image is kept under `assets/images/banners/`.

## Deployment checklist
1. Confirm `config/db_connect.php` contains the right credentials for the target server.
2. Make sure web root (C:\xampp\htdocs\newmyhospital) is served with PHP enabled.
3. Clear browser storage/cache when testing popup behaviors (uses `localStorage.hide_popup`).
=======
# myhospital
เว็บไซต์โรงพยาบาลใหม่
>>>>>>> 5cc5d0e5101d1097419c25bc5eb901c5d678b934
