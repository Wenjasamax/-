#!/bin/sh
set -eu

BASE_URL="${1:-http://localhost:8000}"
MASTER_ID="${2:-2}"
REQUEST_ID="${3:-2}"
COOKIE_FILE="/tmp/repair_cookie_${MASTER_ID}.txt"

# Login as master
curl -s -c "$COOKIE_FILE" -b "$COOKIE_FILE" -X POST "$BASE_URL/login" \
  -d "user_id=$MASTER_ID" >/dev/null

echo "Running race test for request $REQUEST_ID and master $MASTER_ID"

(
  curl -s -o /tmp/race_1.txt -w "REQ1 HTTP:%{http_code}\n" -c "$COOKIE_FILE" -b "$COOKIE_FILE" \
    -X POST "$BASE_URL/api/master/take" -d "request_id=$REQUEST_ID"
) &
(
  curl -s -o /tmp/race_2.txt -w "REQ2 HTTP:%{http_code}\n" -c "$COOKIE_FILE" -b "$COOKIE_FILE" \
    -X POST "$BASE_URL/api/master/take" -d "request_id=$REQUEST_ID"
) &

wait

echo "Response 1:"; cat /tmp/race_1.txt; echo
echo "Response 2:"; cat /tmp/race_2.txt; echo
