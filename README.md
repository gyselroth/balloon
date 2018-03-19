# balloon

[![Build Status](https://travis-ci.org/gyselroth/balloon.svg)](https://travis-ci.org/gyselroth/balloon)
[![GitHub release](https://img.shields.io/github/release/gyselroth/balloon.svg)](https://github.com/gyselroth/balloon/releases)
[ ![Download](https://api.bintray.com/packages/gyselroth/balloon/balloon/images/download.svg) ](https://bintray.com/gyselroth/balloon/balloon/_latestVersion) 
 [![GitHub license](https://img.shields.io/badge/license-GPL-blue.svg)](https://raw.githubusercontent.com/gyselroth/balloon/master/LICENSE)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/gyselroth/balloon/badges/quality-score.png)](https://scrutinizer-ci.com/g/gyselroth/balloon)
[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bgithub.com%2Fgyselroth%2Fballoon.svg?type=shield)](https://app.fossa.io/projects/git%2Bgithub.com%2Fgyselroth%2Fballoon?ref=badge_shield)

<p align="center">
    <img src="https://raw.githubusercontent.com/gyselroth/balloon-client-desktop/master/app/img/balloon-startup.png"/>
</p>

## Features

* Virtual Filesystem based on MongoDB which is highly scalable
* Multiple types of authentication, local (MongoDB), LDAP, OpenID-Connect
* REST API
* WebDAV Support (Support for network drives on Windows)
* Various DMS features like tagging, meta data, sharing, file history and more
* Sharing for user and groups with different levels of permissions (manager, read-write, readonly, mailbox)
* Event logging and possibiliy of undoing an event
* Integrated app system to support 3rd party apps
* The core is shipped with various core apps pre-installed
* Integrated deduplication system to save your storage
* User quotas
* File previews (core app)
* Notifications (core app)
* Full text search via Elasticsearch (core app)
* Malware scanning via ClamAV (core app)
* WOPI support for libre office online and Office 365 (ongoing) (core app)
* Automatically let your files convert to other file formats (Keep an auotmatic updated pdf file for a word file for example) (core app)
* Automatically destroy files/folders at a certain time
* Globally accessible share links (core app)
* Task scheduler
* Cluster/Distributed system support
* Support for cloud native deployment
* Packaged for debian and as docker image
* ... and much more

## Web UI
There is a modern web based user interface for balloon! It does support all features and integrates smoothly with the balloon server.
Check out the balloon web ui on [gyselroth/balloon-client-web](https://github.com/gyselroth/balloon-client-web).

## Desktop Client
Of course there is also a complete desktop solution for balloon. The desktop clients brings your cloud onto your desktop for Windows, Mac OS X and Linux.
It can sync your entire cloud and more, checkout the balloon desktop client on [gyselroth/balloon-client-desktop](https://github.com/gyselroth/balloon-client-desktop).

## Changelog
A changelog is available [here](https://github.com/gyselroth/balloon/CHANGELOG.md).

## Upgrade
Upgrading from an older version of balloon? Please note the [changelog](https://github.com/gyselroth/balloon/blob/master/CHANGELOG.md) and follow the instructions given 
in the [upgrade guide](https://github.com/gyselroth/balloon/blob/master/UPGRADE.md).

## Contribute
We are glad that you would like to contribute to this project. Please follow the given [terms](https://github.com/gyselroth/balloon/blob/master/CONTRIBUTING.md).
