#!/bin/bash

echo "# INSTALL & CONFIGURE BALLOON"
echo "## INSTALL BUILD DEPENDENCIES"
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y git npm jq yui-compressor 
# create node alias for nodejs binary
update-alternatives --install /usr/bin/node node /usr/bin/nodejs 10
npm -g install bower apidoc

echo "# INSTALL BALLOON"
balloonDir="/srv/www/balloon"

echo "## BUILD BALLOON"
# build balloon
$balloonDir/build.sh --dep --minify --apidoc --php-cs-fixer

# create self-signed ssl certificate
mkdir /etc/ssl/balloon.local
openssl genrsa -des3 -passout pass:x -out server.pass.key 2048
openssl rsa -passin pass:x -in server.pass.key -out key.pem
rm server.pass.key
openssl req -new -key key.pem -out server.csr \
  -subj "/C=CH//L=Zurich/O=Balloon/CN=balloon.local"
openssl x509 -req -days 365 -in server.csr -signkey key.pem -out chain.pem
rm server.csr
mv key.pem /etc/ssl/balloon.local/
mv chain.pem /etc/ssl/balloon.local/

echo "## CONFIGURE BALLOON"
# bootstrap nginx config
rm /etc/nginx/sites-enabled/default
cp $balloonDir/dist/nginx.site.conf /etc/nginx/sites-enabled/balloon.local
sed -i "s#/path/to/vhost#$balloonDir#g" /etc/nginx/sites-enabled/balloon.local
sed -i "s#/path/to/ssl#/etc/ssl/balloon.local#g" /etc/nginx/sites-enabled/balloon.local
sed -i "s#FQDN#balloon.local#g" /etc/nginx/sites-enabled/balloon.local

# bootstrap configs
mkdir $balloonDir/config
cp $balloonDir/dist/config.xml $balloonDir/config/
cp $balloonDir/dist/cli.xml $balloonDir/config/
cp $balloonDir/dist/config.js $balloonDir/src/httpdocs/ui/

# create logdir
mkdir $balloonDir/log

chown -R  www-data:www-data $balloonDir

# define cronjob
echo "* * * * * www-data /usr/bin/php /srv/www/balloon/src/cgi-bin/cli.php &> /dev/null" > /etc/cron.d/balloon

# create admin user
if [ "$DEV" == "yes" ]
then
    echo "## CREATE initial admin user (admin:admin)"
    service mongodb start
    ping -n2 127.0.0.1 &> /dev/null
    /usr/local/bin/createUserMongoDB admin admin admin
    service mongodb stop
fi

echo "## CLEANUP"
# cleanup build deps
if [ "$DEV" == "no" ]
then
    rm -rf /usr/lib/node_modules/
    apt-get purge -y git npm jq yui-compressor 
fi
apt-get autoremove -y
# apt cleanup
rm -rf /var/lib/apt/lists/*
apt-get clean
