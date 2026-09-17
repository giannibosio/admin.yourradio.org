#!/usr/bin/env bash
# Predispone la destinazione backup sul server Aruba.
#
# Su Aruba/Plesk di solito NON puoi creare utenti di sistema (useradd assente).
# Se hai già creato l'utente da Plesk, usa --user.
#
# Uso (utente già creato da Plesk):
#   bash aruba_server_setup.sh --user NOME_UTENTE --domain s2.yourradio.org 92.48.114.60
#
# Uso Plesk (usa owner della subscription):
#   bash aruba_server_setup.sh --plesk s2.yourradio.org 92.48.114.60
#
# Uso VPS (crea utente se manca):
#   bash aruba_server_setup.sh --vps yourradio-backup 92.48.114.60

set -euo pipefail

MODE=""
BACKUP_USER=""
DOMAIN=""
REGISTER_IP=""
BASE=""
HOME_DIR=""

usage() {
  cat <<'EOF'
Uso:
  # Utente già creato da Plesk (consigliato nel tuo caso):
  bash aruba_server_setup.sh --user NOME_UTENTE --domain s2.yourradio.org [IP_REGISTER]

  # Oppure base path esplicita:
  bash aruba_server_setup.sh --user NOME_UTENTE --base /var/www/vhosts/s2.yourradio.org/backups/yourradio.org [IP_REGISTER]

  # Owner automatico della subscription:
  bash aruba_server_setup.sh --plesk s2.yourradio.org [IP_REGISTER]

  # VPS con useradd/adduser:
  bash aruba_server_setup.sh --vps yourradio-backup [IP_REGISTER]
EOF
}

create_system_user() {
  local user="$1"
  if id "$user" >/dev/null 2>&1; then
    echo "Utente già presente: $user"
    return 0
  fi

  if command -v useradd >/dev/null 2>&1; then
    useradd -m -s /bin/bash "$user"
  elif command -v adduser >/dev/null 2>&1; then
    adduser --disabled-password --gecos "" "$user"
  else
    echo "ERRORE: né useradd né adduser disponibili." >&2
    echo "Crea l'utente da Plesk, poi: bash aruba_server_setup.sh --user NOME --domain s2.yourradio.org" >&2
    exit 1
  fi
  echo "Utente creato: $user"
}

if [[ $# -eq 0 ]]; then
  usage
  exit 1
fi

while [[ $# -gt 0 ]]; do
  case "$1" in
    --user)
      MODE="existing"
      BACKUP_USER="${2:-}"
      shift 2
      ;;
    --domain)
      DOMAIN="${2:-}"
      shift 2
      ;;
    --base)
      BASE="${2:-}"
      shift 2
      ;;
    --plesk)
      MODE="plesk"
      DOMAIN="${2:-}"
      shift 2
      ;;
    --vps)
      MODE="vps"
      BACKUP_USER="${2:-yourradio-backup}"
      shift 2
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      # IP register o legacy USER IP
      if [[ "$1" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        REGISTER_IP="$1"
        shift
      elif [[ -z "$MODE" ]]; then
        MODE="vps"
        BACKUP_USER="$1"
        shift
      else
        REGISTER_IP="$1"
        shift
      fi
      ;;
  esac
done

MODE="${MODE:-existing}"

if [[ "$MODE" == "existing" ]]; then
  [[ -n "$BACKUP_USER" ]] || { echo "Manca --user NOME"; usage; exit 1; }
  if ! id "$BACKUP_USER" >/dev/null 2>&1; then
    echo "ERRORE: utente '$BACKUP_USER' non trovato sul sistema." >&2
    echo "Verifica il nome esatto in Plesk (Users / Access to the server)." >&2
    exit 1
  fi
  HOME_DIR="$(getent passwd "$BACKUP_USER" | cut -d: -f6)"
  if [[ -z "$BASE" ]]; then
    if [[ -n "$DOMAIN" ]]; then
      BASE="/var/www/vhosts/${DOMAIN}/backups/yourradio.org"
    elif [[ -n "$HOME_DIR" ]]; then
      BASE="${HOME_DIR}/backups/yourradio.org"
    else
      echo "ERRORE: specifica --domain oppure --base" >&2
      exit 1
    fi
  fi
  AUTH_KEYS="${HOME_DIR}/.ssh/authorized_keys"

elif [[ "$MODE" == "plesk" ]]; then
  [[ -n "$DOMAIN" ]] || { usage; exit 1; }
  VHOST_ROOT="/var/www/vhosts/${DOMAIN}"
  if [[ ! -d "$VHOST_ROOT" ]]; then
    echo "ERRORE: dominio non trovato: $VHOST_ROOT" >&2
    exit 1
  fi
  BACKUP_USER="$(stat -c '%U' "$VHOST_ROOT" 2>/dev/null || true)"
  if [[ -z "$BACKUP_USER" || "$BACKUP_USER" == "root" ]]; then
    [[ -d "$VHOST_ROOT/httpdocs" ]] && BACKUP_USER="$(stat -c '%U' "$VHOST_ROOT/httpdocs")"
  fi
  [[ -n "$BACKUP_USER" ]] || { echo "ERRORE: utente Plesk non determinabile"; exit 1; }
  HOME_DIR="$(getent passwd "$BACKUP_USER" | cut -d: -f6)"
  [[ -n "$HOME_DIR" ]] || HOME_DIR="$VHOST_ROOT"
  BASE="${VHOST_ROOT}/backups/yourradio.org"
  AUTH_KEYS="${HOME_DIR}/.ssh/authorized_keys"

elif [[ "$MODE" == "vps" ]]; then
  if [[ $EUID -ne 0 ]]; then
    echo "Modalità --vps richiede root" >&2
    exit 1
  fi
  create_system_user "$BACKUP_USER"
  HOME_DIR="$(getent passwd "$BACKUP_USER" | cut -d: -f6)"
  BASE="${HOME_DIR}/backups/yourradio.org"
  AUTH_KEYS="${HOME_DIR}/.ssh/authorized_keys"
else
  usage
  exit 1
fi

# --- create dirs ---
mkdir -p "$BASE"/{latest,mysql}
PARENT="$(dirname "$BASE")"
mkdir -p "$PARENT"
# blocca accesso web se la cartella finisse sotto un docroot
if [[ "$PARENT" == *httpdocs* || "$BASE" == *httpdocs* ]]; then
  echo "ATTENZIONE: $BASE è sotto httpdocs — sposta i backup fuori dal web root." >&2
fi
echo "Require all denied" > "${PARENT}/.htaccess" 2>/dev/null || true

if id "$BACKUP_USER" >/dev/null 2>&1; then
  # "--" evita che chown interpreti nomi strani come opzioni
  chown -R -- "${BACKUP_USER}:${BACKUP_USER}" "$PARENT" 2>/dev/null \
    || chown -R -- "$BACKUP_USER" "$PARENT" || true
fi
chmod 750 "$PARENT" "$BASE" 2>/dev/null || true

# --- SSH ---
SSH_DIR="$(dirname "$AUTH_KEYS")"
mkdir -p "$SSH_DIR"
chmod 700 "$SSH_DIR"
touch "$AUTH_KEYS"
chmod 600 "$AUTH_KEYS"
chown -R -- "${BACKUP_USER}:${BACKUP_USER}" "$SSH_DIR" 2>/dev/null \
  || chown -R -- "$BACKUP_USER" "$SSH_DIR" || true

HOST_HINT="$(hostname -f 2>/dev/null || hostname)"

cat <<EOF

=== Server Aruba pronto (mode=${MODE}) ===

Utente SSH / owner : ${BACKUP_USER}
Home               : ${HOME_DIR}
Cartella backup    : ${BASE}
authorized_keys    : ${AUTH_KEYS}

1) Sul Register:
   ssh-keygen -t ed25519 -f /root/.ssh/id_ed25519_aruba_backup -N ""

2) Copia la pubkey su Aruba:
   ssh-copy-id -i /root/.ssh/id_ed25519_aruba_backup.pub ${BACKUP_USER}@${HOST_HINT}

   oppure incolla in: ${AUTH_KEYS}

3) Test dal Register:
   ssh -i /root/.ssh/id_ed25519_aruba_backup ${BACKUP_USER}@${HOST_HINT} "mkdir -p ${BASE}/test && echo OK"

4) In /etc/yourradio/backup.env sul Register:
   ARUBA_USER=${BACKUP_USER}
   ARUBA_HOST=${HOST_HINT}
   ARUBA_BASE=${BASE}
   ARUBA_SSH_KEY=/root/.ssh/id_ed25519_aruba_backup

EOF

if [[ -n "$REGISTER_IP" ]]; then
  echo "5) Hardening: in Plesk consenti SSH solo da ${REGISTER_IP}"
  echo ""
fi

echo "Fatto."
