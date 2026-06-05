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
will create a new table for storing comments.

You can wire the extension's TypoScript into your site in two ways. Pick one — both produce the same result.

**Option A: Site Set (recommended, TYPO3 v13+)**

Add the site set to your site configuration in ``config/sites/<identifier>/config.yaml``:

.. code-block:: yaml

   dependencies:
     - pwcomments/comments
     - pwcomments/styling    # optional, default styles

The ``pwcomments/comments`` set is required; ``pwcomments/styling`` ships the
optional default CSS. Settings are then editable in the backend module
**Site Management → Settings**.

**Option B: Static template includes (legacy, still supported)**

Edit your root TYPO3 template and include the static templates:

|comment_static-includes|

The Main Static Template must be included. The Styles are optional, but recommended.
Settings are then editable via the Constants Editor under the **Template** module.

When the includes are in place (via either option) you can access several libs to include pw_comments
into your page (as described in the chapter :ref:`configuration`).

.. important::
   When you want to work with mail notifications, you should configure an absolute URL (like ``https://my-domain.com/``) in
   TYPO3's **site configuration** instead of a plain ``/``. This is required by TypoLink to create absolute URLs in mails.
