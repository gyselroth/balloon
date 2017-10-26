FROM ubuntu:16.04
ARG DEV=no
RUN mkdir -p /srv/www/balloon
COPY . /srv/www/balloon/
COPY packaging/docker/tools/* /usr/local/bin/
RUN /srv/www/balloon/packaging/docker/bootstrap_scripts/php.sh
RUN /srv/www/balloon/packaging/docker/bootstrap_scripts/mongodb.sh
RUN /srv/www/balloon/packaging/docker/bootstrap_scripts/nginx.sh
RUN /srv/www/balloon/packaging/docker/bootstrap_scripts/libreoffice.sh
RUN /srv/www/balloon/packaging/docker/bootstrap_scripts/cron.sh
RUN /srv/www/balloon/packaging/docker/bootstrap_scripts/balloon.sh
EXPOSE 80 443
CMD cron && service mongodb start && service php7.1-fpm start && /usr/sbin/nginx -g 'daemon off;'
