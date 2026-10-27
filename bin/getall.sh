#!/usr/bin/env bash
set -eE

BASEDIR=$(dirname "$0")
DATADIR=$(realpath $BASEDIR/../data)

#################################
# 先準備好 key 清單
#################################
declare -A keys
keys['101S4']='101S4'

# 102 年到去年每一季對應的 key
last_year=$(( $(date +%Y) - 1 ))
last_tw_year=$(( $last_year - 1911 ))
for tw_year in $(seq 102 $last_tw_year) ; do
    for s in 1 2 3 4; do
        key="$tw_year"S$s
        keys[$key]=$key
    done
done

year=$(date +%Y)
tw_year=$(( $year - 1911 ))
month=$(date +%m)
day=$(date +%d)
today=$(date +%Y%m%d)


# 把本年度已經經過的季的 key 加進來，不包含當季
current_q=0
for q in 1 2 3 4; do
    if [ $month -gt $(( $q * 3 )) ]; then
        key="$tw_year"S$q
        keys[$key]=$key
    else
        current_q=$q
        break
    fi
done

# 把當季的前期 key 們都加進來，每個月的 1 / 11 / 21 日 (含今天，發布當天就能抓)
# Ex: 20240101 / 20240111 / 20240121...
year=$(date +%Y)
today=$(date +%Y%m%d)
latest_key=""
for m in $(seq 1 12); do
    if [ "$m" -le "$(( ($current_q - 1) * 3 ))" ]; then
        continue;
    fi

    for d in 1 11 21; do
        ymd=$(printf "%04d%02d%02d" $year $m $d)
        if [ "$ymd" -gt "$today" ]; then
            break;
        fi

        keys[$ymd]=$ymd
        latest_key=$ymd
    done
done

# 檢查是否有已經被包進季報的過期 key
for key in $(ls $DATADIR | grep '^[0-9]\+$'); do
    if [ -z "${keys[$key]}" ]; then

        # 非互動 (cron) 直接移除：已經被包進季報，留著會重複
        if [ ! -t 0 ]; then
            echo 移除 data/$key ...; rm -r $DATADIR/$key || true
            continue
        fi

        while true; do
            read -p "是否要移除 data/$key? (y/n) " yn
            case $yn in
                [Yy]* ) echo 移除 data/$key ...;rm -r $DATADIR/$key || true; break;;
                [Nn]* ) break;;
                * ) echo "請輸入 y / n";;
            esac
        done
    fi
done

echo ----------------------------------------
echo 會處理的 key 清單
echo ----------------------------------------

last_key="S"
for key in $(echo "${!keys[@]}" | tr ' ' "\n" | sort -n); do
    if [[ "$last_key" == *S* ]] && [[ "$key" != *S* ]] ; then echo; echo; fi
    echo -n "$key "
    if [[ "$key" == *"S4" ]]; then echo ; fi
    if [[ "$key" == "20"*"21" ]]; then echo ; fi
    last_key=$key
done
echo
echo ----------------------------------------

#################################
# 每個 key 各自拉檔案做處理
#################################
for key in $(echo ${!keys[@]} | tr " " "\n" | sort); do
    if [ -d $BASEDIR/../data/$key ]; then
        echo "$key 資料已存在，不處理"
    else
        # 最新的 key 額外處理，其他 key 用 download.sh 下載
        lvr_time=""
        if [ "$key" == "$latest_key" ]; then
            filepath=$DATADIR/$key.zip
            curl -# "https://plvr.land.moi.gov.tw//Download?type=zip&fileName=lvr_landxml.zip" -o $filepath

            # 最新一期的網址不分期數，還沒發布時拿到的是上一期：比對資料內容期間
            lvr_time=$(unzip -p $filepath build_time.xml | grep -o '<lvr_time>.*</lvr_time>' || true)
            if [ -n "$lvr_time" ] && grep -qxF "$lvr_time" $DATADIR/*/lvr_time.txt 2>/dev/null; then
                echo "$key 尚未發布，目前的資料跟已下載的相同：$lvr_time"
                rm -f $filepath
                continue
            fi
        else
            filepath=$($BASEDIR/download.sh $key $DATADIR)
        fi
        echo $BASEDIR/process.sh $filepath $key $DATADIR
        $BASEDIR/process.sh $filepath $key $DATADIR
        if [ -n "$lvr_time" ]; then
            echo "$lvr_time" > $DATADIR/$key/lvr_time.txt
        fi
        touch $DATADIR/updated
    fi
done

echo ----------------------------------------
echo 資料下載完成
echo ----------------------------------------
