# balloon

[![Build Status](https://travis-ci.org/gyselroth/balloon.svg?branch=dev)](https://travis-ci.org/gyselroth/balloon)
[![GitHub release](https://img.shields.io/github/release/gyselroth/balloon.svg)](https://github.com/gyselroth/balloon/releases)
[ ![Download](https://api.bintray.com/packages/gyselroth/balloon/balloon/images/download.svg) ](https://bintray.com/gyselroth/balloon/balloon/_latestVersion) 
 [![GitHub license](https://img.shields.io/badge/license-GPL-blue.svg)](https://raw.githubusercontent.com/gyselroth/balloon/master/LICENSE)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/gyselroth/balloon/badges/quality-score.png?b=dev)](https://scrutinizer-ci.com/g/gyselroth/balloon/?branch=dev)
[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bgithub.com%2Fgyselroth%2Fballoon.svg?type=shield)](https://app.fossa.io/projects/git%2Bgithub.com%2Fgyselroth%2Fballoon?ref=badge_shield)

<p align="center">
    <img src="https://raw.githubusercontent.com/gyselroth/balloon-client-desktop/master/app/img/balloon-startup.png"/>
</p>

## Features

* Virtual Filesystem based on MongoDB which is highly scalable
* Multiple types of authentication, local (MongoDB), LDAP, OpenID-Connect
* HTTP API
* WebDAV Support (Support for network drives on Windows)
* Various DMS features like tagging, meta data, sharing, file history and more
* Sharing for user and groups with different levels of permissions (manager, read-write, readonly, mailbox)
* Event logging and possibiliy of undoing an event
* Integrated app system to support 3rd party apps
* The core is shipped with various core apps pre-installed
* Full LDAP integration for authentication and user/group sync
* Integrated deduplication system to save your storage
* User quotas
* File previews (core app)
* Notifications (core app)
* Automatically let your files convert to other file formats (Keep an auotmatic updated pdf file for a word file for example)
* Automatically destroy files/folders at a certain time
* Globally accessible share links 
* Task scheduler
* Cluster/Distributed system support
* Support for cloud native deployment
* Packaged for debian and as docker image
* ... and much more

## What else?

Here are some other core apps which are also shipped by default.

### LibreOffice - Collaborative webinterface integration

The office app introduces libreoffice (collabora) for balloon. Meaning this is real integration of a full office suite within your personal or business cloud. It does support read and write all types of office formats and also features collaborative editing sessions.

### Elasticsearch - Fulltext search

Elasticsearch provides full text search over all stored documents. The elasticsearch balloon app makes use of that and transperantly replaces the core search mechanism of meta data only.

### ClamAV - Antivirus engine

Autmatically scan your uploaded files in the background for viruses and other malware.

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
