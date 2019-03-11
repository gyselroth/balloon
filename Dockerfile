FROM php:7.2-fpm

RUN apt-get update && apt-get install -y \
  libldb-dev \
  libldap2-dev \
  libxml2-dev \
  libcurl4-openssl-dev \
  libssl-dev \
  libzip-dev \
  libicu-dev \
  libmagickwand-dev \
  wget \
  gnupg \
  smbclient \
  libsmbclient-dev \
  && rm -rf /var/lib/apt/lists/*

RUN ln -s /usr/lib/x86_64-linux-gnu/libldap.so /usr/lib/libldap.so
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
COPY config/config.yaml.docker.dist /etc/balloon/config.docker.yaml
RUN ln -s /usr/share/balloon/bin/console/ballooncli /usr/bin/ballooncli

ENV BALLOON_PATH /usr/share/balloon
ENV BALLOON_DIR_CONFIG /etc/balloon

EXPOSE 443 9000
CMD sleep 300
#CMD nohup ballooncli jobs listen -vv && service nginx start && php-fpm;
