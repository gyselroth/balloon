#!/bin/bash
cwd=$(pwd)
echo "# INSTALL & CONFIGURE BALLOON"
echo "## INSTALL BUILD DEPENDENCIES"

export BALLOON_VERSION=$(cat /srv/www/balloon/VERSION)
if [ ${BALLOON_VERSION:0:1} -lt 2 ]
then
    echo "ERROR: Dockerfile requires a balloon version >= 2.0.0"
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get --no-install-recommends install -y git npm make
# create node alias for nodejs binary
update-alternatives --install /usr/bin/node node /usr/bin/nodejs 10
npm -g install apidoc

echo "## INSTALL COMPOSER"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('SHA384', 'composer-setup.php') === '544e09ee996cdf60ece3804abc52599c22b1f40f4323403c44d44fdfdd586475ca9813a858088ffbc1f233e9b180f061') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php --filename=composer --install-dir=bin
php -r "unlink('composer-setup.php');"

echo "# INSTALL BALLOON"
balloonDir="/srv/www/balloon"
cd $balloonDir

echo "## BUILD BALLOON"
# build balloon
make build

cd $cwd

echo "## CONFIGURE BALLOON"
# create self-signed ssl certificate
mkdir /etc/ssl/balloon.local
openssl genrsa -des3 -passout pass:balloon -out server.pass.key 2048
openssl rsa -passin pass:balloon -in server.pass.key -out key.pem
rm server.pass.key
openssl req -new -key key.pem -out server.csr \
  -subj "/C=CH/L=Zurich/O=Balloon/CN=balloon.local"
openssl x509 -req -days 365 -in server.csr -signkey key.pem -out chain.pem
rm server.csr
mv key.pem /etc/ssl/balloon.local/
mv chain.pem /etc/ssl/balloon.local/

# bootstrap nginx config
rm /etc/nginx/sites-enabled/default
cp $balloonDir/packaging/nginx.site.conf /etc/nginx/sites-enabled/balloon.local
sed -i "s#/path/to/vhost#$balloonDir#g" /etc/nginx/sites-enabled/balloon.local
sed -i "s#/path/to/ssl#/etc/ssl/balloon.local#g" /etc/nginx/sites-enabled/balloon.local
sed -i "s#FQDN#balloon.local#g" /etc/nginx/sites-enabled/balloon.local

# create logdir
touch $balloonDir/log/out.log

# fix permissions
chown -R  www-data:www-data $balloonDir

# install cli daemon
cp $balloonDir/packaging/systemd.service /etc/systemd/system/balloon-cli.service
systemctl enable balloon-cli.service

echo "## CLEANUP"
# cleanup build deps
cd $balloonDir
make mostlyclean
cd $cwd
# apt cleanup
apt-get purge -y git npm make
rm -rf /usr/local/lib/node_modules/
apt-get autoremove -y
rm -rf /var/lib/apt/lists/*
apt-get clean
