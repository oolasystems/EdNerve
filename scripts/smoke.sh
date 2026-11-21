#!/usr/bin/env bash
# Production smoke check for OolaEdNerve API + web (30 seconds).
# POSIX-compatible; used by CI. Set API_BASE_URL / SMOKE_EMAIL / SMOKE_PASSWORD via env.
set -uo pipefail

API_BASE_URL="${API_BASE_URL:-https://ednerve-api.oolasystems.com/api}"
SMOKE_EMAIL="${SMOKE_EMAIL:-}"
SMOKE_PASSWORD="${SMOKE_PASSWORD:-}"

if [ -z "$SMOKE_EMAIL" ] || [ -z "$SMOKE_PASSWORD" ]; then
  printf 'SMOKE_EMAIL and SMOKE_PASSWORD are required. Do not use demo defaults.\n' >&2
  exit 2
fi

fails=0
checks=0

log_ok()   { printf '  [OK]   %s\n' "$1"; }
log_fail() { printf '  [FAIL] %s\n' "$1" >&2; fails=$((fails+1)); }
log_skip() { printf '  [SKIP] %s\n' "$1"; }

check_code() {
  local label="$1"; local method="${2:-GET}"; local url="$3"; local expected="${4:-200}"; local auth="${5:-}"
  local code
  if [ -n "$auth" ]; then
    code=$(curl -sS -o /tmp/_smoke_body -w '%{http_code}' -X "$method" "$url" \
      -H "Authorization: Bearer $auth" -H 'Accept: application/json' --max-time 20)
  else
    code=$(curl -sS -o /tmp/_smoke_body -w '%{http_code}' -X "$method" "$url" \
      -H 'Accept: application/json' --max-time 20)
  fi
  checks=$((checks+1))
  if [ "$code" = "$expected" ]; then log_ok "$label (HTTP $code)"; else log_fail "$label (HTTP $code, wanted $expected)"; fi
}

echo "OolaEdNerve — production smoke check"
echo "API: $API_BASE_URL"
echo

check_code "health"            GET "$API_BASE_URL/health" 200

TOKEN=$(curl -sS --max-time 20 -X POST "$API_BASE_URL/auth/login" \
  -H 'Content-Type: application/json' \
  -d "{\"email\":\"$SMOKE_EMAIL\",\"password\":\"$SMOKE_PASSWORD\"}" \
  | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
checks=$((checks+1))
if [ -n "$TOKEN" ]; then log_ok "login as $SMOKE_EMAIL"; else log_fail "login as $SMOKE_EMAIL"; fi

if [ -n "$TOKEN" ]; then
  check_code "/institution"     GET "$API_BASE_URL/institution"     200 "$TOKEN"
  check_code "/faculties"       GET "$API_BASE_URL/faculties"       200 "$TOKEN"
  check_code "/schools"         GET "$API_BASE_URL/schools"         200 "$TOKEN"
  check_code "/academic-units"  GET "$API_BASE_URL/academic-units"  200 "$TOKEN"
  check_code "/admissions/intakes" GET "$API_BASE_URL/admissions/intakes" 200 "$TOKEN"
fi

check_code "/public/admissions/amu" GET "$API_BASE_URL/public/admissions/amu" 200

echo
if [ "$fails" -ne 0 ]; then
  echo "SMOKE FAIL ($fails of $checks failed)" >&2
  exit 1
fi
echo "SMOKE PASS ($checks checks)"
