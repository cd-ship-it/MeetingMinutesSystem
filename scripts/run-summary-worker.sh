#!/usr/bin/env bash
#
# Production runner for the AI summary worker.
# Calls generate-summaries.php and directs stdout to one log file, stderr to another.
# By default only meetings with empty ai_summary are processed; use --force-ai-refresh to overwrite.
#
# Usage:
#   ./scripts/run-summary-worker.sh [OPTIONS]
#   ./scripts/run-summary-worker.sh --help
#
# Options (passed through to generate-summaries.php):
#   --limit=N           Max meetings per run (default: 10).
#   --force-ai-refresh  Process even when ai_summary is set (overwrite).
#   --help              Show options and exit.
#
# Cron example (every 10 minutes, only process empty ai_summary):
#   */10 * * * * /path/to/MeetingMinutesSystem/scripts/run-summary-worker.sh
#
# Cron with force refresh (e.g. once daily):
#   0 2 * * * /path/to/MeetingMinutesSystem/scripts/run-summary-worker.sh --force-ai-refresh --limit=50

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
LOG_DIR="${LOG_DIR:-$PROJECT_ROOT/logs}"
LOG_FILE="${LOG_FILE:-$LOG_DIR/summary-worker.log}"
ERR_FILE="${ERR_FILE:-$LOG_DIR/summary-worker-error.log}"
PHP_SCRIPT="$SCRIPT_DIR/generate-summaries.php"

show_help() {
    echo "run-summary-worker.sh - Run AI summary worker for meeting minutes"
    echo ""
    echo "Usage:"
    echo "  $0 [OPTIONS]"
    echo ""
    echo "Options (passed to generate-summaries.php):"
    echo "  --limit=N           Max meetings per run (default: 10)."
    echo "  --all, --limit=all  Process all matching records (no limit)."
    echo "  --force-ai-refresh  Process meetings even when ai_summary is set (overwrite)."
    echo "  --help              Show this help and exit."
    echo ""
    echo "Default: only runs for meetings where ai_summary IS NULL, limit 10. Use --all to process all."
    echo ""
    echo "Logs:"
    echo "  stdout -> $LOG_FILE"
    echo "  stderr -> $ERR_FILE"
    echo ""
    echo "Override log paths: LOG_FILE=path ERR_FILE=path $0"
    echo ""
    echo "Cron (every 10 min): */10 * * * * $PROJECT_ROOT/scripts/run-summary-worker.sh"
}

for arg in "$@"; do
    case "$arg" in
        --help|-h) show_help; exit 0 ;;
    esac
done

mkdir -p "$LOG_DIR"

# Append with timestamp so each run is visible
echo "--- $(date '+%Y-%m-%d %H:%M:%S') run-summary-worker start ---" >> "$LOG_FILE"
php "$PHP_SCRIPT" "$@" >> "$LOG_FILE" 2>> "$ERR_FILE"
exit_code=$?
echo "--- $(date '+%Y-%m-%d %H:%M:%S') run-summary-worker end (exit $exit_code) ---" >> "$LOG_FILE"

exit $exit_code
