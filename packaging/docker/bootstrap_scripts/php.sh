#!/bin/bash

echo "# INSTALL PHP"
export DEBIAN_FRONTEND=noninteractive
# install wget
apt-get update
apt-get install --no-install-recommends -y wget

# add ppa and key
echo "deb http://ppa.launchpad.net/ondrej/php/ubuntu xenial main" > /etc/apt/sources.list.d/php.list
wget -qO- "http://keyserver.ubuntu.com:11371/pks/lookup?op=get&search=0x4F4EA0AAE5267A6C" | sed -n '/^-----.*/,/^-----.*/p' | apt-key add -

# need to apt-get update because of added source
apt-get update
apt-get install -y php7.1 php7.1-ldap php7.1-xml php7.1-mongodb php7.1-opcache php7.1-curl php7.1-imagick php7.1-cli php7.1-zip php7.1-intl php7.1-apc php7.1-fpm php7.1-mbstring

# apt cleanup
## remove wget
apt-get purge -y wget
apt-get autoremove -y
rm -rf /var/lib/apt/lists/*
apt-get clean
