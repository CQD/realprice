.PHONY: server

asset: public/build/option.json

server:
	php -S 0.0.0.0:8080 -t public/

########################

public/build/option.json: transactions.sqlite3 bin/build_option.php
	 bin/build_option.php > $@
