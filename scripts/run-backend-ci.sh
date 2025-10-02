#!/usr/bin/env bash
set -euo pipefail

# Mirror the backend GitHub Actions workflow locally so branches can be validated before pushing.
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="$REPO_ROOT/backend"
ENV_FILE="$BACKEND_DIR/.env"
ENV_DIST_FILE="$BACKEND_DIR/.env.dist"
ROOT_ENV_DIST_FILE="$REPO_ROOT/.env.dist"

if [[ ! -d "$BACKEND_DIR" ]]; then
  echo "Backend directory not found at $BACKEND_DIR" >&2
  exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
  if [[ -f "$ENV_DIST_FILE" ]]; then
    echo "Creating backend .env from backend/.env.dist"
    cp "$ENV_DIST_FILE" "$ENV_FILE"
  elif [[ -f "$ROOT_ENV_DIST_FILE" ]]; then
    echo "Creating backend .env from repository .env.dist"
    cp "$ROOT_ENV_DIST_FILE" "$ENV_FILE"
  else
    echo "No template .env.dist found for backend" >&2
    exit 1
  fi
fi

if ! grep -q '^DEFAULT_URI=' "$ENV_FILE"; then
  echo "DEFAULT_URI=http://localhost" >> "$ENV_FILE"
fi

if grep -q '^APP_ENV=' "$ENV_FILE"; then
  sed -i 's/^APP_ENV=.*/APP_ENV=test/' "$ENV_FILE"
else
  echo "APP_ENV=test" >> "$ENV_FILE"
fi

if ! command -v composer >/dev/null 2>&1; then
  echo "composer is required but was not found in PATH" >&2
  exit 1
fi

run_step() {
  local description="$1"
  shift
  echo
  echo "==> $description"
  "$@"
}

cd "$BACKEND_DIR"

echo "Running backend CI checks..."

run_step "Validating composer.json" composer validate --strict
run_step "Installing dependencies" composer install --no-interaction --no-progress --prefer-dist
run_step "Running code style check" composer run lint
run_step "Running static analysis" composer run phpstan
run_step "Running PHPUnit" env APP_ENV=test APP_DEBUG=0 composer run test

echo
echo "Backend CI checks completed successfully."
