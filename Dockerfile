FROM php:7.3-fpm-alpine

RUN apk update && apk add --no-cache \
  ldb-dev \
  openldap-dev \
  libxml2-dev \
  curl-dev \
  openssl-dev \
  libzip-dev \
  curl-dev \
  icu-dev \
  samba-dev \
  samba-client \
  autoconf \
  nginx \
  g++ \
  git \
  make \
  curl \
  freetype-dev libpng-dev libjpeg-turbo-dev \
  imagemagick \
  imagemagick-dev

RUN docker-php-ext-install ldap xml opcache curl zip intl sockets pcntl sysvmsg

RUN pecl install mongodb \
    && pecl install apcu \
    && pecl install imagick \
    && pecl install smbclient \
    && docker-php-ext-enable mongodb apcu imagick smbclient

RUN mkdir /usr/share/balloon && mkdir /usr/share/balloon/bin/console -p && mkdir /etc/balloon
COPY src/lib /usr/share/balloon/src/lib
COPY src/app /usr/share/balloon/src/app
COPY vendor /usr/share/balloon/vendor
COPY src/.container.config.php /usr/share/balloon/src
COPY src/cgi-bin/cli.php /usr/share/balloon/bin/console/ballooncli
COPY src/httpdocs /usr/share/balloon/bin/httpdocs
COPY config/config.yaml.dist /etc/balloon/
RUN ln -s /usr/share/balloon/bin/console/ballooncli /usr/bin/ballooncli

ENV BALLOON_PATH /usr/share/balloon
ENV BALLOON_CONFIG_DIR /etc/balloon

EXPOSE 443 9000
CMD nohup ballooncli jobs listen -vv && service nginx start && php-fpm;
