#!/bin/bash

if [ "$DEV" == "yes" ]
then
    echo "# INSTALL MONGODB"
    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    apt-get install --no-install-recommends -y mongodb

    # apt cleanup
    rm -rf /var/lib/apt/lists/*
    apt-get clean
fi
