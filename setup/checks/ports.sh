#!/usr/bin/env bash
# Port Drupal hanya di localhost.
export PATH="$PATH:/c/Program Files/Docker/Docker/resources/bin"
p=$(docker compose port drupal 80)
case "$p" in 127.0.0.1:*) echo "port $p";; *) echo "port terbuka ke jaringan: $p"; exit 1;; esac
