## Set up the local environment
#### Requirements
* [Node](https://nodejs.org/)
* [Docker](https://store.docker.com/search?type=edition&offering=community)
* [Docker Compose](https://docs.docker.com/compose/install/#install-compose)

#### Project Setup
* Open a shell on your computer and navigate to the projects directory
* Run `docker-compose up -d`
* Open your browser and type [http://localhost/](http://localhost/)
* Log in with the username `admin` and the password `admin`
* Run `docker exec -u www-data passwords-php /var/www/html/occ app:enable passwords`
* Run `docker exec -u www-data passwords-php /var/www/html/occ config:app:set passwords environment --value dev`
* Run `npm install`
* Run `npm run watch`
* You can now access the [app](http://localhost/index.php/apps/passwords)
* A [file with sample data](https://git.mdns.eu/nextcloud/passwords/wikis/_files/Sample%20Passwords.json) is available in the wiki and can be imported on the [backups page](http://localhost/index.php/apps/passwords#/backup)

#### Helpful commands
* `npm run build` - Create production build of JS/CSS files
* `npm run watch` - Create development build of JS/CSS files and watch for changes
* `docker exec -u www-data passwords-php php /var/www/html/cron.php` - Execute cron jobs

#### First steps
* [Issues for beginners](https://github.com/marius-wieschollek/passwords/labels/for%3Astarters)
* [Wiki](https://github.com/marius-wieschollek/passwords/wiki)