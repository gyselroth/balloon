#!/bin/bash

echo "# INSTALL LIBREOFFICE"
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install --no-install-recommends -y libreoffice

# apt cleanup
rm -rf /var/lib/apt/lists/*
apt-get clean
