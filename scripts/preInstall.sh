#set env vars
set -o allexport; source .env; set +o allexport;

KEY=$(openssl rand -base64 32)

APP_KEY="base64:$KEY"

length=32

# Generate a random string
JWT_SECRET=$(openssl rand -base64 $((length * 3/4 + 1)) | tr -d '+/' | cut -c1-$length)



cat << EOT >> ./.env

APP_KEY=${APP_KEY}
JWT_SECRET=${JWT_SECRET}
EOT


cat <<EOT > ./servers.json
{
    "Servers": {
        "1": {
            "Name": "local",
            "Group": "Servers",
            "Host": "172.17.0.1",
            "Port": 43360,
            "MaintenanceDB": "postgres",
            "SSLMode": "prefer",
            "Username": "postgres",
            "PassFile": "/pgpass"
        }
    }
}
EOT

docker-compose up -d postgres

sleep 30s;