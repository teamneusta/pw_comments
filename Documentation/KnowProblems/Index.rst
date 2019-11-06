.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt

.. _knowProblems:

Know problems / Troubleshooter
==============================

fe_user not found
-----------------
If you work with registred users, they will need a special property set in order to be recognized by Extbase. The
following extension explains which field that is and sets it to default: Extension 'fe_users_default_extbase_type'


Beautified URL is gone after comment has been submitted
-------------------------------------------------------
The Extbase URI builder is not able to detect the configuration made in routing enhancers. Redirects made by Extbase
will work, but not with the beautified URL. pw_comments offers a new option "enhancedRouting" in typoscript, to
always use the beautified URI. When this option is enabled, other query parameters will get lost.
