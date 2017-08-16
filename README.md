# pw_comments

The full documentation can be found here:

https://docs.typo3.org/typo3cms/extensions/pw_comments/


## Vagrant setup

A Vagrantfile is shipped with pw_comments. Open http://127.0.0.1:8080 or http://127.0.0.1:8080/76/ in your browser 
after you've performed a 
```
vagrant up
```

On Windows you need to install the vagrant plugin WinNFSd before you can vagrant up:
```
vagrant plugin install vagrant-winnfsd
```

and this vagrant plugin is required on any machine:
```
vagrant plugin install vagrant-bindfs
```

Your files are automatically uploaded to `/var/www/html/typo3conf/ext/pw_comments` and 
`/var/www/html76/typo3conf/ext/pw_comments`. **Caution! Files are synched!** Deleting files in machine will also delete 
them on host machine.

### Credentials

**For TYPO3:** *admin* / *password* (also install tool password)
**For Database:** *root* / - (no password set)
**For SSH:** *vagrant* / *vagrant*

**TYPO3 paths:** `/var/www/html/` and `/var/www/html76/` (uses composer, you can update with `composer update`).
