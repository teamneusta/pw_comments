.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt

.. _moderation:

Moderation and e-mail notification
==================================

.. important::
   When you want to work with mail notifications, you should configure an absolute URL (like ``https://my-domain.com/``) in
   TYPO3's **site configuration** instead of a plain ``/``. This is required by TypoLink to create absolute URLs in mails.

===================================== ========
Property                               Type
===================================== ========
moderateNewComments_                   boolean
sendMailOnNewCommentsTo_               string
sendMailTemplate_                      string
sendMailMimeType_                      string
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
