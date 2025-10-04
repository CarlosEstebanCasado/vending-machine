#!/usr/bin/env bash
set -euo pipefail

ENV_FILE=${1:-.env}
TTL_DEFAULT=3600
ISSUER_DEFAULT="vending-machine"

if ! command -v openssl >/dev/null 2>&1; then
  echo "openssl is required to generate secrets" >&2
  exit 1
fi

if [ ! -f "$ENV_FILE" ]; then
  echo "Environment file '$ENV_FILE' not found" >&2
  exit 1
fi

SECRET=$(openssl rand -base64 48 | tr -d '\n')

update_var() {
  local var_name=$1
  local value=$2
  local file=$3

  if grep -q "^${var_name}=" "$file"; then
    sed -i.bak "s|^${var_name}=.*|${var_name}=${value}|" "$file"
    rm -f "${file}.bak"
  else
    echo "${var_name}=${value}" >> "$file"
  fi
}

update_var "ADMIN_JWT_SECRET" "$SECRET" "$ENV_FILE"

if ! grep -q '^ADMIN_JWT_TTL=' "$ENV_FILE"; then
  update_var "ADMIN_JWT_TTL" "${ADMIN_JWT_TTL:-$TTL_DEFAULT}" "$ENV_FILE"
fi

if ! grep -q '^ADMIN_JWT_ISSUER=' "$ENV_FILE"; then
  update_var "ADMIN_JWT_ISSUER" "${ADMIN_JWT_ISSUER:-$ISSUER_DEFAULT}" "$ENV_FILE"
fi

echo "Admin JWT secret and defaults updated in $ENV_FILE"

echo "Summary:" 
echo "  ADMIN_JWT_SECRET=<generated>"
echo "  ADMIN_JWT_TTL=$(grep '^ADMIN_JWT_TTL=' "$ENV_FILE" | cut -d'=' -f2)"
echo "  ADMIN_JWT_ISSUER=$(grep '^ADMIN_JWT_ISSUER=' "$ENV_FILE" | cut -d'=' -f2)"
