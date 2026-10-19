#!/usr/bin/env python3
"""
把 data/*/*_lvr_land_{a,b,c}.xml.json.gz 全部載入 data/raw.duckdb
a = 成屋買賣、b = 預售屋買賣、c = 租賃

用法: .venv/bin/python bin/stats_load.py
"""
import os
import duckdb

ROOT = os.path.dirname(os.path.abspath(__file__)) + '/..'
DB = f'{ROOT}/data/raw.duckdb'

tmp = DB + '.tmp'
if os.path.exists(tmp):
    os.remove(tmp)
con = duckdb.connect(tmp)
for kind in 'abc':
    con.execute(f"""
    CREATE TABLE raw_{kind} AS
    SELECT *, regexp_extract(filename, '/([a-z])_lvr_land', 1) AS county_code
    FROM read_json('{ROOT}/data/*/*_lvr_land_{kind}.xml.json.gz', format='newline_delimited',
                   filename=true, union_by_name=true, ignore_errors=true)
    """)
    print(kind, con.execute(f"SELECT count(*) FROM raw_{kind}").fetchone()[0], flush=True)
con.close()
os.replace(tmp, DB)
