# balloon

[![Build Status](https://travis-ci.org/gyselroth/balloon.svg)](https://travis-ci.org/gyselroth/balloon)
[![GitHub release](https://img.shields.io/github/release/gyselroth/balloon.svg)](https://github.com/gyselroth/balloon/releases)
[ ![Download](https://api.bintray.com/packages/gyselroth/balloon/balloon/images/download.svg) ](https://bintray.com/gyselroth/balloon/balloon/_latestVersion) 
 [![GitHub license](https://img.shields.io/badge/license-GPL-blue.svg)](https://raw.githubusercontent.com/gyselroth/balloon/master/LICENSE)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/gyselroth/balloon/badges/quality-score.png)](https://scrutinizer-ci.com/g/gyselroth/balloon)

<p align="center">
    <img src="https://raw.githubusercontent.com/gyselroth/balloon-screenshots/master/src/tree.png"/>
    (This shows the <a href="https://github.com/gyselroth/balloon-client-web">balloon-client-web</a> since it is more appealing than showing the server :) )
</p>

* High performance server, horizontally scalable, easy clustering
* Using MongoDB which is highly scalable and super fast
* WebDAV Support (Support for network drives on Windows and other clients)
* Various DMS features like tagging, meta data, sharing, file history and more
* Sharing for user and groups with different levels of permissions (manager, read-write, readonly, mailbox)
* Comes with cloud native support and deploy ready kubernetes resources
* Deployable out of the box for debian (deb), tar archive, docker images, kubernetes and more
* App system for 3rd party apps
* The core is shipped with various core apps pre-installed
* File previews for various formats (including office documents, pdf, text, images, markdown, html and more)
* Notification system (including mail notifications)
* Full text search using [Elasticsearch](https://github.com/elastic/elasticsearch)
* Malware scanning using [ClamAV](https://github.com/Cisco-Talos/clamav-devel)
* Full WOPI support (Tested with Libre Office Online and Microsoft Office Online)
* Automatically convert files (shadow nodes) to other file formats (for example keep an automatic pdf file for a word file)
* Automatically destroy files/folders at a certain time
* Mount external storage (Currently only SMB/CIFS is supported since v2.1.0)
* Eventlog
* Integrated deduplication system
* Burl (URL file format, including rendered site previews)
* Globally accessible share links
* Intelligent collections based on custom rules
* Multiple types of authentication, basic auth (internal users), basic auth LDAP, OpenID-Connect, WebAuthn support
* Integrated OpenID-connect server and also supports OpenID-connect for any external OpenID-Connect providers (including google, microsoft, github and more)
* Support for Google reCaptcha v2 (Anti bruteforce account security)
* REST API (OpenAPI v3 specs)
* User quotas
* [Official Web UI](https://github.com/gyselroth/balloon-client-web)
* [Official Desktop client](https://github.com/gyselroth/balloon-client-desktop) for Windows, Linux and OS X
* Rich eco system (including various sdk's)
* ... and much more

# Documentation
Interested? How to deploy? Please visit the [documentation](https://gyselroth.github.io/balloon-docs/).

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
