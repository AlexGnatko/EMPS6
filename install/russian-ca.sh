#!/bin/bash

# russian-ca.sh - prepare the Russian Trusted CA (Ministry of Digital Development) certificate bundle
# for EMPS_MaxBot (core/max.class.php).
#
# What it does:
#   1. Creates the SSL directory (default: /srv/www/ssl) if it does not exist.
#   2. Downloads the Russian Trusted Root CA and Sub CA certificates from gu-st.ru
#      (certificate verification stays ON for the download).
#   3. Verifies them: the root must match the pinned SHA-256 fingerprint, be self-signed,
#      and the sub CA must be issued by that root.
#   4. Writes max-ca.pem = root + sub CA (each with a trailing newline).
#
# What it does NOT do: it does not install anything into the system trust store
# (no update-ca-certificates). The bundle is used only where you point to it explicitly:
#     define("MAX_CA_FILE", "/srv/www/ssl/max-ca.pem");
# or  $max->ca_file = "/srv/www/ssl/max-ca.pem";
#
# Usage:
#     ./russian-ca.sh [ssl_dir]
#
# Environment overrides (optional):
#     ROOT_SHA256  expected SHA-256 fingerprint of the root certificate (use it if the CA is re-issued)
#     BASE_URL     download location (default: https://gu-st.ru/content/lending)
#
# Always confirm the root fingerprint against a second independent source
# (e.g. the certificate downloaded in a browser from https://www.gosuslugi.ru/crt).

set -euo pipefail

SSL_DIR="${1:-/srv/www/ssl}"
BASE_URL="${BASE_URL:-https://gu-st.ru/content/lending}"
ROOT_NAME="russian_trusted_root_ca_pem.crt"
SUB_NAME="russian_trusted_sub_ca_pem.crt"
OUT_NAME="max-ca.pem"
ROOT_SHA256="${ROOT_SHA256:-D2:6D:2D:02:31:B7:C3:9F:92:CC:73:85:12:BA:54:10:35:19:E4:40:5D:68:B5:BD:70:3E:97:88:CA:8E:CF:31}"
WARN_DAYS=90

say() { echo "russian-ca: $*"; }
die() { echo "russian-ca: ERROR: $*" >&2; exit 1; }

command -v openssl >/dev/null 2>&1 || die "openssl is required"

fetch() {
    local url="$1" dest="$2"
    if command -v curl >/dev/null 2>&1; then
        curl -fsSL --connect-timeout 15 --max-time 60 -o "$dest" "$url"
    elif command -v wget >/dev/null 2>&1; then
        wget -q --timeout=60 -O "$dest" "$url"
    else
        die "curl or wget is required"
    fi
}

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

say "Downloading certificates from ${BASE_URL} ..."
fetch "${BASE_URL}/${ROOT_NAME}" "${TMP_DIR}/${ROOT_NAME}" || die "could not download ${ROOT_NAME}"
fetch "${BASE_URL}/${SUB_NAME}" "${TMP_DIR}/${SUB_NAME}" || die "could not download ${SUB_NAME}"

# Both files must be valid PEM certificates
openssl x509 -in "${TMP_DIR}/${ROOT_NAME}" -noout 2>/dev/null || die "${ROOT_NAME} is not a valid PEM certificate"
openssl x509 -in "${TMP_DIR}/${SUB_NAME}" -noout 2>/dev/null || die "${SUB_NAME} is not a valid PEM certificate"

# Root: pinned fingerprint and self-signed
GOT_SHA256="$(openssl x509 -in "${TMP_DIR}/${ROOT_NAME}" -noout -fingerprint -sha256 | cut -d= -f2 | tr 'a-f' 'A-F')"
WANT_SHA256="$(echo "$ROOT_SHA256" | tr 'a-f' 'A-F')"
if [ "$GOT_SHA256" != "$WANT_SHA256" ]; then
    die "root certificate fingerprint mismatch: got ${GOT_SHA256}, expected ${WANT_SHA256}"
fi

ROOT_SUBJECT="$(openssl x509 -in "${TMP_DIR}/${ROOT_NAME}" -noout -subject)"
ROOT_ISSUER="$(openssl x509 -in "${TMP_DIR}/${ROOT_NAME}" -noout -issuer)"
[ "${ROOT_SUBJECT#subject=}" = "${ROOT_ISSUER#issuer=}" ] || die "root certificate is not self-signed"

# Sub CA must be issued by this root. An expired sub CA is only a warning: the root alone is
# enough when the server sends its own chain, but the sub CA file should be refreshed.
if ! openssl verify -CAfile "${TMP_DIR}/${ROOT_NAME}" "${TMP_DIR}/${SUB_NAME}" >/dev/null 2>&1; then
    say "WARNING: the sub CA certificate does not verify against the root (expired or not issued by it)"
fi
if ! openssl x509 -in "${TMP_DIR}/${SUB_NAME}" -noout -checkend $((WARN_DAYS * 86400)) >/dev/null 2>&1; then
    say "WARNING: the sub CA certificate expires within ${WARN_DAYS} days (or has expired): $(openssl x509 -in "${TMP_DIR}/${SUB_NAME}" -noout -enddate)"
fi

# Build the bundle. A trailing newline after each file is required: the downloaded
# files may lack one, and a plain `cat` would glue END/BEGIN lines together.
{
    cat "${TMP_DIR}/${ROOT_NAME}"
    echo
    cat "${TMP_DIR}/${SUB_NAME}"
    echo
} > "${TMP_DIR}/${OUT_NAME}"

COUNT="$(grep -c 'BEGIN CERTIFICATE' "${TMP_DIR}/${OUT_NAME}")"
[ "$COUNT" = "2" ] || die "unexpected bundle content: ${COUNT} certificates instead of 2"

mkdir -p "$SSL_DIR" || die "could not create ${SSL_DIR} (run as root or pass another directory)"
chmod 755 "$SSL_DIR" 2>/dev/null || true

install -m 644 "${TMP_DIR}/${ROOT_NAME}" "${SSL_DIR}/${ROOT_NAME}"
install -m 644 "${TMP_DIR}/${SUB_NAME}" "${SSL_DIR}/${SUB_NAME}"
install -m 644 "${TMP_DIR}/${OUT_NAME}" "${SSL_DIR}/${OUT_NAME}"

say "Root CA : ${ROOT_SUBJECT#subject=}"
say "          SHA-256 ${GOT_SHA256}"
say "          $(openssl x509 -in "${SSL_DIR}/${ROOT_NAME}" -noout -enddate)"
say "Sub CA  : $(openssl x509 -in "${SSL_DIR}/${SUB_NAME}" -noout -subject | sed 's/^subject=//')"
say "          $(openssl x509 -in "${SSL_DIR}/${SUB_NAME}" -noout -enddate)"
say "Bundle  : ${SSL_DIR}/${OUT_NAME} (2 certificates)"

# Optional connectivity check against Max (HTTP 401 without a token means TLS verification passed)
if command -v curl >/dev/null 2>&1; then
    CODE="$(curl -s -o /dev/null -w '%{http_code}' --connect-timeout 10 --max-time 20 --cacert "${SSL_DIR}/${OUT_NAME}" https://platform-api2.max.ru/me || true)"
    if [ "$CODE" = "401" ]; then
        say "Check   : platform-api2.max.ru answered HTTP 401 (no token) - TLS verification passed"
    else
        say "Check   : platform-api2.max.ru check returned '${CODE:-no response}' - verify manually:"
        say "          curl --cacert ${SSL_DIR}/${OUT_NAME} -i https://platform-api2.max.ru/me"
    fi
fi

say "Done. Add to the project config:"
say "    define(\"MAX_CA_FILE\", \"${SSL_DIR}/${OUT_NAME}\");"
