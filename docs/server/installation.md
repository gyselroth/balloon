# Installation

This is a step-by-step tutorial how to correctly deploy the balloon server and the [balloon web](github.com/gyselroth/balloon-client-web) based user interface.
This tutorial includes the web interface, of course you may also just install the server components only. The web ui acts as a balloon client and is completely optional.

There are multiple supported ways to deploy balloon:

* Docker (docker-compose)
* [Kubernetes](https://kubernetes.io/docs/concepts/overview/what-is-kubernetes/) using [helm](https://github.com/gyselroth/balloon-helm)
* Manually to [Kubernetes](https://kubernetes.io/docs/concepts/overview/what-is-kubernetes/)
* Classic way from debian package via apt
* Manually from tar archive
* Compile manually from scratch

The docker/kubernetes deployment is the **recommended** way to deploy balloon. And it is also the simplest way.

## Docker (docker-compose)

The easiest and fastest way to deploy a balloon environment is to spin it up using docker and docker-compose.
Since the installation is not the same for different host os and docker can be started on Linux, Windows and Mac please visit 
the docker documentation on how to install [docker](https://docs.docker.com/install) and [docker-compose](https://docs.docker.com/compose/install).

Now a docker-compose file is required with all required containers by balloon.
Create a file named `balloon-stable.yaml` with this content:

**Requirements**:

* docker
* docker-compose
* curl

```
mkdir balloon; cd balloon
curl https://github.com/gyselroth/balloon/blob/master/packaging/docker-compose/docker-compose.yaml > docker-compose.yaml
docker-compose up
```

>**Note** All balloon containers provide a version tag besides `latest`. It is best practice to use an exact version of a service instead the latest tag in production environment.
The containers provide a `latest-unstable` tag for the balloon-jobs, balloon and balloon-web container. It is in no way reccomened to use pre-releases in production environments! 
If you want to install beta and alpha versions replace `latest` with `latest-unstable` or specify an exact version tag. Pre-releases are only ment for testing purposes and are in no way recommended in production environements!


The balloon web interface is now available at `http://localhost`.

Username: admin <br/>
Password: admin <br/>

## Deploy on kubernetes (helm)

See the complete documentation for balloon helm [here](https://github.com/gyselroth/balloon-helm).

To install the chart with the release name `my-release`:

```console
helm repo add balloon https://gyselroth.github.io/balloon-helm/stable
helm install balloon/balloon --name my-release --namespace mynamespace
```

Example deployment with ingress/tls enabled:

```console
helm install balloon/balloon --name my-release --namespace mynamespace \
    --set balloon-proxy.ingress.enabled=true \ 
    --set balloon-web.ingress.enabled=true \ 
    --set balloon-proxy.ingress.host=balloon.local \
    --set balloon-web.ingress.host=balloon.local \
    --set balloon-web.ingress.tls[0].secretName=tls-balloon.local \
    --set balloon-proxy.ingress.tls[0].secretName=tls-balloon.local \
    --set balloon.url=https://balloon.local
```

## Deploy on kubernetes (manually)

>**Note** Using helm to deploy balloon on kubernetes is the preferred way.


## Debian based distribution

Both the server and the web ui get distributed as .deb packages to make it easy to install and upgrade.

**Requirements**:

* Debian based linux distribution

You need a running debian based linux distribution. This can be [debian](https://www.debian.org) itself or debian based distribution like [Ubuntu](https://www.ubuntu.com). You may also convert the package using `alien` to rpm and other package formats. 
If you are not sure how to deploy such a server please visit the documentation of those distributions as this is out of the scope of this documentation.

This tutorial describes how to install all balloon components on the same server. In production environments this may not be the best way and it is neither scalable nor performant. You certainly can deploy all components on different server. The balloon server is fully scalable and can be scaled horizontally as well as can the required components like MongoDB and Elasticsearch (Elasticsearch is optional since it is a core app and my be disabled). You can also deploy multiple web ui instances if you have to. Everything will scale easlily to your needs.

### Package Repository

You need to add the balloon repository to your package management configuration as well as repositories for the latest PHP and MongoDB releases.
The following commands must be executed with `root` permissions unless noted otherwise.

#### balloon
```sh
apt-get -y install apt-transport-https
echo "deb https://dl.bintray.com/gyselroth/balloon stable main" | sudo tee -a /etc/apt/sources.list
wget -qO - https://bintray.com/user/downloadSubjectPublicKey?username=gyselroth | sudo apt-key add -
sudo apt-get update
```

>**Note** If you want to install beta and alpha versions replace `stable` with `unstable`. Pre-releases are only ment for testing purposes and are in no way recommended in production environements!
This repository also includes the web client and the [desktop client](github.com/gyselroth/balloon-client-web).

#### PHP
The balloon server requires PHP 7.3. If your current distribution does not provide 7.3 out of their stable archives (which is most certainly the case) please add the PPA ppa:ondrej/php which will provide the latest PHP 7.3 releases.

```sh
sudo apt-get install lsb-release ca-certificates
sudo wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg
echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/php.list
sudo apt-get update
```

#### MongoDB
balloon uses MongoDB as its main database. At least MongoDB 3.4 is required. If your current distribution does not ship at least this release you will need to add the official MongoDB repository.

>**Note** MongoDB recommends to use the official MongoDB repository anyway since the releases in the debian and or ubuntu repositories are not maintained by them and lack newer minor releases.

```sh
sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv 2930ADAE8CAF5059EE73BB4B58712A2291FA4AD5
echo "deb http://repo.mongodb.org/apt/debian jessie/mongodb-org/3.6 main" | sudo tee /etc/apt/sources.list.d/mongodb-org-3.6.list
sudo apt-get update
``` 

>**Note** This will add the repository for debian jessie, if you need another repository please refer to the [MongoDB installation](https://docs.mongodb.com/manual/administration/install-on-linux/) docs.

#### Elasticsearch
Elasticsearch is not shipped in any official linux distribution archives therefore it is required to add this repository as well.
This will install the latest elasticsearch of the 6.x series.

```sh
wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch | sudo apt-key add -
echo "deb https://artifacts.elastic.co/packages/6.x/apt stable main" | sudo tee -a /etc/apt/sources.list.d/elastic-6.x.list
sudo apt-get update
```

#### Libreoffice online

```sh
sudo echo 'deb https://www.collaboraoffice.com/repos/CollaboraOnline/CODE-debian9 ./' >> /etc/apt/sources.list
sudo apt-get update
```

### Install balloon
Now balloon and its components can be installed.
```
apt-get install mongodb-org elasticsearch libreoffice clamav loolwsd code-brand balloon balloon-web
```

>**Note** ClamAV, Elasticsearch and LibreOffice are optional balloon components and are used in the core apps Balloon.App.ClamAV, Balloon.App.Elasticsearch and Balloon.App.Preview.

After all packages have been installed the balloon web interface is reachable at `http://localhost`.
The installation will create a default admin account:

Username: admin<br/>
Password: admin<br/>

## Using the tar archive

The tar archive contains a builded version of the balloon server.
You need to install all server components manually. Note that using the deb packages is the preferred way while not using docker.


## Manually install from source

This topic is only for advanced users or developers and describes how to deploy balloon by installing from source.
If you are a developer please also continue reading [this](https://github.com/gyselroth/balloon/blob/master/CONTRIBUTING.md) article.

**Requirements**:

* posix based operating system (Basically every linux/unix)
* make
* [comoser](https://getcomposer.org/download/)
* git
* php >= 7.3
* php ext-mongodb
* php ext-curl
* php ext-mbstring
* php ext-intl
* php ext-zip
* php ext-posix
* php ext-pnctl

**Optional requirements**:

* php ext-apc (Used in \Micro\Auth to cache discovery metadata)
* php ext-imagick (Used in Balloon.App.Preview)
* php ext-ldap (Used for LDAP authentication adapter in Micro\Auth)
* php ext-smb (Used for SMB external storage)

This will only install the balloon server and the balloon web client. Dependencies such as MongoDB, LibreOffice or Elasticsearch wont get installed.
You can install those dependencies either bei using distributed packages, see [Debian based distribution](#debian-based-distribution) or by installing them seperately from source.

### Install balloon server
```sh
git clone https://github.com/gyselroth/balloon.git
cd balloon
make install
```

>**Note** You can also create .deb or .tar packages using make. Just execute either `make deb` or `make tar` or `make dist` for both.

### Install balloon web client
```sh
git clone https://github.com/gyselroth/balloon-client-web.git
cd balloon-client-web
make install
```
