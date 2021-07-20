The wp-graphql-persisted-queries plugin.

Local development with docker to build the app and a local running WordPress. As well as run the test suites.

# Docker App Image

## Setup

`cp .env.dist .env`

## build

### docker-compose build

Build all images in the docker compose configuration.

`WP_VERSION=5.7.2  PHP_VERSION=7.4 docker-compose build --build-arg WP_VERSION=5.7.2 --build-arg PHP_VERSION=7.4`

Build fresh docker image without cache by adding `--no-cache`.

`WP_VERSION=5.7.2  PHP_VERSION=7.4 docker-compose build --build-arg WP_VERSION=5.7.2 --build-arg PHP_VERSION=7.4 --no-cache`

Build using wp-graphql image from docker hub registry, instead of building your own wp-graphql image.

`WP_VERSION=5.7.2  PHP_VERSION=7.4 docker-compose build --build-arg WP_VERSION=5.7.2 --build-arg PHP_VERSION=7.4 --build-arg DOCKER_REGISTRY=ghcr.io/wp-graphql/`

### docker build

`docker build -f docker/Dockerfile -t wp-graphql-persisted-queries:latest-wp5.6-php7.4 --build-arg WP_VERSION=5.6 --build-arg PHP_VERSION=7.4`

## run

`docker compose up app`

`docker compose up -e WP_VERSION=5.7.2 -e PHP_VERSION=7.4 app`

## shell in the docker image

`docker-compose run app bash`

`docker-compose run -e WP_VERSION=5.7.2 -e PHP_VERSION=7.4 app bash`

## stop

`docker-compose stop`

## Attach local wp-graphql plugin

Add this to volumes in docker-compose.yml. 

      - './local-wp-graphql:/var/www/html/wp-content/plugins/wp-graphql'

# Plugin

## build

`composer install --optimize-autoloader`

or

`composer update --optimize-autoloader`

# WP Tests

## build image

### docker-compose build

`WP_VERSION=5.7.2 PHP_VERSION=7.4 docker build -f Dockerfile.testing -t wp-graphql-persisted-queries-testing:latest-wp${WP_VERSION}-php${PHP_VERSION} --build-arg WP_VERSION=${WP_VERSION} --build-arg PHP_VERSION=${PHP_VERSION} `

### docker build

`docker build -f docker/Dockerfile.testing -t wp-graphql-persisted-queries-testing:latest-wp5.7.2-php7.4 --build-arg WP_VERSION=5.7.2 --build-arg PHP_VERSION=7.4`

## run

`WP_VERSION=5.7.2 PHP_VERSION=7.4 SUITES=acceptance,functional docker-compose run testing`

## shell in the docker image

`docker-compose run --entrypoint bash testing`

`docker-compose run -e WP_VERSION=5.7.2 -e PHP_VERSION=7.4 --entrypoint bash testing`
