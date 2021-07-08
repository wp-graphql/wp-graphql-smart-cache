Hello wp-graphql-persisted-queries plugin.

# Docker

## Setup

`cp .env.dist .env`

## build

Build all images in the docker compose confiuration.

`docker-compose build --build-arg WP_VERSION=5.7.2 --build-arg PHP_VERSION=7.4`

Build fresh docker image. Exclude cache by adding `--no-cache`.

`docker-compose build --build-arg WP_VERSION=5.7.2 --build-arg PHP_VERSION=7.4 --no-cache`

Build using wp-graphql image from docker hub registry, instead of building your own wp-graphql image.

`docker-compose build --build-arg WP_VERSION=5.7.2 --build-arg PHP_VERSION=7.4 --build-arg DOCKER_REGISTRY=docker.io/`

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
