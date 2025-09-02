FROM ubuntu:22.04

# Set environment variables to avoid interactive prompts
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

# Install packages following SearXNG installation guide
RUN apt-get update && apt-get install -y \
    python3-dev python3-babel python3-venv python-is-python3 \
    uwsgi uwsgi-plugin-python3 \
    git build-essential libxslt-dev zlib1g-dev libffi-dev libssl-dev \
    nginx curl bash \
    && rm -rf /var/lib/apt/lists/*

# Create searxng user following the guide
RUN useradd --shell /bin/bash --system \
    --home-dir "/usr/local/searxng" \
    --comment 'Privacy-respecting metasearch engine' \
    searxng && \
    mkdir "/usr/local/searxng" && \
    chown -R "searxng:searxng" "/usr/local/searxng"

# Switch to searxng user and install SearXNG
USER searxng
WORKDIR /usr/local/searxng

# Clone SearXNG repository
RUN git clone "https://github.com/searxng/searxng" \
    "/usr/local/searxng/searxng-src"

# Create virtual environment
RUN python3 -m venv "/usr/local/searxng/searx-pyenv" && \
    echo ". /usr/local/searxng/searx-pyenv/bin/activate" >> "/usr/local/searxng/.profile"

# Install SearXNG dependencies
RUN . /usr/local/searxng/searx-pyenv/bin/activate && \
    pip install -U pip setuptools wheel pyyaml && \
    cd "/usr/local/searxng/searxng-src" && \
    pip install --use-pep517 --no-build-isolation -e .

# Switch back to root for configuration
USER root

# Create configuration directory and copy default settings
RUN mkdir -p "/etc/searxng" && \
    cp "/usr/local/searxng/searxng-src/utils/templates/etc/searxng/settings.yml" \
    "/etc/searxng/settings.yml"

# Create cache directory for nginx
RUN mkdir -p /tmp/nginx_cache /var/log/nginx && \
    chown -R searxng:searxng /tmp/nginx_cache /var/log/nginx

# Expose port 80 for nginx cache proxy
EXPOSE 80

# Use our startup script (will be mounted from volume)
CMD ["/etc/searxng/scripts/start-with-cache.sh"]
