## Instructions

```
$ composer install
$ docker-compose up -d
$ docker exec -it journal-cms.docker.amazee.io /bin/bash
$ cd web
$ drush si config_installer -y
``` 

Once it is setup, visit `http://journal-cms.docker.amazee.io`.
