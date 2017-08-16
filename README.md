# pw_comments

The full documentation can be found in the wiki of pw_comments on TYPO3 Forge:

https://forge.typo3.org/projects/extension-pw_comments/wiki/Documentation


## Vagrant setup

A Vagrantfile is shipped with pw_comments. Open http://127.0.0.1:8080 in your browser after you've performed a 
```
vagrant up
```

On Windows you need to install the vagrant plugin WinNFSd before you can vagrant up:
```
vagrant plugin install vagrant-winnfsd
```

Your files are automatically uploaded to `/var/www/html/typo3conf/ext/pw_comments`.
**Caution! Files are synched!** Deleting files in machine will also delete them on host machine.


The used box [ArminVieweg/trusty64-lamp](https://atlas.hashicorp.com/ArminVieweg/boxes/trusty64-lamp) contains:

- Apache2
- PHP 7.0 & 5.6 *(need to switch manually by changing symlinks in Apache2's mods dir)* 
- mysql-server & mysql-client
- Imagemagick
- Git
- Composer (with auto self-update on vagrant up)
- TYPO3 8.7 LTS
- jigal/**t3adminer** extension (as [composer package](https://packagist.org/packages/jigal/t3adminer))

### Credentials

**For TYPO3:** *admin* / *password* (also install tool password)
**For Database:** *root* / - (no password set)
**For SSH:** *vagrant* / *vagrant*

**TYPO3 path:** `/var/www/html/` (uses composer, you can update with `composer update`).
