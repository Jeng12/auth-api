#!/usr/bin/env bash
# Smoke-test the deployed auth-api end-to-end.
# Usage:
#   bash scripts/smoke-test.sh
#
# What it does:
#   1. GET /up                      -> 200
#   2. POST /api/register           -> 201, captures token
#   3. GET /api/me                  -> 200 (unverified user is still allowed)
#   4. GET /api/account             -> 403 (verified middleware blocks)
#   5. Prompts you to paste the OTP (from the Resend inbox or Supabase)
#   6. POST /api/email/verify       -> 200
#   7. GET /api/account             -> 200 (now verified)
#   8. POST /api/login              -> 200, new token
#   9. POST /api/logout             -> 200
#  10. GET /api/me with stale token -> 401

set -e

BASE="${BASE:-https://auth-api-two-theta.vercel.app}"
EMAIL="${EMAIL:-smoketest+$(date +%s)@example.com}"
PASS="${PASS:-Password1!}"

JH='Content-Type: application/json'
JA='Accept: application/json'

c() { curl -s -w "\n  HTTP %{http_code}\n" "$@"; }

echo "Base URL : $BASE"
echo "Email    : $EMAIL"
echo

echo ">>> 1) GET /up"
curl -s -o /dev/null -w "  HTTP %{http_code}\n" "$BASE/up"

echo
echo ">>> 2) POST /api/register"
REG=$(curl -s -w "\n%{http_code}" -X POST -H "$JH" -H "$JA" "$BASE/api/register" \
  -d "{\"name\":\"Smoke\",\"email\":\"$EMAIL\",\"password\":\"$PASS\",\"password_confirmation\":\"$PASS\"}")
echo "$REG"
TOKEN=$(echo "$REG" | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
[ -z "$TOKEN" ] && { echo "  !! could not extract token; aborting"; exit 1; }
echo "  token=${TOKEN:0:24}..."

echo
echo ">>> 3) GET /api/me (no verify yet)"
c -H "Authorization: Bearer $TOKEN" -H "$JA" "$BASE/api/me"

echo
echo ">>> 4) GET /api/account (expect 403)"
c -H "Authorization: Bearer $TOKEN" -H "$JA" "$BASE/api/account"

echo
echo "Paste the 6-digit OTP (from Resend inbox or Supabase users.otp):"
read -r OTP

echo
echo ">>> 5) POST /api/email/verify"
c -X POST -H "$JH" -H "$JA" -H "Authorization: Bearer $TOKEN" \
  "$BASE/api/email/verify" -d "{\"otp\":\"$OTP\"}"

echo
echo ">>> 6) GET /api/account (expect 200)"
c -H "Authorization: Bearer $TOKEN" -H "$JA" "$BASE/api/account"

echo
echo ">>> 7) POST /api/login (fresh token)"
LOG=$(curl -s -w "\n%{http_code}" -X POST -H "$JH" -H "$JA" "$BASE/api/login" \
  -d "{\"email\":\"$EMAIL\",\"password\":\"$PASS\"}")
echo "$LOG"
TOKEN2=$(echo "$LOG" | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')

echo
echo ">>> 8) POST /api/logout"
c -X POST -H "$JA" -H "Authorization: Bearer $TOKEN2" "$BASE/api/logout"

echo
echo ">>> 9) GET /api/me with revoked token (expect 401)"
curl -s -o /dev/null -w "  HTTP %{http_code}\n" \
  -H "Authorization: Bearer $TOKEN2" -H "$JA" "$BASE/api/me"

echo
echo "Smoke test done. Inspect the per-step HTTP codes above."
