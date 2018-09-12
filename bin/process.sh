#!/bin/bash

set -eE

if [ -z "$1" ]; then
    echo "沒有輸入 key 無法處理資料"
    echo "用法： process.sh {key} [targetdir]"
    false
fi

key=$1
targetdir=${2:-$(pwd)}
BASEDIR=$(dirname "$0")

trap "rm -f $targetdir/$key.zip" EXIT

###############################################################

function preservFile
{
    file=$1

    if [[ "$file" == *manifest.csv* ]]; then
        return 0
    fi

    if [[ $file != *.xml* ]]; then
        return -1
    elif [[ $file = *_build.*  ]] || [[ $file = *_land.* ]] || [[ $file = *_park.* ]]; then
        return -1
    else
        return 0
    fi
}

###############################################################

function main {

    if [ ! -d "$targetdir" ]; then
        echo "目錄 $targetdir 不存在，無法寫入資料"
        false
    fi

    echo 下載 $key.zip

    [[ $key =~ ^[0-9]{8}$ ]] \
        && curl -# "https://plvr.land.moi.gov.tw/DownloadHistory?type=history&fileName=${key}" -o $targetdir/$key.zip \
        || curl -# "https://plvr.land.moi.gov.tw/DownloadSeason?season=${key}&type=zip&fileName=lvr_landxml.zip" -o $targetdir/$key.zip \
        || (echo "無法下載 key=$key 的資料" && return -1)

    if ! unzip -t $targetdir/$key.zip ; then
        echo "無法解壓縮 $key.zip"
        return -1
    fi

    echo 解開 zip
    unzip -q -LL -d $targetdir/$key $targetdir/$key.zip
    rm $targetdir/$key.zip

    echo 砍掉不要的檔案
    for file in $targetdir/$key/*; do
        if ! preservFile $file ; then
            rm $file
        fi
    done

    echo 把檔案分別轉成 json 然後 gzip
    for file in $targetdir/$key/*; do
        if [[ $file = *".xml"  ]]; then
            cat $file | $BASEDIR/xml2json | gzip > $file.json.gz
            rm $file
        else
            gzip $file
        fi
    done
    echo
}

main
