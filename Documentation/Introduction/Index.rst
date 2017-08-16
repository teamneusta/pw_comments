.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt
.. include:: Images.txt

.. _introduction:

Introduction
============

.. only:: html

   :ref:`what` | :ref:`requirements` | :ref:`features` | :ref:`screenshots` | :ref:`issues` |

.. _what:

What is 'pw_comments'?
----------------------
pw_comments is an extension for TYPO3 which adds the possibility to post comments on pages
(similar to the comment function in blogs) or extension entries like tt_news-news. This extension is based completely
on Extbase and Fluid.

.. _requirements:

Requirements of pw_comments
---------------------------
- TYPO3 7.6.x
- Extbase / Fluid

.. _features:

Features
--------
- Adds the possibility for users to post comments on pages
- Easy to integrate into existing or new TYPO3 sites
- Compatible with other extensions like EXT:news
- Very(!!) customizable thanks to TypoScript (constant manager fully supported)
- Vote comments and replies
- Replying to comments
- Gravatar support
- Spam protection:
   - Bad word list for comment message, name and mail address
   - "Hidden field" spam protection
   - Customizable waiting time between two comments
- Moderate new comments
- Change order of comments and/or replies (separately adjustable)
- Rich mail notifications

.. _screenshots:

Screenshots
-----------

Frontend: Comment output
^^^^^^^^^^^^^^^^^^^^^^^^
|comment_fe|

Frontend: Comment voting
^^^^^^^^^^^^^^^^^^^^^^^^
|comment_fe_voting|

Backend: Comment Record
^^^^^^^^^^^^^^^^^^^^^^^
|comment_be_record|

.. _issues:

Issues, bugs and feature requests
---------------------------------
If you have found a bug or you want to submit a request for a new feature, please use the Forge issue tracker
at http://forge.typo3.org/projects/extension-pw_comments/issues.

In case you don't want to create an account on Forge, you may send me an e-mail at armin(at)v.ieweg.de
