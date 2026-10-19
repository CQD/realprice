APPLICATION_ID=mythical-temple-395806

build: vendor/autoload.php download | option build/transactions.sqlite3 cache stats

download:
	bin/getall.sh

server:
	php -S 0.0.0.0:8080 -t public/

clean:
	rm public/build/option.json || true

deep-clean: clean
	rm build/transactions.sqlite3 || true
	rm -rf build/cache || true

deploy:
	gcloud app deploy --project='$(APPLICATION_ID)' --promote --stop-previous-version $(OPTIONS)

test: vendor/bin/phpunit
	vendor/bin/phpunit tests

########################

vendor/autoload.php: composer.lock
	composer install

vendor/bin/phpunit: composer.lock
	composer install

option: public/build/option.json build/option.php

public/build/option.json: build/transactions.sqlite3 bin/build_option.php src/Controller/ApiOption.php
	 bin/build_option.php > $@

build/option.php: public/build/option.json
	bin/json2php.php $< > $@

cache: build/cache

# 新版頁面的統計資料 (Python + DuckDB，只在離線建置時使用)
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

build/cache: build/transactions.sqlite3 build/option.php
	bin/build_cache.php

build/transactions.sqlite3: data/updated bin/getall.sh
	bin/build_sqlite.php

.PHONY: server download cache stats
