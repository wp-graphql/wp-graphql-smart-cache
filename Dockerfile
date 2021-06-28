ARG WP_VERSION
ARG PHP_VERSION

FROM wp-graphql:latest-wp${WP_VERSION}-php${PHP_VERSION}

# Move the base image app setup script out of the way
# Put our shell script in place which will invoke the base image script
RUN mv /usr/local/bin/app-setup.sh /usr/local/bin/original-app-setup.sh
COPY setup.sh /usr/local/bin/app-setup.sh
RUN chmod 755 /usr/local/bin/app-setup.sh
