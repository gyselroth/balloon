# balloon

[![Build Status](https://travis-ci.org/gyselroth/balloon.svg)](https://travis-ci.org/gyselroth/balloon)
[![GitHub release](https://img.shields.io/github/release/gyselroth/balloon.svg)](https://github.com/gyselroth/balloon/releases)
[ ![Download](https://api.bintray.com/packages/gyselroth/balloon/balloon/images/download.svg) ](https://bintray.com/gyselroth/balloon/balloon/_latestVersion) 
 [![GitHub license](https://img.shields.io/badge/license-GPL-blue.svg)](https://raw.githubusercontent.com/gyselroth/balloon/master/LICENSE)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/gyselroth/balloon/badges/quality-score.png)](https://scrutinizer-ci.com/g/gyselroth/balloon)

<p align="center">
    <img src="https://raw.githubusercontent.com/gyselroth/balloon-screenshots/master/src/tree.png"/>
</p>

* Various DMS features like tagging, meta data, sharing, file history and more
* Using MongoDB which is highly scalable and super fast
* WebDAV Support (Support for network drives on Windows)
* Sharing for user and groups with different levels of permissions (manager, read-write, readonly, mailbox)
* High performance server, horizontally scalable, easy clustering
* Deployable out of the box for debian (deb), tar archive, docker images, kubernetes and more
* User quotas
* App system for 3rd party apps
* The core is shipped with various core apps pre-installed
* File previews (core app, Balloon.App.Preview)
* Mail Notifications (core app, Balloon.App.Notificsation)
* Full text search using [Elasticsearch](https://github.com/elastic/elasticsearch) (core app, Balloon.App.Elasticsearch)
* Malware scanning using [ClamAV](https://github.com/Cisco-Talos/clamav-devel) (core app, Balloon.App.ClamAv)
* WOPI support for libre office online and Office 365 (ongoing) (core app, Balloon.App.Office)
* Automatically let your files convert to other file formats (Keep an auotmatic updated pdf file for a word file for example) (core app)
* Automatically destroy files/folders at a certain time
* Mount external storage (Currently only SMB/CIFS is supported since v2.1.0)
* Eventlog
* Integrated deduplication system to save your storage
* Globally accessible share links (core app, Balloon.App.Sharelink)
* Multiple types of authentication, local (MongoDB), LDAP, OpenID-Connect
* REST API
* ... and much more

## Documentation
Interested? How to deploy? Well please visit the [documentation](https://github.com/gyselroth/balloon/tree/master/docs).

## Web UI
There is a modern web based user interface for balloon! It does support all features and integrates smoothly with the balloon server.
Check out the balloon web ui on [gyselroth/balloon-client-web](https://github.com/gyselroth/balloon-client-web).

## Desktop Client
Of course there is also a complete desktop solution for balloon. The desktop clients brings your cloud onto your desktop for Windows, Mac OS X and Linux.
It can sync your entire cloud and more, checkout the balloon desktop client on [gyselroth/balloon-client-desktop](https://github.com/gyselroth/balloon-client-desktop).

## Changelog
A changelog is available [here](https://github.com/gyselroth/balloon/blob/master/CHANGELOG.md).

## Upgrade
Upgrading from an older version of balloon? Please note the [changelog](https://github.com/gyselroth/balloon/blob/master/CHANGELOG.md) and follow the instructions given 
in the [upgrade guide](https://github.com/gyselroth/balloon/blob/master/UPGRADE.md).

## Contribute
We are glad that you would like to contribute to this project. Please follow the given [terms](https://github.com/gyselroth/balloon/blob/master/CONTRIBUTING.md).

<p align="center">
    <img src="https://raw.githubusercontent.com/gyselroth/balloon-client-desktop/master/app/img/balloon-startup.png"/>
</p>
