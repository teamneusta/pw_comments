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
senderAddress_                         string
senderName_                            string
sendMailToAuthorAfterSubmit_           boolean
sendMailToAuthorAfterSubmitTemplate_   string
sendMailToAuthorAfterPublish_          boolean
sendMailToAuthorAfterPublishTemplate_  string
sitenameUsedInMails_  string
===================================== ========

.. _senderAddress:

senderAddress
"""""""""""""""""""""""""""
.. container:: table-row

   Property
      senderAddress
   Data type
      string
   Description
      Sender's mail address when pw_comments sends out mails.

.. _senderName:

senderName
"""""""""""""""""""""""""""
.. container:: table-row

   Property
      senderName
   Data type
      string
   Description
      Name to display in mail clients, instead of mail address. (optional)

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

.. _sitenameUsedInMails:

sitenameUsedInMails
"""""""""""""""""""
.. container:: table-row

   Property
      sitenameUsedInMails
   Data type
      string
   Description
      When this option is set, it replaces the getHostname() output, used in mail subject.
