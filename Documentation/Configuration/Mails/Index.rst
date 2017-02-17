.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt

.. _mails:

Mails to comment author
=======================

===================================== ========
Property                               Type
===================================== ========
sendMailToAuthorAfterSubmit_           boolean
sendMailToAuthorAfterSubmitTemplate_   string
sendMailToAuthorAfterPublish_          boolean
sendMailToAuthorAfterPublishTemplate_  string
===================================== ========

.. _sendMailToAuthorAfterSubmit:

sendMailToAuthorAfterSubmit
"""""""""""""""""""""""""""
.. container:: table-row

   Property
      sendMailToAuthorAfterSubmit
   Data type
      boolean
   Default
      0
   Description
      If this option is enabled, the author receives a mail after each comment he/she has written.

.. _sendMailToAuthorAfterSubmitTemplate:

sendMailToAuthorAfterSubmitTemplate
"""""""""""""""""""""""""""""""""""
.. container:: table-row

   Property
      sendMailToAuthorAfterSubmitTemplate
   Data type
      string
   Default
      EXT:pw_comments/[...]/mailToAuthorAfterSubmit.html
   Description
      Defines the path to the Fluid template which should be used for the e-mail to comment author.

.. _sendMailToAuthorAfterPublish:

sendMailToAuthorAfterPublish
""""""""""""""""""""""""""""
.. container:: table-row

   Property
      sendMailToAuthorAfterPublish
   Data type
      boolean
   Default
      0
   Description
      	If this option is enabled, the author receives a mail after a comment he/she has written has been published.
      	This option requires activated "moderateNewComments".

.. _sendMailToAuthorAfterPublishTemplate:

sendMailToAuthorAfterPublishTemplate
""""""""""""""""""""""""""""""""""""
.. container:: table-row

   Property
      sendMailToAuthorAfterPublishTemplate
   Data type
      string
   Default
      EXT:pw_comments/[...]/mailToAuthorAfterPublish.html
   Description
      Defines the path to the Fluid template which should be used for the e-mail to comment author.
