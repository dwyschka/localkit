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
# The whole bundle directory, not just contents.d: without s6/user/type the
# 'user' bundle has no type file, and s6-rc.init tries to synthesise one at
# startup - which fails with "cannot create
# /etc/s6-overlay/s6-rc.d/user/type: Permission denied", because the
# directory is root-owned from this COPY while the container runs as
# www-data (below). Shipping the type file means nothing has to be written
# to /etc at runtime.
COPY --chmod=755 s6/user/ /etc/s6-overlay/s6-rc.d/user/

USER www-data

COPY --chown=www-data:www-data . /var/www/html
RUN mv /var/www/html/.env.example /var/www/html/.env
RUN chown www-data:www-data /var/www/html/storage/logs
RUN chown www-data:www-data /var/www/html/storage/app
RUN chown www-data:www-data /var/www/html/storage/database
RUN composer install
