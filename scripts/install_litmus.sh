#!/bin/sh

git clone https://github.com/tolsen/litmus
cd litmus
./configure
make install
cd ..
rm -rfv litmus
