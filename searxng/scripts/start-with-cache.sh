#!/bin/bash

echo "Starting SearXNG with cache layer..."

echo "Redis started for caching"

# Copy nginx config from mounted volume to expected location
cp /etc/searxng/nginx/nginx.conf /etc/nginx/nginx.conf

# Set up environment for SearXNG
export SEARXNG_SETTINGS_PATH="/etc/searxng/settings.yml"

# Activate the virtual environment and start SearXNG as searxng user
echo "Starting SearXNG..."
cd /usr/local/searxng/searxng-src
su - searxng -c "
    source /usr/local/searxng/searx-pyenv/bin/activate
    export SEARXNG_SETTINGS_PATH='/etc/searxng/settings.yml'
    cd /usr/local/searxng/searxng-src
    python searx/webapp.py
" &
SEARXNG_PID=$!
echo "SearXNG started with PID: $SEARXNG_PID"

# Wait for SearXNG to be ready
echo "Waiting for SearXNG to start..."
sleep 5

# Check if SearXNG is responding
for i in {1..30}; do
    if curl -s http://localhost:8888/ > /dev/null 2>&1; then
        echo "SearXNG is ready!"
        break
    fi
    echo "Waiting for SearXNG... (attempt $i/30)"
    sleep 1
done

# Start nginx as cache proxy
echo "Starting nginx cache proxy..."
nginx -g "daemon off;" &
NGINX_PID=$!
echo "Nginx started with PID: $NGINX_PID"

# Function to handle shutdown
cleanup() {
    echo "Shutting down..."
    kill $NGINX_PID 2>/dev/null
    kill $SEARXNG_PID 2>/dev/null
    redis-cli shutdown 2>/dev/null
    exit 0
}

# Trap signals for graceful shutdown
trap cleanup SIGTERM SIGINT

# Wait for both processes
wait $NGINX_PID $SEARXNG_PID
