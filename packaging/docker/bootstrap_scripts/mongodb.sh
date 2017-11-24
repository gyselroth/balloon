#!/bin/bash

echo "# INSTALL MONGODB & CONFIGURE"
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install --no-install-recommends -y mongodb

# create admin user
echo "## CREATE initial admin user (admin:admin)"
service mongodb start
ping -n2 127.0.0.1 &> /dev/null
/usr/local/bin/createUserMongoDB admin admin admin
service mongodb stop

# apt cleanup
rm -rf /var/lib/apt/lists/*
apt-get clean
