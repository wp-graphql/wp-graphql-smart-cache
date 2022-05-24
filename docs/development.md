The wp-graphql-labs plugin.

Local development with docker to build the app and a local running WordPress. As well as run the test suites.

# Plugin

## Build

Use one of the following commands to build the plugin source and it's dependencies. Do this at least once after initial checkout or after updating composer.json.

    composer install --optimize-autoloader

    composer update --optimize-autoloader

One option is to use a docker image to run php/composer:

    docker run -v $PWD:/app composer install --optimize-autoloader

# Docker App Image

This section describes how to setup and run this plugin, WP and the wp-graphql plugin locally with docker.  It requires building the images at least once, which can take a few moments the first time. 

## Build

Use one of the following commands to build the local images for the app and testing.

### docker-compose

Build all images in the docker compose configuration. Requires having built your own wp-graphql local images.

    WP_VERSION=5.9  PHP_VERSION=8.0 docker-compose build --build-arg WP_VERSION=5.9 --build-arg PHP_VERSION=8.0

Build fresh docker image without cache by adding `--no-cache`.

    WP_VERSION=5.9  PHP_VERSION=8.0 docker-compose build --build-arg WP_VERSION=5.9 --build-arg PHP_VERSION=8.0 --no-cache

Build using wp-graphql image from docker hub registry, instead of building your own wp-graphql image.

    WP_VERSION=5.9  PHP_VERSION=8.0 docker-compose build --build-arg WP_VERSION=5.9 --build-arg PHP_VERSION=8.0 --build-arg DOCKER_REGISTRY=ghcr.io/wp-graphql/

### docker

Use this command if you want to build a specific image. If you ran the docker-compose command above, this is not necessary.

    docker build -f docker/Dockerfile -t wp-graphql-labs:latest-wp5.6-php7.4 --build-arg WP_VERSION=5.6 --build-arg PHP_VERSION=8.0

## Run

Use one of the following to start the WP app with the plugin installed and running. After running, navigate to the app in a web browser at http://localhost:8091/

    docker compose up app

This is an example of specifying the WP and PHP version for the wp-graphql images.

    WP_VERSION=5.9 PHP_VERSION=8.0 docker compose up app

## Shell

Use one of the following if you want to access the WP app with bash command shell.

    docker-compose run app bash

    WP_VERSION=5.9 PHP_VERSION=8.0 docker-compose run app bash

## Stop

Use this command to stop the running app and database.

    docker-compose stop

## Attach local wp-graphql plugin

Add this to volumes section in docker-compose.yml if you have a copy of the wp-graphql plugin you'd like to use in the running app. 

      - './local-wp-graphql:/var/www/html/wp-content/plugins/wp-graphql'

# WP Tests

Use this section to run the plugin codeception test suites.

## Build

Use one of the following commands to build the test docker image. 

### docker-compose

If you ran the docker-compose build command, above, this step is not necessary and you should already have the build docker image, skip to run.

### docker

    WP_VERSION=5.9 PHP_VERSION=8.0 docker build -f docker/Dockerfile.testing -t wp-graphql-labs-testing:latest-wp5.7.2-php7.4 --build-arg WP_VERSION=5.9 --build-arg PHP_VERSION=8.0 --build-arg DOCKER_REGISTRY=ghcr.io/wp-graphql/ .

    docker build -f docker/Dockerfile.testing -t wp-graphql-labs-testing:latest-wp5.7.2-php7.4 --build-arg WP_VERSION=5.9 --build-arg PHP_VERSION=8.0 --build-arg DOCKER_REGISTRY=ghcr.io/wp-graphql/ .

## Run

Use one of these commands to run the test suites.

    WP_VERSION=5.9 PHP_VERSION=8.0 docker-compose run testing

    docker-compose run testing

Use the DEBUG environment variable to see the codeception debug during tests.

    WP_VERSION=5.9 PHP_VERSION=8.0 docker-compose run -e DEBUG=1 testing

## Shell

Use one of the following if you want to access the WP testing app with bash command shell.

    docker-compose run --entrypoint bash testing

This is an example of specifying the WP and PHP version for the wp-graphql images.

    WP_VERSION=5.9 PHP_VERSION=8.0 docker-compose run --entrypoint bash testing
