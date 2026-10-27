FROM ghcr.io/dwyschka/localkit-docker:latest

USER root
RUN docker-php-serversideup-s6-init

# ffmpeg is how localkit talks to the cameras: the devices only serve RTSP,
# which no browser can play, so ffmpeg grabs the still frames for snapshots and
# thumbnails (CameraThumbnailController, TakeSnapshot) and remuxes the stream to
# MPEG-TS for the live view (CameraLiveController).
RUN apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg \
    && rm -rf /var/lib/apt/lists/*

COPY --chmod=755 ./entrypoint.d/ /etc/entrypoint.d/
COPY --chmod=644 ./certs/server.crt /etc/ssl/private/self-signed-web.crt
COPY --chmod=644 ./certs/server.key /etc/ssl/private/self-signed-web.key

COPY --chmod=755 s6/services/ /etc/s6-overlay/s6-rc.d/
# Service definitions stay in s6-rc.d (unchanged in v5), but the user bundle
# that starts them moved: serversideup/php v5 ships S6 Overlay 3.2.3.2, which
# defines the 'user' and 'user2' bundles in /etc/s6-overlay/user-bundles.d/.
# /etc/s6-overlay/s6-rc.d/user must NOT be recreated here - while it exists S6
# ignores user-bundles.d entirely and php-fpm and the web server never start
# (serversideup v4->v5 migration guide). docker-php-serversideup-s6-init above
# already populates the new location.
COPY --chmod=755 s6/user/contents.d/ /etc/s6-overlay/user-bundles.d/user/contents.d/

USER www-data

COPY --chown=www-data:www-data . /var/www/html
RUN mv /var/www/html/.env.example /var/www/html/.env
RUN chown www-data:www-data /var/www/html/storage/logs
RUN chown www-data:www-data /var/www/html/storage/app
RUN chown www-data:www-data /var/www/html/storage/database
RUN composer install
