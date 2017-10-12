# Contribute to balloon
Did you find a bug or would you like to contribute a feature? You are certainly welcome to do so.
Please always fill an [issue](https://github.com/gyselroth/balloon/issues/new) first to discuss the matter.
Do not start development without an open issue otherwise we do not know what you are working on. 

## Bug
If you just want to fill a bug report, please open your [issue](https://github.com/gyselroth/balloon/issues/new).
We are encouraged to fix your bug to provide best software in the opensource community.

## Security flaw
Do not open an issue for a possible security vulnerability, to protect yourself and others please contact <opensource@gyselroth.net>
to report your concern.

### Get the base
```
git clone https://github.com/gyselroth/balloon.git
```

### Container
The easiest way is to grab the balloon docker container and build it for development:
```
git clone https://github.com/gyselroth/balloon-dockerimage
cd balloon-dockerimage
docker build -t balloon .
cd ..
```

### Start server
Now you can start the server and inject the local balloon git reposiory into your docker container:
```
docker run -p 8081:443 -v /path/to/balloon:/srv/www/balloon balloon
```

### Install dependencies
To setup your development base you can make use of the the make buildtool to install all dependencies:
```
docker exec INSTANCE make -C /srv/www/balloon deps
```

### Make
Always execute make via `docker exec` if your are developing with the balloon docker image.


## Testsuite
Execute the testsuite before push:
```
make test
```

## Git
You can clone the repository from:
```
git clone https://github.com/gyselroth/balloon.git
```

## Building
Besides npm scripts like build and start you can use make to build this software. The following make targets are supported:
* `build` Build software, but do not package
* `clean` Clear build and dependencies
* `deb` Create debian packages
* `deps` Install dependencies
* `dist` Distribute (Create tar and deb packages)
* `tar` Create tar package
* `test` Execute testsuite
* `phpcs-fix` Execute phpcs
* `phpstan` Execute phpstan

## Git commit 
Please make sure that you always specify the number of your issue starting with a hastag (#) within any git commits.

## Pull Request
You are absolutely welcome to submit a pull request which references an open issue. Please make sure you're follwing coding standards 
and be sure all your modifications pass the build.
[![Build Status](https://travis-ci.org/gyselroth/balloon.svg?branch=v2)](https://travis-ci.org/gyselroth/balloon)

## Code of Conduct
Please note that this project is released with a [Contributor Code of Conduct](https://github.com/gyselroth/balloon/CODE_OF_CONDUCT.md). By participating in this project you agree to abide by its terms.

## License
This software is freely available under the terms of [GPL-3.0](https://github.com/gyselroth/balloon/LICENSE), please respect this license
and do not contribute software parts which are not compatible with GPL-3.0.

## Editor config
This repository gets shipped with an .editorconfig configuration. For more information on how to configure your editor please visit [editorconfig](https://github.com/editorconfig).

## Code policy
Please make sure that you're following:
* [PSR-1](http://www.php-fig.org/psr/psr-1/)
* [PSR-2](http://www.php-fig.org/psr/psr-2/)

Please also follow the following policy in addition to PSR-1 and PSR-2:

* Abstract classes named with an Abstract prefix: AbstractExample.php
* Interfaces named with an Interface suffix: ExampleInterface.php
* Traits named with a Trait prefix: TraitExample.php
* Variables named with underscore (_) and not camelCase
* All methods must declare return types whenever possible (except testsuite)
* All method parameters must be declared with strict types (string, int, bool, array) (except testsuite)
* All files delcare strict_types=1
* All methods must have a doctype (except testsuite)
* Every API controller must be documented with apidoc compatible doctags
* Designed with Dependency Injection pattern, no registries, no singletons, to static clases, no static methods
* yield whenever possible, do not use return where it makes sense to yield values
* log as many things as possible, clear understandable messsages (everything in lowercase), wrap variables in []
* Do not use empty() for array checks, use count() === 0 instead (multi threading compatibilty)
