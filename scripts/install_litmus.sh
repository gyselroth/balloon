#!/bin/sh

wget http://www.webdav.org/neon/litmus/litmus-0.13.tar.gz
tar xvzf litmus-0.13.tar.gz
cd litmus-0.13
./configure
make install
cd ..
rm -rfv litmus*
