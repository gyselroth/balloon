#!/bin/bash

echo "# INSTALL NGINX"
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install --no-install-recommends -y nginx-full

# apt cleanup
rm -rf /var/lib/apt/lists/*
apt-get clean
