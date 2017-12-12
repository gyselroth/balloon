# balloon
[![GitHub release](https://img.shields.io/github/release/gyselroth/balloon.svg)](https://github.com/gyselroth/balloon/releases)
[![GitHub license](https://img.shields.io/badge/license-GPL-blue.svg)](https://raw.githubusercontent.com/gyselroth/balloon/master/LICENSE)
[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bgithub.com%2Fgyselroth%2Fballoon.svg?type=shield)](https://app.fossa.io/projects/git%2Bgithub.com%2Fgyselroth%2Fballoon?ref=badge_shield)

balloon is both a cloud server and a document management system

## Features

* Virtual Filesystem based on MongoDB and therefore highly scalable
* Higly configurable via XML configuration
* Multiple types of authentication possible: local (MongoDB), LDAP or OAUTH2. 
* HTTP REST API
* WebDAV
* Multilingual webinterface
* High performance webinterface seamlessly written in javascript
* Various DMS features like tagging, meta data, sharing, file history and many more
* Event logging and possibiliy of undoing an event
* Integrated app system
* File previews
* Multiple log adapter and detailed log files
* Webinterface with keyboard navigation, drag&drop, upload files via drag&drop, video&audio player and more
* LDAP integration for authentication, user sync, automatically deployed shares and more
* Fully responsive webinterface
* Notifications
* Possibility to automatically destroy files/folders at a certain time
* Globally accessible share links
* ...

## What else?

There are very useful apps available (fully supported by the core but not distributed with it):

### LibreOffice

The office app introduces libreoffice (collabora) for balloon: i.e. a real integration of a full office suite within your personal or business cloud, supporting read and write all types of office formats and also featuring collaborative editing sessions.

See the [balloon-app-office](https://github.com/gyselroth/balloon-app-office) repository for further information.

### Elasticsearch

Elastic search provides full text search over all stored documents. The elastic search balloon app makes use of that and transparently replaces the core search mechanism of meta data only.

See the [balloon-app-elasticsearch](https://github.com/gyselroth/balloon-app-elasticsearch) repository for further information.

## Requirements

* GNU/Linux Server
* Webserve
* PHP 7.1.x
* MongoDB

## Installation

For installation from source, see the [wiki page](https://github.com/gyselroth/balloon/wiki/Install-balloon-from-source-(v1))

## Docker image

There is a ready-to-go docker image on [Docker Hub](https://hub.docker.com/r/gyselroth/balloon/).


## License
[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bgithub.com%2Fgyselroth%2Fballoon.svg?type=large)](https://app.fossa.io/projects/git%2Bgithub.com%2Fgyselroth%2Fballoon?ref=badge_large)