#!/bin/sh

git clone https://github.com/gyselroth/php-serializable-md5
cd php-serializable-md5
phpize
./configure
make install
echo "extension=smd5.so" >> `php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||"`
php --ini
php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||"
echo "SMD5:"
php -i | grep smd5
cd ..
rm -rfv php-serializable-md5
