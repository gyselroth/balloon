FROM ubuntu:16.04
RUN mkdir -p /srv/www/balloon
COPY . /srv/www/balloon
COPY packaging/docker/tools/* /usr/local/bin/
RUN /srv/www/balloon/packaging/docker/bootstrap_scripts/php.sh
RUN /srv/www/balloon/packaging/docker/bootstrap_scripts/libreoffice.sh
RUN /srv/www/balloon/packaging/docker/bootstrap_scripts/nginx.sh
ARG DEV=no
RUN /srv/www/balloon/packaging/docker/bootstrap_scripts/mongodb.sh
RUN /srv/www/balloon/packaging/docker/bootstrap_scripts/balloon.sh
EXPOSE 80 443
CMD service mongodb start 2> /dev/null; /srv/www/balloon/src/cgi-bin/cli.php async -q -d; service php7.1-fpm start && /usr/sbin/nginx -g 'daemon off;'
