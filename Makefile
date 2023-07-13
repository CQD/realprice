.PHONY: server

build: download | public/build/option.json build/transactions.sqlite3

download:
	bin/getall.sh

server:
	php -S 0.0.0.0:8080 -t public/

clean:
	rm public/build/option.json || true

deep-clean: clean
	rm build/transactions.sqlite3 || true

########################

public/build/option.json: build/transactions.sqlite3 bin/build_option.php
	 bin/build_option.php > $@

build/transactions.sqlite3: bin/getall.sh
	bin/build_sqlite.php > $@
