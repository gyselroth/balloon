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

### Development
The recomended way to get started in development is to use the available docker images.
You need [docker](https://docs.docker.com/engine/installation/linux/docker-ce/debian/) and [docker-compose](https://docs.docker.com/compose/install/) installed on your local machine.

For starters you cann use the full stack development composing configuration `docker-compose-dev.yml`.
Start the development stack `docker-compose -f docker-compose-dev.yml up`.

Your balloon dev environment should now be running: 
```
curl -k -u admin:admin http://localhost:8081/api/v2/users/whoami?pretty
```

#### Make
Always execute make via `docker exec` if you are developing with the balloon docker image.

Update depenencies:
```
docker exec INSTANCE make -C /srv/www/balloon deps
```
>**Note**: You do not need to install dependencies manually, the dev container automatically installs all depencies during bootstrap)

See Building bellow for other make targets.

## Building
Besides npm scripts like build and start you can use make to build this software. The following make targets are supported:

* `build` Build software, but do not package
* `clean` Clear build and dependencies
* `deb` Create debian packages
* `deps` Install dependencies
* `dist` Distribute (Create tar and deb packages)
* `tar` Create tar package
* `test` Execute testsuite
* `phpcs` Execute phpcs check
* `phpstan` Execute phpstan
* `install` Install the balloon server

## Git commit 
Please make sure that you always specify the number of your issue starting with a hastag (#) within any git commits.

## Pull Request
You are absolutely welcome to submit a pull request which references an open issue. Please make sure you're follwing coding standards 
and be sure all your modifications pass the build.
[![Build Status](https://travis-ci.org/gyselroth/balloon.svg?branch=dev)](https://travis-ci.org/gyselroth/balloon)

## Code of Conduct
Please note that this project is released with a [Contributor Code of Conduct](https://github.com/gyselroth/balloon/blob/master/CODE_OF_CONDUCT.md). By participating in this project you agree to abide by its terms.

## License
This software is freely available under the terms of [GPL-3.0](https://github.com/gyselroth/balloon/blob/master/LICENSE), please respect this license
and do not contribute software parts which are not compatible with GPL-3.0.

## Editor config
This repository gets shipped with an .editorconfig configuration. For more information on how to configure your editor please visit [editorconfig](https://github.com/editorconfig).

## Code policy
Add the following script to your git pre-commit hook file, otherwise your build will fail if you do not following code style:

```
./vendor/bin/php-cs-fixer fix --config=.php_cs.dist -v
```

This automatically converts your code into the code style guidelines of this project otherwise your build will fail!

