# Hi.Events All-in-One Docker Image

The all-in-one Docker image runs both the frontend and backend services in a single container. While it can be used in 
production, the recommended approach for production is to run the frontend and backend separately for better scalability and security.

The provided docker-compose.yml file is meant for development and testing purposes. For production, you should use
the [Docker image](https://hub.docker.com/r/daveearley/hi.events-all-in-one), or create your own Docker compose file with the 
necessary [configurations for production](https://hi.events/docs/getting-started/deploying#configuring-environment-variables).

## Quick Start with Docker

### Step 1: Clone the Repository

```bash
git clone git@github.com:HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one
```

### Step 2: Generate the `APP_KEY` and `JWT_SECRET`

Generate the keys using the following commands:

#### Unix/Linux/MacOS/WSL
```bash
echo base64:$(openssl rand -base64 32)  # For APP_KEY
openssl rand -base64 32                 # For JWT_SECRET
```

#### Windows (Command Prompt):
```cmd
for /f "tokens=*" %i in ('openssl rand -base64 32') do @echo APP_KEY=base64:%i
for /f "tokens=*" %i in ('openssl rand -base64 32') do @echo JWT_SECRET=%i
```

#### Windows (PowerShell):
```powershell
"base64:$([Convert]::ToBase64String([System.Security.Cryptography.RandomNumberGenerator]::GetBytes(32)))"  # For APP_KEY
[Convert]::ToBase64String([System.Security.Cryptography.RandomNumberGenerator]::GetBytes(32))  # For JWT_SECRET
```

### Step 3: Update the `.env` File

Update the `.env` file located in `./docker/all-in-one/.env` with the generated `APP_KEY` and `JWT_SECRET`:

```plaintext
APP_KEY=your_generated_app_key
JWT_SECRET=your_generated_jwt_secret
```

### Step 4: Start the Docker Containers

```bash
docker-compose up -d
```

### Step 5: Create an Account

Visit [http://localhost:8123/auth/register](http://localhost:8123/auth/register) to create an account.

---

**Production Note:**  
For production, ensure you generate unique `APP_KEY` and `JWT_SECRET` for each environment and never hardcode sensitive values.
