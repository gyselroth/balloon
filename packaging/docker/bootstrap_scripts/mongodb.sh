#!/bin/bash

echo "# INSTALL MONGODB"
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y mongodb

# apt cleanup
rm -rf /var/lib/apt/lists/*
apt-get clean
