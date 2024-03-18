#!/bin/bash
set -eE

BASEDIR=$(dirname "$0")

#################################
# 先準備好 key 清單
#################################
keys=( "101S4" )

# 102 年到去年每一季對應的 key
lastyear=$(( $(date +%Y) - 1911 - 1 ))
for year in $(seq 102 $lastyear) ; do
    for s in 1 2 3 4; do
        keys+=( "$year"S$s )
    done
done


tw_year=$(( $year - 1911 ))
month=$(date +%m)
day=$(date +%d)

# 把本年度已經經過的季的 key 加進來，不包含當季
passed_q=0
for q in 1 2 3; do
    if [ $month -gt $(( $q * 3 )) ]; then
        keys+=( "$tw_year"S$q )
    fi
    passed_q=$q
done

# 把當季的前期 key 們都加進來，每個月的 1 / 11 / 21 日
# Ex: 20240101 / 20240111 / 20240121...
year=$(date +%Y)
today=$(date +%Y%m%d)
for m in $(seq 1 12); do
    for d in 1 11 21; do
        ymd=$(printf "%04d%02d%02d" $year $m $d)
        if [ $ymd -lt $today ]; then
            keys+=( "$ymd" )
        fi
    done
done

#################################
# 每個 key 各自拉檔案做處理
#################################
for key in ${keys[@]}; do
    if [ -d $BASEDIR/../data/$key ]; then
        echo "$key 資料已存在，不處理"
    else
        filepath=$($BASEDIR/download.sh $key $BASEDIR/../data)
        $BASEDIR/process.sh $filepath $key $BASEDIR/../data
    fi
done
