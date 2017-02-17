.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt

.. _spamProtection:

Spam protection
===============

===================================== ========
Property                               Type
===================================== ========
useBadWordsList_                       boolean
useBadWordsListOnUsername_             boolean
useBadWordsListOnMailAddress_          boolean
badWordsList_                          string
hiddenFieldSpamProtection_             boolean
hiddenFieldName_                       string
hiddenFieldClass_                      string
===================================== ========

.. _useBadWordsList:

useBadWordsList
"""""""""""""""
.. container:: table-row

   Property
      useBadWordsList
   Data type
      boolean
   Default
      1
   Description
      Enables or disables the usage of bad word list. This option just checks the entered comment message.

.. _useBadWordsListOnUsername:

useBadWordsListOnUsername
"""""""""""""""""""""""""
.. container:: table-row

   Property
      useBadWordsListOnUsername
   Data type
      boolean
   Default
      1
   Description
      If enabled performs bad words check on entered username. This option may be activated seperately from
      useBadWordsList option.

.. _useBadWordsListOnMailAddress:

useBadWordsListOnMailAddress
""""""""""""""""""""""""""""
.. container:: table-row

   Property
      useBadWordsListOnMailAddress
   Data type
      boolean
   Default
      0
   Description
      Same like option useBadWordsListOnUsername, just for the entered email address.

.. _badWordsList:

badWordsList
""""""""""""
.. container:: table-row

   Property
      badWordsList
   Data type
      string
   Default
      EXT:pw_comments/[...]/badwords.txt
   Description
      Defines the path a bad word list containing regular expressions (one in each line).

.. _hiddenFieldSpamProtection:

hiddenFieldSpamProtection
"""""""""""""""""""""""""
.. container:: table-row

   Property
      hiddenFieldSpamProtection
   Data type
      boolean
   Default
      hiddenFieldSpamProtection
   Description
      If enabled an additional hidden field will be added to the comment form. If this field is filled
      (for example by a bot) the comment will not be saved.

.. _hiddenFieldName:

hiddenFieldName
"""""""""""""""
.. container:: table-row

   Property
      hiddenFieldName
   Data type
      string
   Default
      authorWebsite
   Description
      Field name of the hidden field (type="input")

.. _hiddenFieldClass:

hiddenFieldClass
""""""""""""""""
.. container:: table-row

   Property
      hiddenFieldClass
   Data type
      string
   Default
      hide_initally
   Description
      CSS class for hiding the field
