APPLICATION_ID=mythical-temple-395806

build: vendor/autoload.php download | option build/transactions.sqlite3

download:
	bin/getall.sh

server:
	php -S 0.0.0.0:8080 -t public/

clean:
	rm public/build/option.json || true

deep-clean: clean
	rm build/transactions.sqlite3 || true

deploy:
	gcloud app deploy --project='$(APPLICATION_ID)' --promote --stop-previous-version $(OPTIONS)

########################

vendor/autoload.php: composer.json
	composer install

option: public/build/option.json build/option.php

public/build/option.json: build/transactions.sqlite3 bin/build_option.php
	 bin/build_option.php > $@

build/option.php: public/build/option.json
	bin/json2php.php $< > $@

build/transactions.sqlite3: bin/getall.sh
	bin/build_sqlite.php > $@

.PHONY: server build/option.php download
