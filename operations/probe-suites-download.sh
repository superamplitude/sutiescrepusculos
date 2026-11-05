#!/usr/bin/env bash
set -euo pipefail
curl -sSL --max-time 25 -r 0-100 -o /dev/null -w 'status=%{http_code} type=%{content_type} size=%{size_download}\n' 'https://drive.google.com/uc?export=download&id=1koyo6TgLhO3kjgM-6cB4ZmrSvcmR0qCn'
