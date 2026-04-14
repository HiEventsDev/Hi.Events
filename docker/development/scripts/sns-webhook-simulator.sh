#!/usr/bin/env bash
#
# sns-webhook-simulator.sh — Simulate AWS SNS notifications against the
# local SES webhook for development debugging.
#
# This tool sends unsigned SNS payloads to the webhook endpoint to test
# bounce, complaint, and delivery handling without needing real AWS
# infrastructure (no SNS topics, no SES Configuration Sets, no ngrok).
#
# It also provides helpers to inspect outgoing message status and manage
# the email suppression list during development.
#
# ENVIRONMENT
#   Requires the docker/development environment running with these
#   settings in docker/development/.env:
#
#     AWS_SNS_VERIFY_SIGNATURE=false   (skip SNS signature check)
#     SES_SUPPRESSION_ENABLED=true     (enable suppression logic)
#
#   This tool is NOT intended for production use. In production, SNS
#   signature verification is enabled, so unsigned payloads are rejected.
#   To test the full SES pipeline in production, send to SES simulator
#   addresses (e.g. bounce@simulator.amazonses.com) which trigger real
#   signed SNS notifications through the webhook.
#
# STATUS TRANSITIONS
#   delivery:  SENT → DELIVERED  (only from SENT)
#   bounce:    SENT or DELIVERED → BOUNCED
#   complaint: SENT or DELIVERED → BOUNCED (also creates suppression)
#
# USAGE
#   ./sns-webhook-simulator.sh list
#   ./sns-webhook-simulator.sh list-suppressions
#   ./sns-webhook-simulator.sh bounce marketing:10
#   ./sns-webhook-simulator.sh bounce transaction:2
#   ./sns-webhook-simulator.sh bounce user@example.com [ses_msg_id]
#   ./sns-webhook-simulator.sh bounce-transient marketing:10
#   ./sns-webhook-simulator.sh complaint marketing:10
#   ./sns-webhook-simulator.sh delivery marketing:10
#   ./sns-webhook-simulator.sh unsuppress <id|email>
#   ./sns-webhook-simulator.sh subscribe
#

set -euo pipefail

WEBHOOK_URL="${SES_TEST_URL:-https://localhost:8443/api/public/webhooks/ses}"
DOCKER_COMPOSE_DIR="$(cd "$(dirname "$0")/.." && pwd)"
DOCKER_COMPOSE="docker compose -f $DOCKER_COMPOSE_DIR/docker-compose.dev.yml"
DB_NAME="${DB_DATABASE:-backend}"
DB_USER="${DB_USERNAME:-postgres}"

# --- helpers ---

generate_uuid() {
    uuidgen 2>/dev/null || cat /proc/sys/kernel/random/uuid 2>/dev/null || python3 -c "import uuid; print(uuid.uuid4())"
}

timestamp() {
    date -u +"%Y-%m-%dT%H:%M:%S.000Z"
}

db_query() {
    local sql="$1"
    $DOCKER_COMPOSE exec -T pgsql psql -U "$DB_USER" -d "$DB_NAME" -c "$sql"
}

db_query_value() {
    local sql="$1"
    $DOCKER_COMPOSE exec -T pgsql psql -U "$DB_USER" -d "$DB_NAME" -t -A -c "$sql"
}

# Resolve a target argument into email + ses_message_id.
# Accepts either:
#   marketing:ID  or  transaction:ID  — looks up from DB
#   email@addr [ses_message_id]       — uses as-is
resolve_target() {
    local arg1="$1"
    local arg2="${2:-}"

    TARGET_TABLE=""
    TARGET_ROW_ID=""
    TARGET_HAS_SES_ID=true

    if [[ "$arg1" == marketing:* ]]; then
        TARGET_TABLE="outgoing_messages"
        TARGET_ROW_ID="${arg1#marketing:}"
        local result
        result=$(db_query_value "SELECT recipient || '|' || COALESCE(ses_message_id, '') || '|' || status FROM outgoing_messages WHERE id = $TARGET_ROW_ID AND deleted_at IS NULL")
        result=$(echo "$result" | tr -d '[:space:]')
        if [ -z "$result" ]; then
            echo "ERROR: No marketing message found with id=$TARGET_ROW_ID" >&2
            exit 1
        fi
        TARGET_EMAIL=$(echo "$result" | cut -d'|' -f1)
        TARGET_SES_MSG_ID=$(echo "$result" | cut -d'|' -f2)
        TARGET_STATUS_BEFORE=$(echo "$result" | cut -d'|' -f3)
        if [ -z "$TARGET_SES_MSG_ID" ]; then
            TARGET_SES_MSG_ID=$(generate_uuid)
            TARGET_HAS_SES_ID=false
            echo "WARNING: marketing:$TARGET_ROW_ID has no ses_message_id — using generated UUID (status won't update)" >&2
        fi
        echo "Resolved marketing:$TARGET_ROW_ID → email=$TARGET_EMAIL, status=$TARGET_STATUS_BEFORE" >&2

    elif [[ "$arg1" == transaction:* ]]; then
        TARGET_TABLE="outgoing_transaction_messages"
        TARGET_ROW_ID="${arg1#transaction:}"
        local result
        result=$(db_query_value "SELECT recipient || '|' || COALESCE(ses_message_id, '') || '|' || status FROM outgoing_transaction_messages WHERE id = $TARGET_ROW_ID AND deleted_at IS NULL")
        result=$(echo "$result" | tr -d '[:space:]')
        if [ -z "$result" ]; then
            echo "ERROR: No transaction message found with id=$TARGET_ROW_ID" >&2
            exit 1
        fi
        TARGET_EMAIL=$(echo "$result" | cut -d'|' -f1)
        TARGET_SES_MSG_ID=$(echo "$result" | cut -d'|' -f2)
        TARGET_STATUS_BEFORE=$(echo "$result" | cut -d'|' -f3)
        if [ -z "$TARGET_SES_MSG_ID" ]; then
            TARGET_SES_MSG_ID=$(generate_uuid)
            TARGET_HAS_SES_ID=false
            echo "WARNING: transaction:$TARGET_ROW_ID has no ses_message_id — using generated UUID (status won't update)" >&2
        fi
        echo "Resolved transaction:$TARGET_ROW_ID → email=$TARGET_EMAIL, status=$TARGET_STATUS_BEFORE" >&2

    else
        TARGET_EMAIL="$arg1"
        TARGET_SES_MSG_ID="${arg2:-$(generate_uuid)}"
        TARGET_STATUS_BEFORE=""
    fi
}

# Check status after sending and report what changed
report_status_change() {
    local event_type="$1"  # delivery, bounce, complaint

    # Only report for DB-targeted messages
    if [ -z "$TARGET_TABLE" ] || [ -z "$TARGET_ROW_ID" ]; then
        return
    fi

    local status_after
    status_after=$(db_query_value "SELECT status FROM $TARGET_TABLE WHERE id = $TARGET_ROW_ID AND deleted_at IS NULL")
    status_after=$(echo "$status_after" | tr -d '[:space:]')

    if [ "$status_after" = "$TARGET_STATUS_BEFORE" ]; then
        echo "  Status unchanged: $TARGET_STATUS_BEFORE"
        if [ "$TARGET_HAS_SES_ID" = false ]; then
            echo "  (message has no ses_message_id — webhook can't match it to a DB row)"
        else
            case "$event_type" in
                delivery)
                    echo "  (delivery only updates SENT → DELIVERED; $TARGET_STATUS_BEFORE is not eligible)" ;;
                bounce|complaint)
                    echo "  ($event_type only updates SENT or DELIVERED → BOUNCED; $TARGET_STATUS_BEFORE is not eligible)" ;;
            esac
        fi
    else
        echo "  Status changed: $TARGET_STATUS_BEFORE → $status_after"
    fi
}

send_sns_payload() {
    local payload="$1"
    local description="$2"

    local http_code
    http_code=$(curl -sk -o /dev/null -w "%{http_code}" \
        -X POST "$WEBHOOK_URL" \
        -H "Content-Type: text/plain" \
        -d "$payload")

    if [ "$http_code" = "204" ]; then
        echo "OK ($http_code) — $description"
    else
        echo "FAILED ($http_code) — $description"
        echo "  URL: $WEBHOOK_URL"
    fi
}

build_sns_envelope() {
    local inner_message="$1"
    local sns_message_id
    sns_message_id=$(generate_uuid)

    local escaped_message
    escaped_message=$(echo "$inner_message" | python3 -c "import sys,json; print(json.dumps(sys.stdin.read().strip()))")

    cat <<EOF
{
  "Type": "Notification",
  "MessageId": "$sns_message_id",
  "TopicArn": "arn:aws:sns:us-east-1:000000000000:test-ses-notifications",
  "Message": $escaped_message,
  "Timestamp": "$(timestamp)"
}
EOF
}

# --- subcommands ---

cmd_list() {
    echo "=== Recent Outgoing Messages ==="
    echo "Use marketing:<id> or transaction:<id> to target a specific message"
    echo ""
    db_query "
        SELECT * FROM (
            SELECT
                'marketing' AS type,
                om.id,
                om.recipient,
                LEFT(om.subject, 40) AS subject,
                om.status,
                CASE WHEN om.ses_message_id IS NOT NULL THEN 'yes' ELSE '-' END AS has_ses_id,
                om.created_at
            FROM outgoing_messages om
            WHERE om.deleted_at IS NULL
            ORDER BY om.created_at DESC
            LIMIT 10
        ) marketing
        UNION ALL
        SELECT * FROM (
            SELECT
                'transaction' AS type,
                otm.id,
                otm.recipient,
                LEFT(otm.subject, 40) AS subject,
                otm.status,
                CASE WHEN otm.ses_message_id IS NOT NULL THEN 'yes' ELSE '-' END AS has_ses_id,
                otm.created_at
            FROM outgoing_transaction_messages otm
            WHERE otm.deleted_at IS NULL
            ORDER BY otm.created_at DESC
            LIMIT 10
        ) transact
        ORDER BY created_at DESC
        LIMIT 20;
    "
}

cmd_list_suppressions() {
    echo "=== Email Suppressions ==="
    echo ""
    db_query "
        SELECT
            id,
            email,
            reason,
            bounce_type,
            bounce_sub_type,
            complaint_type,
            source,
            account_id,
            created_at
        FROM email_suppressions
        WHERE deleted_at IS NULL
        ORDER BY created_at DESC
        LIMIT 20;
    "
}

cmd_bounce() {
    local target="${1:?Usage: ses-test.sh bounce <marketing:ID|transaction:ID|email> [ses_message_id]}"
    resolve_target "$target" "${2:-}"

    local inner
    inner=$(cat <<EOF
{
  "notificationType": "Bounce",
  "bounce": {
    "bounceType": "Permanent",
    "bounceSubType": "General",
    "bouncedRecipients": [{"emailAddress": "$TARGET_EMAIL"}],
    "timestamp": "$(timestamp)"
  },
  "mail": {
    "messageId": "$TARGET_SES_MSG_ID",
    "timestamp": "$(timestamp)",
    "destination": ["$TARGET_EMAIL"]
  }
}
EOF
)

    local payload
    payload=$(build_sns_envelope "$inner")
    send_sns_payload "$payload" "Permanent bounce for $TARGET_EMAIL (ses_msg_id=$TARGET_SES_MSG_ID)"
    report_status_change bounce
}

cmd_bounce_transient() {
    local target="${1:?Usage: ses-test.sh bounce-transient <marketing:ID|transaction:ID|email> [ses_message_id]}"
    resolve_target "$target" "${2:-}"

    local inner
    inner=$(cat <<EOF
{
  "notificationType": "Bounce",
  "bounce": {
    "bounceType": "Transient",
    "bounceSubType": "General",
    "bouncedRecipients": [{"emailAddress": "$TARGET_EMAIL"}],
    "timestamp": "$(timestamp)"
  },
  "mail": {
    "messageId": "$TARGET_SES_MSG_ID",
    "timestamp": "$(timestamp)",
    "destination": ["$TARGET_EMAIL"]
  }
}
EOF
)

    local payload
    payload=$(build_sns_envelope "$inner")
    send_sns_payload "$payload" "Transient bounce for $TARGET_EMAIL (ses_msg_id=$TARGET_SES_MSG_ID)"
    report_status_change bounce
}

cmd_complaint() {
    local target="${1:?Usage: ses-test.sh complaint <marketing:ID|transaction:ID|email> [ses_message_id]}"
    resolve_target "$target" "${2:-}"

    local inner
    inner=$(cat <<EOF
{
  "notificationType": "Complaint",
  "complaint": {
    "complaintFeedbackType": "abuse",
    "complainedRecipients": [{"emailAddress": "$TARGET_EMAIL"}],
    "timestamp": "$(timestamp)"
  },
  "mail": {
    "messageId": "$TARGET_SES_MSG_ID",
    "timestamp": "$(timestamp)",
    "destination": ["$TARGET_EMAIL"]
  }
}
EOF
)

    local payload
    payload=$(build_sns_envelope "$inner")
    send_sns_payload "$payload" "Complaint (abuse) for $TARGET_EMAIL (ses_msg_id=$TARGET_SES_MSG_ID)"
    report_status_change complaint
}

cmd_delivery() {
    local target="${1:?Usage: ses-test.sh delivery <marketing:ID|transaction:ID|email> [ses_message_id]}"
    resolve_target "$target" "${2:-}"

    local inner
    inner=$(cat <<EOF
{
  "notificationType": "Delivery",
  "delivery": {
    "recipients": ["$TARGET_EMAIL"],
    "timestamp": "$(timestamp)",
    "smtpResponse": "250 2.6.0 Message received"
  },
  "mail": {
    "messageId": "$TARGET_SES_MSG_ID",
    "timestamp": "$(timestamp)",
    "destination": ["$TARGET_EMAIL"]
  }
}
EOF
)

    local payload
    payload=$(build_sns_envelope "$inner")
    send_sns_payload "$payload" "Delivery notification for $TARGET_EMAIL (should update to DELIVERED)"
    report_status_change delivery
}

cmd_subscribe() {
    local payload
    payload=$(cat <<EOF
{
  "Type": "SubscriptionConfirmation",
  "MessageId": "$(generate_uuid)",
  "TopicArn": "arn:aws:sns:us-east-1:000000000000:test-ses-notifications",
  "Message": "You have chosen to subscribe to the topic...",
  "SubscribeURL": "https://sns.us-east-1.amazonaws.com/?Action=ConfirmSubscription&TopicArn=test&Token=fake",
  "Timestamp": "$(timestamp)",
  "Token": "fake-confirmation-token"
}
EOF
)

    send_sns_payload "$payload" "SubscriptionConfirmation (will attempt to confirm fake URL — check logs)"
}

cmd_unsuppress() {
    local target="${1:?Usage: sns-webhook-simulator.sh unsuppress <id|email>}"

    if [[ "$target" =~ ^[0-9]+$ ]]; then
        echo "Soft-deleting suppression id=$target..."
        db_query "UPDATE email_suppressions SET deleted_at = NOW() WHERE id = $target AND deleted_at IS NULL;"
    else
        echo "Soft-deleting all suppressions for email=$target..."
        db_query "UPDATE email_suppressions SET deleted_at = NOW() WHERE email = '$(echo "$target" | tr "'" "")' AND deleted_at IS NULL;"
    fi
}

# --- main ---

case "${1:-help}" in
    list)
        cmd_list
        ;;
    list-suppressions|suppressions)
        cmd_list_suppressions
        ;;
    bounce)
        shift
        cmd_bounce "$@"
        ;;
    bounce-transient)
        shift
        cmd_bounce_transient "$@"
        ;;
    complaint)
        shift
        cmd_complaint "$@"
        ;;
    delivery)
        shift
        cmd_delivery "$@"
        ;;
    subscribe)
        cmd_subscribe
        ;;
    unsuppress)
        shift
        cmd_unsuppress "$@"
        ;;
    help|--help|-h|*)
        cat <<USAGE
Usage: sns-webhook-simulator.sh <command> [args]

Simulate SNS webhook events:
  bounce <target>           Simulate a permanent (hard) bounce
  bounce-transient <target> Simulate a transient (soft) bounce
  complaint <target>        Simulate an abuse complaint
  delivery <target>         Simulate a delivery notification
  subscribe                 Simulate SNS subscription confirmation

Inspect and manage:
  list                      Show recent outgoing messages (marketing + transaction)
  list-suppressions         Show email suppression records
  unsuppress <id|email>     Remove suppression by id or email

Target formats:
  marketing:10              Look up email + ses_message_id from outgoing_messages row 10
  transaction:2             Look up from outgoing_transaction_messages row 2
  user@example.com          Use this email with a generated ses_message_id
  user@example.com ID       Use this email with a specific ses_message_id

Status transitions:
  delivery:   SENT → DELIVERED  (only from SENT)
  bounce:     SENT or DELIVERED → BOUNCED
  complaint:  SENT or DELIVERED → BOUNCED  (also creates suppression)

Environment:
  SES_TEST_URL    Webhook URL (default: https://localhost:8443/api/public/webhooks/ses)

Examples:
  sns-webhook-simulator.sh list
  sns-webhook-simulator.sh delivery marketing:10
  sns-webhook-simulator.sh bounce marketing:10
  sns-webhook-simulator.sh bounce transaction:1
  sns-webhook-simulator.sh complaint marketing:10
  sns-webhook-simulator.sh bounce user@example.com
  sns-webhook-simulator.sh list-suppressions
  sns-webhook-simulator.sh unsuppress 3
  sns-webhook-simulator.sh unsuppress bounce@simulator.amazonses.com

Requires docker/development environment running with:
  AWS_SNS_VERIFY_SIGNATURE=false
  SES_SUPPRESSION_ENABLED=true
USAGE
        ;;
esac
