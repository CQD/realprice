#!/bin/bash

BASEDIR=$(dirname "$0")

#################################
# 先準備好 key 清單
#################################
keys=( "101S4" )
for year in $(seq 102 111); do
    for s in 1 2 3 4; do
        keys+=( "$year"S$s )
    done
done

#################################
# 每個 key 各自拉檔案做處理
#################################
for key in ${keys[@]}; do
    if [ -d $BASEDIR/../data/$key ]; then
        echo "$key 資料已存在，不處理"
    else
        $BASEDIR/process.sh $key $BASEDIR/../data
    fi
done
