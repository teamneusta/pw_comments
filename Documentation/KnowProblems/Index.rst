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


Links in mails does not start with domain
-----------------------------------------
pw_comments is able to send mails to comment author and administrators. Those mails may contain links.
In case your links in mails start with "/", you need to adjust the "Entry Point" (base) in the current site
configuration of TYPO3.
