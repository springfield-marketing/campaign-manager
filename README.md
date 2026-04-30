# Campaign Tracker

Laravel application scaffolded with:

- Laravel 13
- Laravel Breeze authentication with Blade views
- PostgreSQL as the default database
- Vite for frontend assets

## Local setup

1. Install PHP dependencies:

```bash
composer install
```

2. Install frontend dependencies:

```bash
npm install
```

3. Confirm the PostgreSQL database settings in `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=campaign_tracker
DB_USERNAME=sfre_devcoralsettle1
DB_PASSWORD=
```

4. Run the migrations:

```bash
php artisan migrate
```

5. Start the application:

```bash
php artisan serve
npm run dev
```

## Authentication

Laravel Breeze provides:

- Login
- Registration
- Password reset
- Email verification flow scaffolding

## Notes

- A local PostgreSQL database named `campaign_tracker` has been created for this project.
- This setup assumes local PostgreSQL access through the macOS user `sfre_devcoralsettle1`.
