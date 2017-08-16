.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt

.. _replying:

Replying to comments
====================

===================================== ========
Property                               Type
===================================== ========
enableRepliesToComments_               boolean
showRepliesToComments_                 boolean
countReplies_                          boolean
===================================== ========

.. _enableRepliesToComments:

enableRepliesToComments
"""""""""""""""""""""""
.. container:: table-row

   Property
      enableRepliesToComments
   Data type
      boolean
   Default
      0
   Description
      Enables or disables the reply link and adds a hidden field to new comment form with uid of parent comment. If
      this option is activated the next one should be activated, too.

.. _showRepliesToComments:

showRepliesToComments
"""""""""""""""""""""
.. container:: table-row

   Property
      showRepliesToComments
   Data type
      boolean
   Default
      0
   Description
      Enables or disables the display of already existing replies to comments.

.. _countReplies:

countReplies
""""""""""""
.. container:: table-row

   Property
      countReplies
   Data type
      boolean
   Default
      1
   Description
      If true, the placeholder {commentCount} will also count replies on comments. Otherwise just comments
      (no replies) will get counted.