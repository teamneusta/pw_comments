.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt
.. include:: Images.txt

.. _administration:

Administration
==============

.. only:: html

How to get pw_comments
----------------------
There are two ways to get pw_comments. You can visit the TER at
https://extensions.typo3.org/extension/pw_comments and download pw_comments there or you can use
Composer to check out the latest version (https://packagist.org/packages/teamneusta/pw_comments).

Installation
------------
After loading the extension onto the server, you will have to install it like every other extension. The installation
will create a new table for storing comments. After the installation you should add the static include to your current
TYPO3 template:

|comment_static-includes|

The Main Static Template must be included. The Styles are optional, but recommended.

When the statics have been added you can access several libs to include pw_comments into your page
(as described in the chapter :ref:`configuration`).

.. important::
   When you want to work with mail notifications, you should configure an absolute URL (like ``https://my-domain.com/``) in
   TYPO3's **site configuration** instead of a plain ``/``. This is required by TypoLink to create absolute URLs in mails.
