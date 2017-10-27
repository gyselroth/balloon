#!/bin/bash

echo "# INSTALL & CONFIGURE PHP"
export DEBIAN_FRONTEND=noninteractive
# install wget
apt-get update
apt-get install --no-install-recommends -y wget

# add ppa and key
echo "deb http://ppa.launchpad.net/ondrej/php/ubuntu xenial main" > /etc/apt/sources.list.d/php.list
wget -qO- "http://keyserver.ubuntu.com:11371/pks/lookup?op=get&search=0x4F4EA0AAE5267A6C" | sed -n '/^-----.*/,/^-----.*/p' | apt-key add -

# need to apt-get update because of added source
apt-get update
apt-get install --no-install-recommends -y php7.1 php7.1-fpm php7.1-ldap php7.1-xml php7.1-mongodb php7.1-curl php7.1-imagick php7.1-mbstring php7.1-zip php7.1-intl php7.1-apc

# configure fpm pool
sed -i 's#^listen = /run/php/php7.1-fpm.sock#listen = 127.0.0.1:9001#g' /etc/php/7.1/fpm/pool.d/www.conf

# apt cleanup
## remove wget
apt-get purge -y wget
apt-get autoremove -y
rm -rf /var/lib/apt/lists/*
apt-get clean
