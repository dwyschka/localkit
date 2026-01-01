FROM serversideup/php:8.3-fpm-nginx

ARG TARGETARCH
ARG TARGETPLATFORM

USER root
RUN install-php-extensions intl

# Install Go2rtc
RUN set -ex; \
    case "$TARGETPLATFORM" in \
        "linux/amd64")  ARCH="amd64" ;; \
        "linux/arm64")  ARCH="arm64" ;; \
        "linux/arm/v7") ARCH="armv7" ;; \
        *) echo "Unsupported platform: $TARGETPLATFORM" && exit 1 ;; \
    esac; \
    curl -fsSL -o /usr/local/bin/go2rtc \
        "https://github.com/AlexxIT/go2rtc/releases/latest/download/go2rtc_linux_${ARCH}"; \
    chmod +x /usr/local/bin/go2rtc
# Endinstall Go2rtc
# Entrypoint Scripts
COPY --chmod=755 ./entrypoint.d/ /etc/entrypoint.d/
RUN docker-php-serversideup-s6-init

# If you have your own long running services, copy them to the s6 directory
COPY --chmod=755 s6/services/ /etc/s6-overlay/s6-rc.d/
COPY --chmod=755 s6/user/contents.d/ /etc/s6-overlay/s6-rc.d/user/contents.d/

USER www-data


ENV AUTORUN_ENABLED=true
COPY --chown=www-data:www-data . /var/www/html
RUN mv /var/www/html/.env.example /var/www/html/.env
RUN chown www-data:www-data /var/www/html/storage/logs
RUN chown www-data:www-data /var/www/html/storage/app
RUN chown www-data:www-data /var/www/html/storage/database

USER www-data
