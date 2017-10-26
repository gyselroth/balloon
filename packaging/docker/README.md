# Docker Image for balloon
## Build Image
 1. Clone this repository
```git clone https://github.com/gyselroth/balloon```
 2. Change into repository directory
``cd balloon``
 3. Build docker image
``docker build -t balloon .``

## Development
To build a development image, which contains all necessary tools to run, test and build balloon, run the docker build with the build argument `DEV` set to `yes`.
Example: ``docker build -t balloon-development --build-arg DEV=yes .``

### Access
Access your new balloon development instance via https://localhost:8081

User: admin
Password: admin


### Inject local codebase
Start a new development container and inject the local git repo into the docker image:
(Replace /path/to/balloon with the path where you cloned the balloon server repository)
```
docker run -p 8081:443 -v /path/to/balloon:/srv/www/balloon balloon-development
```

#### Install dependencies:
(Always execute build.sh via docker exec!)
```
docker exec CONTAINER /srv/www/balloon/build.sh --dep
```

## Create Users
There's a script in the container to create users in the mongodb (default quota: 100 MB, append "admin" to create an admin user).

``Usage: /usr/local/bin/createUserMongoDB USERNAME PASSWORD [admin]
``

For example to run the script with `docker exec`:
``docker exec -it CONTAINER createUserMongoDB USERNAME PASSWORD``

## Customizations
### SSL Certificates
During the build process the image generates a self-signed certifcate used for HTTPS.
If you want to use your own certificate, you can map a directory containing your key as ``key.pem`` and your certificate as ``chain.pem`` to ``/etc/ssl/balloon.local``.

### Configuration
The image uses the minimal default configuration from the balloon repository (https://github.com/gyselroth/balloon/blob/master/dist/config.xml).
If you want to use your own config, you can map a directory containing your ``config.xml`` (and if necessary your ``cli.xml``) to ``/srv/www/balloon/config``.
