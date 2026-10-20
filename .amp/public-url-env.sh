url="${WEB_URL%/}"
export APP_URL="$url/api" APP_FRONTEND_URL="$url" APP_CDN_URL="$url/api/storage"
export VITE_API_URL_CLIENT="$url/api" VITE_FRONTEND_URL="$url" VITE_API_URL_SERVER=http://localhost:8000
