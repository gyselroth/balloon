#!/bin/bash

echo "# INSTALL CRON"
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y cron

# apt cleanup
rm -rf /var/lib/apt/lists/*
apt-get clean
