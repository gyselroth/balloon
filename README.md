# balloon

[![Build Status](https://travis-ci.org/gyselroth/balloon.svg?branch=v2)](https://travis-ci.org/gyselroth/balloon)
[![GitHub release](https://img.shields.io/github/release/gyselroth/balloon.svg)](https://github.com/gyselroth/balloon/releases)
[![GitHub license](https://img.shields.io/badge/license-GPL-blue.svg)](https://raw.githubusercontent.com/gyselroth/balloon/master/LICENSE)

balloon is a cloud server and document management system.

## Features

* Virtual Filesystem based on MongoDB which is highly scalable
* Multiple types of authentication, local (MongoDB), LDAP, OpenID-Connect, PSK (Pre-Shared-Key). 
* Mighty HTTP API
* WebDAV Support (Support for network drives on Windows)
* Responsive and super fast SPA webinterface (Single Page Application)
* Multilangual webinterface
* Webinterface with keyboard navigation, drag&drop, upload files via drag&drop, video&audio player and more
* Various DMS features like tagging, meta data, sharing, file history and more
* Sharing for user and groups with different levels of permissions (manager, read-write, readonly, writeonly, mailbox)
* Event logging and possibiliy of undoing an event
* Integrated app system to support 3rd party apps
* The core is shipped with various core apps pre-installed
* Full LDAP integration for authentication, user sync, automatically deployed shares and more
* Integrated deduplication system to save your storage
* User quotas
* File previews (core app)
* Notifications (core app)
* Various other core apps, even features like WebDAV is a core app on its own and could be disabled
* Automatically destroy files/folders at a certain time
* Globally accessible share links 
* Multiple log adapter and detailed log files
* Configurable via XML configuration
* Asynchronous background mechanism (Available as a daemon) to not bother your users with long requests
* Cluster/Distributed system support
* ... And more

## What else?

There are very usefull apps available (fully supported by the core but not distributed with it):

### LibreOffice - Collaborative webinterface integration

The office app introduces libreoffice (collabora) for balloon. Meaning this is real integration of a full office suite within your personal or business cloud. It does support read and write all types of office formats and also features collaborative editing sessions.

See the [balloon-app-office](https://github.com/gyselroth/balloon-app-office) repository for further information.

### Elasticsearch - Fulltext search

Elasticsearch provides full text search over all stored documents. The elasticsearch balloon app makes use of that and transperantly replaces the core search mechanism of meta data only.

See the [balloon-app-elasticsearch](https://github.com/gyselroth/balloon-app-elasticsearch) repository for further information.

## Requirements

* GNU/Linux Server
* Webserver
* PHP 7.1.x
* MongoDB

## Installation
### From source
For installation from source, see the [wiki page](https://github.com/gyselroth/balloon/wiki/Install-balloon-from-source-(v2))

## Changelog
A changelog is available [here](https://github.com/gyselroth/balloon/CHANGELOG.md).

## Upgrade
Upgrading from an older version of balloon? Please note the [changelog](https://github.com/gyselroth/balloon/CHANGELOG.md) and follow the instructions given 
in the [upgrade guide](https://github.com/gyselroth/balloon/UPGRADE.md).

## Contribute
We are glad that you would like to contribute to this project. Please follow the given [terms](https://github.com/gyselroth/balloon/CONTRIBUTE.md).
