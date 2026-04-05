# Hi.Events Flutter Check-In App

A cross-platform mobile check-in application for Hi.Events, built with Flutter.

## Features

- QR code scanning for fast attendee check-in
- Offline-first with local SQLite sync
- Multi-event support with event switching
- Real-time check-in statistics
- Search attendees by name or ticket ID

## Getting Started

### Prerequisites

- Flutter SDK >= 3.16.0
- Dart SDK >= 3.2.0
- Android Studio / Xcode

### Setup

```bash
cd mobile
flutter pub get
flutter run
```

### Configuration

Copy `.env.example` to `.env` and set your API URL:

```
API_BASE_URL=https://your-hievents-instance.com/api
```

## Architecture

- **Provider** for state management
- **Repository pattern** mirroring the backend architecture
- **Offline-first**: SQLite local cache with background sync
- **Material 3** design system with Hi.Events brand colors

## Project Structure

```
lib/
  models/       - Data models (Event, Attendee, CheckIn)
  providers/    - State providers
  repositories/ - API + local DB repositories
  screens/      - Screen widgets
  services/     - API client, auth, sync services
  widgets/      - Reusable components
```
