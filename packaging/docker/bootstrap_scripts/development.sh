#!/bin/bash
echo "# INSTALL BALLOON DEVELOPMENT DEPENDENCIES"

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get --no-install-recommends install -y git npm make
# create node alias for nodejs binary
update-alternatives --install /usr/bin/node node /usr/bin/nodejs 10
npm -g install apidoc
