FROM serversideup/8.3-fpm-nginx-alpine


COPY --chown=www-data:www-data . /var/www/html
