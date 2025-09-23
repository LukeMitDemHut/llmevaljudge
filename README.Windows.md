# 🚀 Windows User Guide – Running the Dev Environment Without ./dev

This project provides a ./dev script (written in bash) to simplify working with the Docker-based development environment.
Unfortunately, this script does not work on Windows by default unless you are using WSL or Git Bash.

This guide explains how to run all the commands manually in PowerShell or Command Prompt, so you can work with the environment without the script.

📋 Prerequisites

- Docker Desktop for Windows installed and running.
- Ensure the docker compose command works (try `docker compose version`).
- If only `docker-compose` works, replace `docker compose` with `docker-compose` in the commands below.
- PowerShell (recommended) or Command Prompt.
- This project’s repository cloned onto your machine.

## 🐳 Container Management

Equivalent to `./dev up`, `./dev down`, `./dev restart`:

Start containers (detached):

```powershell
docker compose up -d
```

Stop containers:

```powershell
docker compose down
```

Restart (pull, rebuild, and start):

```powershell
docker compose down
docker compose pull
docker compose build
docker compose up -d
```

## 💻 Working Inside the App Container

The main web container is called `judge_web`. Most commands run inside it.

Open a bash shell in the container (like `./dev` with no args):

```powershell
docker exec -it judge_web bash
```

## ⚡ Symfony, PHP & Tests

Symfony console command (e.g., cache clear):

```powershell
docker exec -it judge_web php bin/console cache:clear
```

Run PHPUnit tests:

```powershell
docker exec -it judge_web php bin/phpunit
```

## 📦 Dependency Management

Install PHP & Node dependencies:

```powershell
docker exec -it judge_web composer install
docker exec -it judge_web npm install
```

## 🎨 Frontend Tasks

Build assets:

```powershell
docker exec -it judge_web npm run build
```

Run dev server:

```powershell
docker exec -it judge_web npm run dev-server
```

Run watcher:

```powershell
docker exec -it judge_web npm run watch
```

## 🛠 Database Setup

Equivalent to the `./dev setup` script:

```powershell
# Start containers
docker compose up -d

# Install dependencies
docker exec -it judge_web composer install
docker exec -it judge_web npm install

# Update database schema
docker exec -it judge_web php bin/console doctrine:schema:update --force

# Restart containers so Messenger can start properly
docker compose up -d

# Build frontend assets
docker exec -it judge_web npm run build
```

## 🧭 Command Reference

Here’s a quick map of `./dev` to manual commands:

| ./dev ...     | Equivalent Windows command                                                                 |
| ------------- | ------------------------------------------------------------------------------------------ |
| ./dev         | docker exec -it judge_web bash                                                             |
| ./dev up      | docker compose up -d                                                                       |
| ./dev down    | docker compose down                                                                        |
| ./dev restart | docker compose down && docker compose pull && docker compose build && docker compose up -d |
| ./dev console | docker exec -it judge_web php bin/console <args>                                           |
| ./dev phpunit | docker exec -it judge_web php bin/phpunit <args>                                           |
| ./dev test    | docker exec -it judge_web php bin/phpunit <args>                                           |
| ./dev build   | docker exec -it judge_web npm run build                                                    |
| ./dev dev     | docker exec -it judge_web npm run dev-server                                               |
| ./dev watch   | docker exec -it judge_web npm run watch                                                    |
| ./dev install | docker exec -it judge_web composer install && docker exec -it judge_web npm install        |
| ./dev setup   | See Database Setup section                                                                 |
