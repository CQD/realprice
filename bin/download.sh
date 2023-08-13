#!/bin/bash

set -eE

if [ -z "$1" ]; then
    echo "沒有輸入 key 無法下載資料"
    echo "用法： download.sh {key} [targetdir]"
    false
fi

key=$1
targetdir=${2:-$(pwd)}
BASEDIR=$(dirname "$0")

###############################################################

function main {

    if [ ! -d "$targetdir" ]; then
        echo "目錄 $targetdir 不存在，無法寫入資料" >&2
        false
    fi

    if [ -f "$targetdir/$key.zip" ]; then
        echo "目標檔案 $targetdir/$key.zip 已存在，不處理" >&2
        echo $targetdir/$key.zip
        return
    fi


    echo 下載 $key.zip >&2

    [[ $key =~ ^[0-9]{8}$ ]] \
        && curl -# "https://plvr.land.moi.gov.tw/DownloadHistory?type=history&fileName=${key}" -o $targetdir/$key.zip \
        || curl -# "https://plvr.land.moi.gov.tw/DownloadSeason?season=${key}&type=zip&fileName=lvr_landxml.zip" -o $targetdir/$key.zip \
        || (echo "無法下載 key=$key 的資料" && return -1)

    echo "d:" $targetdir/$key.zip  >&2 # XXX
    echo $targetdir/$key.zip
}

main
