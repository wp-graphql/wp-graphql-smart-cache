ARG WP_VERSION
ARG PHP_VERSION

FROM wp-graphql:latest-wp${WP_VERSION}-php${PHP_VERSION}

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod 755 /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
