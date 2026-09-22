#!/bin/sh

set -e

COVERAGE_FILE="${1:-coverage/clover.xml}"
THRESHOLD="${2:-${MIN_COVERAGE_PERCENTAGE:-80}}"

if [ ! -f "$COVERAGE_FILE" ]; then
    echo "Error: Coverage file not found: $COVERAGE_FILE"
    exit 1
fi

COVERAGE=$(php -r "
\$xml = simplexml_load_file('$COVERAGE_FILE');
\$metrics = \$xml->xpath('//metrics');
\$totalElements = \$coveredElements = 0;
foreach (\$metrics as \$metric) {
    \$totalElements += (int)\$metric['elements'];
    \$coveredElements += (int)\$metric['coveredelements'];
}
\$coverage = \$totalElements > 0 ? (\$coveredElements / \$totalElements) * 100 : 0;
echo sprintf('%.2f', \$coverage);
")

echo ""
echo "========================================"
echo "  Code Coverage Report"
echo "========================================"
echo "Coverage:  $COVERAGE%"
echo "Threshold: $THRESHOLD%"
echo "========================================"
echo ""

# Use awk for floating point comparison (bc may not be available)
if awk "BEGIN {exit !($COVERAGE < $THRESHOLD)}"; then
    echo "FAILED: Coverage $COVERAGE% is below threshold $THRESHOLD%"
    exit 1
fi

echo "PASSED: Coverage $COVERAGE% meets threshold $THRESHOLD%"
exit 0
