#!/bin/bash
release=$1
repository=gyselroth/balloon
repositoryDevelopment=$repository-development
version=$(cat VERSION)
tag=$release-$version

docker build -t $repository:$tag .
docker build --build-arg DEV=yes -t $repositoryDevelopment:$tag .

docker login -u $DOCKER_USER -p $DOCKER_PASSWORD
docker push $repository:$tag
docker push $repositoryDevelopment:$tag
