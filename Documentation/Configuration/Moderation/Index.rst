.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt

.. _moderation:

Moderation and e-mail notification
==================================

===================================== ========
Property                               Type
===================================== ========
moderateNewComments_                   boolean
sendMailOnNewCommentsTo_               string
sendMailTemplate_                      string
sendMailMimeType_                      string
overwriteBackendDomain_                string
subFolder_                             string
===================================== ========

.. _moderateNewComments:

moderateNewComments
"""""""""""""""""""
.. container:: table-row

   Property
      moderateNewComments
   Data type
      boolean
   Default
      1
   Description
      If this option is enabled, comments are not automatically visible. A back end user has to activate them first.

.. _sendMailOnNewCommentsTo:

sendMailOnNewCommentsTo
"""""""""""""""""""""""
.. container:: table-row

   Property
      sendMailOnNewCommentsTo
   Data type
      string
   Description
      One or more e-mail addresses who will be informed every time a new comment has been submitted. Separate
      multiple recipients using a comma or leave empty to disable e-mail notifications.


.. _sendMailTemplate:

sendMailTemplate
""""""""""""""""
.. container:: table-row

   Property
      sendMailTemplate
   Data type
      string
   Default
      EXT:pw_comments/[...]/mail.html
   Description
      Defines the path to the Fluid template which should be used for notification e-mails

.. _sendMailMimeType:

sendMailMimeType
""""""""""""""""
.. container:: table-row

   Property
      sendMailMimeType
   Data type
      string
   Default
      text/plain
   Description
      Defines the MIME-type of the e-mail body. Please note that changes made here also need to be regarded in the
      appropriate template.

.. _overwriteBackendDomain:

overwriteBackendDomain
""""""""""""""""""""""
.. container:: table-row

   Property
      overwriteBackendDomain
   Data type
      string
   Description
      If your back end (sub-)domain is different to the one in the front end, you can change it here. The activation
      link in notification e-mails is generated accordingly to this setting.

.. _subFolder:

subFolder
"""""""""
.. container:: table-row

   Property
      subFolder
   Data type
      string
   Description
      Since 1.3: If your TYPO3 instance is located in a subfolder you need to tell in this option the subfolder
      with beginning slash (e.g. "/website")
