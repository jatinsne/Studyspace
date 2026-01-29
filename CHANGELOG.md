# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2026-01-29

### 🚀 Initial Release

The first production-ready version of StudySpace OS.

### ✨ New Features

- **Core:** Implemented `SeatManager` class for conflict-free booking logic (checks date & time overlaps).
- **UI/UX:** Added "Glassmorphism" dark mode UI with Tailwind CSS.
- **Admin:** Added comprehensive `admin/` panel with 10+ modules (Expenses, Attendance, Settings, etc.).
- **Security:** Implemented role-based redirects and session security gates.
- **Notifications:** Integrated SweetAlert2 for non-blocking success/error toasts.
- **Loader:** Added global CSS page loader for smooth transitions.

### 🔧 Improvements

- **Mobile:** Made all data tables horizontally scrollable (`overflow-x-auto`) for mobile devices.
- **Printing:** Optimized `id_card.php` and `receipt.php` for A4 printing (hidden headers/footers).
- **Navigation:** Created unified `header.php` for both Student and Admin panels to ensure consistency.
- **Database:** Optimized `schema.sql` with indexing on frequently queried columns (`start_date`, `end_date`).

### 🐛 Bug Fixes

- Fixed issue where Admin was redirected to Student Login page.
- Fixed "File Not Found" errors on Dashboard Quick Actions.
- Resolved layout breaking on small screens in the User Directory.
