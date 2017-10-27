#!/bin/bash

echo "# INSTALL CRON"
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install --no-install-recommends -y cron

# apt cleanup
rm -rf /var/lib/apt/lists/*
apt-get clean
