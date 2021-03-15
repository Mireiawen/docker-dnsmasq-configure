#!/bin/bash
set -euo pipefail
IFS=$'\n\t'
docker 'run' \
	--rm \
	--volume "$(pwd)/files:/app/files:ro" \
	--interactive \
	--tty 'mireiawen/dnsmasq-config'
