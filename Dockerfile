FROM php:7.3-fpm-alpine

RUN apk update && apk add --virtual .build-deps --no-cache \
  ldb-dev \
  openldap-dev \
  libxml2-dev \
  curl-dev \
  openssl-dev \
  libzip-dev \
  curl-dev \
  icu-dev \
  samba-dev \
  autoconf \
  gmp-dev \
  g++ \
  git \
  make \
  freetype-dev \
  libpng-dev \
  libjpeg-turbo-dev \
  imagemagick-dev \
  && apk add --no-cache libstdc++ \
  libzip \
  icu-libs \
  imagemagick \
  coreutils \
  curl \
  samba-client \
  && docker-php-ext-install ldap xml opcache curl zip intl sockets pcntl sysvmsg gmp \
  && pecl install mongodb \
  && pecl install apcu \
  # TODO: use imagick on php 7.4
  && pecl install imagick-3.4.4 \
  && pecl install smbclient \
  && docker-php-ext-enable mongodb apcu imagick smbclient \
  && git clone https://github.com/gyselroth/php-serializable-md5 \
  && docker-php-source extract \
  && cd php-serializable-md5 \
  && phpize \
  && ./configure \
  && make install \
  && echo "extension=smd5.so" > /usr/local/etc/php/conf.d/docker-php-ext-smd5.ini \
  && cd .. \
  && rm -rfv php-serializable-md5 \
  && apk del .build-deps

RUN mkdir /usr/share/balloon && mkdir /usr/share/balloon/bin/console -p && mkdir /etc/balloon
COPY src/lib /usr/share/balloon/src/lib
COPY src/app /usr/share/balloon/src/app
COPY vendor /usr/share/balloon/vendor
COPY src/.container.config.php /usr/share/balloon/src
COPY src/cgi-bin/cli.php /usr/share/balloon/bin/console/ballooncli
COPY src/httpdocs /usr/share/balloon/bin/httpdocs
COPY config/config.yaml.dist /etc/balloon/
RUN ln -s /usr/share/balloon/bin/console/ballooncli /usr/bin/ballooncli
RUN mkdir /var/cache/samba/msg.lock

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && echo "expose_php=0" >> "$PHP_INI_DIR/php.ini"

USER 1000:1000

ENV BALLOON_PATH /usr/share/balloon
ENV BALLOON_CONFIG_DIR /etc/balloon
