FROM ghcr.io/dwyschka/localkit-docker:latest

USER root
RUN docker-php-serversideup-s6-init

COPY --chmod=755 ./entrypoint.d/ /etc/entrypoint.d/
COPY --chmod=644 ./certs/server.crt /etc/ssl/private/self-signed-web.crt
COPY --chmod=644 ./certs/server.key /etc/ssl/private/self-signed-web.key

COPY --chmod=755 s6/services/ /etc/s6-overlay/s6-rc.d/
COPY --chmod=755 s6/user/contents.d/ /etc/s6-overlay/s6-rc.d/user/contents.d/

USER www-data

COPY --chown=www-data:www-data . /var/www/html
RUN mv /var/www/html/.env.example /var/www/html/.env
RUN chown www-data:www-data /var/www/html/storage/logs
RUN chown www-data:www-data /var/www/html/storage/app
RUN chown www-data:www-data /var/www/html/storage/database
RUN composer install
