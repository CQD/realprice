#!/usr/bin/env bash
# 部署後清理，省儲存費：刪掉沒有流量的 GAE 版本、Artifact Registry 裡舊的 build image
# 用法： gae_cleanup.sh {project}
set -eEuo pipefail

project=$1
service=default

versions=$(gcloud app versions list --project="$project" --service=$service --filter='traffic_split=0' --format='value(id)')
if [ -n "$versions" ]; then
    echo "刪除 GAE 版本：" $versions
    gcloud app versions delete --project="$project" --service=$service --quiet $versions
fi

# 每次部署的 image 在 gae-standard/app/{service}，latest 是線上版本，其他都可以刪
# (build-cache 只有一個 image，留著加速下次 build)
location=$(gcloud app describe --project="$project" --format='value(locationId)')
repo=$location-docker.pkg.dev/$project/gae-standard/app/$service
for digest in $(gcloud artifacts docker images list "$repo" --include-tags --filter='NOT tags:latest' --format='value(version)' 2>/dev/null); do
    echo "刪除 image：$repo@$digest"
    gcloud artifacts docker images delete "$repo@$digest" --delete-tags --quiet
done
