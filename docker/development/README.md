Here’s a cleaner and more polished version of your Hi.Events local development guide:

---

# Hi.Events local development with Docker

This guide walks you through setting up Hi.Events using Docker, including requirements, setup steps, configuration, and
environment variables.

## Requirements

1. **Docker** – Required for containerized development. [Install Docker](https://docs.docker.com/get-docker/)
2. **Git** – Needed to clone the
   repository. [Install Git](https://git-scm.com/book/en/v2/Getting-Started-Installing-Git)

> **Note:** This guide assumes a macOS or Linux environment. Windows users may need to adjust certain commands.

## Setup instructions

### 1. Clone the repository

Clone the Hi.Events GitHub repository:

```bash
git clone git@github.com:HiEventsDev/Hi.Events.git
```

### 2. Start the development environment

Navigate to the Docker development directory and run the startup script:

```bash
cd Hi.Events/docker/development
./start-dev.sh
```

Once running, access the app at:

- **Frontend**: [https://localhost:8443](https://localhost:8443)

## Additional configuration

Hi.Events uses environment variables for configuration. You’ll find `.env` files in:

- `frontend/.env`
- `backend/.env`

You can modify these to customize your setup.

For a full list of environment variables, see
the [Environment Variables Documentation](https://hi.events/docs/getting-started/deploying#environment-variables).
