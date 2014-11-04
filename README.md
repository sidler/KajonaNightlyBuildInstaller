KajonaNightlyBuildInstaller
===========================

Downloads the latest Kajona build (development snapshot) to the current directory and installs the system using sqlite directly.
If the script succeeds, you'll get a fully operational Kajona installation with just one click.

Call it either by commandline

```
php -f install.php
```

or call it using your webserver

```
http://yourwebserver/kajona/install.php
```

The script downloads the latest nightly build into the current directory (the file is downloaded only once).
The zip-file is extracted to ```kajona```.

After installation, you may log into the system using

Username: admin
Password: demo

