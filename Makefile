APPLICATION_ID=mythical-temple-395806

build: vendor/autoload.php download | stats

download:
	bin/getall.sh

server:
	php -S 0.0.0.0:8080 -t public/

clean:
	rm -f build/site.json build/site.php
	rm -rf public/data

deep-clean: clean
	rm -f data/raw.duckdb

deploy:
	gcloud app deploy --project='$(APPLICATION_ID)' --promote --stop-previous-version $(OPTIONS)

test: vendor/bin/phpunit
	vendor/bin/phpunit tests

########################

vendor/autoload.php: composer.lock
	composer install

vendor/bin/phpunit: composer.lock
	composer install

# 統計資料 (Python + DuckDB，只在離線建置時使用)
stats: build/site.php

.venv/bin/python: requirements.txt
	python3 -m venv .venv
	.venv/bin/pip install -r requirements.txt
	touch $@

data/raw.duckdb: data/updated bin/stats_load.py .venv/bin/python
	.venv/bin/python bin/stats_load.py

build/site.json: data/raw.duckdb bin/stats_build.py
	.venv/bin/python bin/stats_build.py

build/site.php: build/site.json
	bin/json2php.php $< > $@

.PHONY: server download stats clean deep-clean
