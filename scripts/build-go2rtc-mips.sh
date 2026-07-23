#!/usr/bin/env bash
set -euo pipefail

# Cross-compiles AlexxIT/go2rtc for the little-endian MIPS (mipsle) Ingenic
# Xburst camera SoCs used by Petkit devices (e.g. D4H, see public/petkit/D4H/go2rtc).
#
# Go's default `GOARCH=mips` target is big-endian. These SoCs are little-endian,
# so a plain `GOARCH=mips` build traps immediately on first exec
# ("Trace/Breakpoint trap") - GOARCH=mipsle is required.

GO2RTC_REF="${GO2RTC_REF:-df95ce39d08f}" # matches the commit vendored at public/petkit/D4H/go2rtc
GO2RTC_REPO="https://github.com/AlexxIT/go2rtc.git"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
CACHE_DIR="$SCRIPT_DIR/.build-cache/go2rtc"
OUTPUT_DIR="$REPO_ROOT/build/output"
OUTPUT_FILE="$OUTPUT_DIR/go2rtc-mipsle"
OUTPUT_FILE_PACKED="$OUTPUT_DIR/go2rtc-mipsle.upx"

mkdir -p "$OUTPUT_DIR"

if [ ! -d "$CACHE_DIR" ]; then
    git clone "$GO2RTC_REPO" "$CACHE_DIR"
fi

cd "$CACHE_DIR"
git fetch --all --tags
git checkout "$GO2RTC_REF"

echo "Building go2rtc ($GO2RTC_REF) for linux/mipsle (hardfloat)..."
GOOS=linux GOARCH=mipsle GOMIPS=hardfloat go build -trimpath -ldflags="-s -w" -o "$OUTPUT_FILE" .

echo "Built: $OUTPUT_FILE"
file "$OUTPUT_FILE"

# The device's flash (appfs mtd partition) is small - the vendored
# public/petkit/D4H/go2rtc binary is UPX-packed to ~4MB. An uncompressed
# build (~18MB) will not fit/transfer intact, which manifests on-device as
# a shell "syntax error: unexpected (" - the kernel's execve() rejects the
# truncated/corrupt file (ENOEXEC) and the shell falls back to trying to
# interpret its raw bytes as a script.
if command -v upx >/dev/null 2>&1; then
    echo "Packing with UPX..."
    upx --ultra-brute -f -o "$OUTPUT_FILE_PACKED" "$OUTPUT_FILE"
    echo "Packed: $OUTPUT_FILE_PACKED"
    ls -la "$OUTPUT_FILE" "$OUTPUT_FILE_PACKED"
else
    echo "WARNING: upx not found - skipping packing. Install upx to produce a device-sized binary." >&2
fi
