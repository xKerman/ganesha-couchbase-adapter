# -*- coding: utf-8 -*-

container_name = couchbase-server

.PHONY: start
start:
	docker run --rm --name $(container_name) -d -p 8091:8091 -p 8092:8092 -p 8093:8093 -p 8094:8094 -p 11210:11210 couchbase/server:community-5.0.1
	sleep 10
	docker exec -i $(container_name) bash < ./init.sh

.PHONY: stop
stop:
	docker stop $(container_name)
	docker rm $(container_name)

doc: src/Adapter/*.php
	if [ ! -f phpDocumentor.phar ]; then curl -L -o phpDocumentor.phar http://phpdoc.org/phpDocumentor.phar; fi
	php phpDocumentor.phar run -d src/ -t doc/
