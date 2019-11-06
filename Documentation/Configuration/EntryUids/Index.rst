.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt

.. _entryUids:

EntryUid (for usage with other extensions like EXT:news)
========================================================

===================================== ========
Property                               Type
===================================== ========
useEntryUid_                           boolean
entryUid_                              cObject
===================================== ========

.. _useEntryUid:

useEntryUid
"""""""""""
.. container:: table-row

   Property
      useEntryUid
   Data type
      boolean
   Default
      1
   Description
      Enables or disables the usage of entryUid

.. _entryUid:

entryUid
""""""""
.. container:: table-row

   Property
      entryUid
   Data type
      cObject
   Description
      Use cObject to define the value of entryUid dynamically. See chapter "How to use pw_comments with other
      extensions" for further information.

.. _enhancedRouting:

enhancedRouting
""""""""
.. container:: table-row

   Property
      enhancedRouting
   Data type
      boolean
   Default
      0
   Description
      When a routing enhancer has been used (e.g. for news), pw_comments is unable to redirect to beautified URL.
      To bypass this, enabled this option and redirections will be performed on route's path only.
      Disable this option, when you need additional query parameters.
