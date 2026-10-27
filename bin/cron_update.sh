#!/usr/bin/env bash
# 定時更新 (crontab 用)：下載新資料、重建統計，site.php 比上次部署新才部署，部署後清掉舊版本與 image
# PATH (gcloud、composer、python3)、log、通知由呼叫端處理；失敗時 exit 非 0
set -eEuo pipefail

cd "$(dirname "$0")/.."

# 避免上一次還沒跑完又疊一個
exec 9>data/.cron_update.lock
if ! flock -n 9; then
    echo "上一次更新還在跑，跳過"
    exit 1
fi

echo "== $(date '+%F %T') 開始更新"

make build < /dev/null

# 部署成功才 touch，部署失敗下次會再試
if [ build/site.php -nt data/.deployed ]; then
    make deploy OPTIONS=--quiet < /dev/null
    touch data/.deployed
    echo "== $(date '+%F %T') 部署完成"
    make cleanup < /dev/null
else
    echo "== $(date '+%F %T') 沒有新資料，不部署"
fi
