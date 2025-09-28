#!/usr/bin/env bash
set -euo pipefail

HOST_ENTRY="127.0.0.1 vendingmachine.test"
HOSTS_FILE="/etc/hosts"

if grep -q "vendingmachine.test" "$HOSTS_FILE"; then
  echo "Entry already present in $HOSTS_FILE"
  exit 0
fi

echo "Adding host entry to $HOSTS_FILE (requires sudo)..."
echo "$HOST_ENTRY" | sudo tee -a "$HOSTS_FILE" > /dev/null

echo "Added: $HOST_ENTRY"
