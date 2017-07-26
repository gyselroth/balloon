# balloon

[![Build Status](https://travis-ci.org/gyselroth/balloon.svg?branch=master)](https://travis-ci.org/gyselroth/balloon)
[![Dependency Status](https://www.versioneye.com/user/projects/594a42e9368b080044d19efa/badge.svg?style=flat-square)](https://www.versioneye.com/user/projects/594a42e9368b080044d19efa)

balloon is a cloud server and document management system.

## Features

* Virtual Filesystem based on MongoDB which is highly scalable
* Higly configurable via XML configuration
* Multiple types of authentication, local (MongoDB), LDAP or OAUTH2. 
* HTTP REST API
* WebDAV
* Multilangual webinterface
* High performance webinterface which is completely written in javascript
* Various DMS features like tagging, meta data, sharing, file history and more
* Event logging and possibiliy of undo an event
* Integrated app system
* File previews
* Multiple log adapter and detailed log files
* Webinterface with keyboard navigation, drag&drop, upload files via drag&drop, video&audio player and more
* LDAP integration for authentication, user sync, automatically deployed shares and more
* Fully responsive webinterface
* Notifications
* Automatically destroy files/folders at a certain time
* Globally accessible share links
* ...

## What else?

There are very usefefull apps available (fully supported by the core but not distributed with it):

### LibreOffice

The office app introduces libreoffice (collabora) for balloon. Meaning this is real integration of a full office suite within your personal or business cloud. It does support read and write all types of office formats and also features collaborative editing sessions.

See the [balloon-app-office](https://github.com/gyselroth/balloon-app-office) repository for further information.

### Elasticsearch

Elasticsearch provides full text search over all stored documents. The elasticsearch balloon app makes use of that and transperantly replaces the core search mechanism of meta data only.

See the [balloon-app-elasticsearch](https://github.com/gyselroth/balloon-app-elasticsearch) repository for further information.

## Requirements

* GNU/Linux Server
* Webserve
* PHP 7.1.x
* MongoDB

## Docker image

There is a Dockerfile for a ready-to-go docker image in the [balloon-dockerimage](https://github.com/gyselroth/balloon-dockerimage) repository.
