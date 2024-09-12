# Running Hi.Events Locally Without Docker

This guide provides instructions for setting up Hi.Events locally without using Docker, including the necessary prerequisites,
setup steps, and configuration details.

**For a faster and more reliable setup, we strongly recommend using the official [Docker setup](https://hi.events/docs/getting-started/quick-start).**

## Prerequisites

1. [Install PHP 8.2 or higher](https://www.php.net/downloads.php)
2. [Install Composer](https://getcomposer.org/download/)
3. [Install PostgreSQL](https://www.postgresql.org/download/)
4. [Install Node.js](https://nodejs.org/en)
5. [Install Yarn](https://yarnpkg.com/getting-started/install)

### PHP Extensions

Ensure the following PHP extensions are installed: `gd`, `pdo_pgsql`, `sodium`, `curl`, `intl`, `mbstring`, `xml`, `zip`, `bcmath`.

## Setup

First, fork the repository and clone it locally:

```bash
git clone https://github.com/youraccount/Hi.Events.git
```

Hi.Events has two main directories: `backend` (Laravel) and `frontend` (React).

### Backend Setup

1. **Create the `.env` file:**

   Navigate to the `backend` directory and copy the example `.env` file:

   ```bash
   cd backend
   cp .env.example .env
   ```

2. **Database Configuration:**

   Update the `.env` file with your database credentials:

   ```bash
   DB_CONNECTION=pgsql
   DB_HOST=localhost
   DB_PORT=5432
   DB_DATABASE=postgres
   DB_USERNAME=postgres
   DB_PASSWORD=postgres
   ```

   This assume the default PostgreSQL configuration. Update the values as needed.

3. **Mail Server Configuration:**

   Configure Mailtrap for email handling, or use the `log` driver to log emails locally:

   ```bash
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.mailtrap.io
   MAIL_PORT=2525
   MAIL_USERNAME=your_mailtrap_username
   MAIL_PASSWORD=your_mailtrap_password
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=your_email
   MAIL_FROM_NAME="${APP_NAME}"

   # Alternatively use just this value to log emails locally:
   MAIL_MAILER=log
   ```

4. **URL Configuration:**

   Set the application and frontend URLs in the `.env` file:

   ```bash
   APP_URL=http://localhost
   APP_PORT=8000
   APP_FRONTEND_URL=http://localhost:5678
   ```

5. **Install Dependencies:**

   Install the backend dependencies:

   ```bash
   composer install
   ```

6. **Generate Application Key:**

   Generate the Laravel application key:

   ```bash
   php artisan key:generate
   ```

7. **Run Migrations:**

   Run the database migrations:

   ```bash
   php artisan migrate
   ```

8. **Configure File Storage:**

   Set the following values in your `.env` file:

   ```bash
   FILESYSTEM_PUBLIC_DISK=public
   FILESYSTEM_PRIVATE_DISK=local
   APP_CDN_URL=http://localhost:8000/storage
   ```

   Then create a symbolic link for storage:

   ```bash
   php artisan storage:link
   ```

9. **Start the Backend Server:**

   Start the Laravel development server:

   ```bash
   php artisan serve
   ```

   Visit `http://localhost:8000` to verify the backend is running.

10. **Optional: Configure Stripe (for Payment Integration):**

If you want to test the payment functionality, configure Stripe:

```bash
STRIPE_PUBLIC_KEY=your_public_key
STRIPE_SECRET_KEY=your_secret_key
STRIPE_WEBHOOK_SECRET=your_webhook_secret
```

### Frontend Setup

#### 1. **Create the `.env` File:**

Navigate to the `frontend` directory and copy the example `.env` file:

   ```bash
   cd frontend
   cp .env.example .env
   ```

#### 2. **Configure Frontend `.env`:**

Update the `.env` file with the following settings:

   ```bash
   VITE_API_URL_CLIENT=http://localhost:8000
   VITE_API_URL_SERVER=http://localhost:8000
   VITE_FRONTEND_URL=http://localhost:5678
   VITE_STRIPE_PUBLISHABLE_KEY=pk_test_XXXXXXXX
   ```

#### 3. **Install Dependencies:**

Install the frontend dependencies:

   ```bash
   yarn install
   ```

#### 4. **Set Environment Variables:**

Set the environment variables before starting the frontend app.

- **Windows:**

  ```bash
  $env:VITE_API_URL_CLIENT="http://localhost:8000"
  $env:VITE_API_URL_SERVER="http://localhost:8000"
  $env:VITE_FRONTEND_URL="http://localhost:5678"
  $env:VITE_STRIPE_PUBLISHABLE_KEY="pk_test_XXXXXXXX"
  ```

- **Linux/Mac:**

  ```bash
  export VITE_API_URL_CLIENT="http://localhost:8000"
  export VITE_API_URL_SERVER="http://localhost:8000"
  export VITE_FRONTEND_URL="http://localhost:5678"
  export VITE_STRIPE_PUBLISHABLE_KEY="pk_test_XXXXXXXX"
  ```

#### 5. **Build and Start the Frontend:**

Run the following commands to build and start the frontend application:

   ```bash
   yarn build
   yarn start
   ```

Visit `http://localhost:5678` to view the frontend.

## Troubleshooting

1. **Composer Install Errors:**  
   Ensure the required PHP extensions are installed. Check by running:

   ```bash
   php -m
   ```

2. **Database Connection Issues:**  
   Verify the database credentials in the `.env` file and ensure the PostgreSQL service is running.

3. **Mail Server Errors:**  
   Ensure that your mail server credentials (e.g., Mailtrap) are correct or use the `log` driver for local email logging.

4. **Frontend not connecting to the backend:**  
   Ensure the API URLs are set correctly in both the frontend `.env` file and the backend `.env` file. Also, verify that environment variables are properly exported in the terminal.
