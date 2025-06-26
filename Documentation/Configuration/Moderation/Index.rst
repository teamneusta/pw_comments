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
enableAiModeration_                    boolean
aiModerationProvider_                  string
aiModerationThreshold_                 float
aiModerationApiKey_                    string
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

AI Enhanced Moderation
======================

The pw_comments extension now supports AI-enhanced content moderation using OpenAI's moderation API to automatically detect and flag inappropriate content before it's published.

.. note::
   As of the time of writing, OpenAI's moderation API endpoint is free to use and does not consume your API usage quota or budget. This makes it an excellent choice for automated content moderation without additional costs.

Features
--------

- **Automatic Content Analysis**: Comments are automatically analyzed for potentially harmful content
- **Real-time Detection**: Content is evaluated immediately upon submission
- **Detailed Categorization**: The system provides specific categories of flagged content (hate speech, harassment, violence, etc.)
- **Confidence Scoring**: Each analysis includes confidence scores for different violation categories
- **Backend Integration**: Moderation results are stored and visible in the TYPO3 backend
- **Flexible Thresholds**: Configure custom sensitivity levels for content flagging

.. _enableAiModeration:

enableAiModeration
""""""""""""""""""
.. container:: table-row

   Property
      enableAiModeration
   Data type
      boolean
   Default
      0
   Description
      Enables AI-powered content moderation using OpenAI's moderation API. When enabled, all new comments will be automatically analyzed for potentially harmful content before being published.

.. _aiModerationProvider:

aiModerationProvider
""""""""""""""""""""
.. container:: table-row

   Property
      aiModerationProvider
   Data type
      string
   Default
      openai
   Description
      Specifies which AI moderation service to use. Currently supports "openai" for OpenAI's moderation API. Additional providers may be added in future versions.

.. _aiModerationThreshold:

aiModerationThreshold
"""""""""""""""""""""
.. container:: table-row

   Property
      aiModerationThreshold
   Data type
      float
   Default
      0.7
   Description
      Sets the confidence threshold (0.0 to 1.0) for flagging content as inappropriate. Lower values are more sensitive and will flag more content. A value of 0.7 provides a good balance between catching problematic content and avoiding false positives.

.. _aiModerationApiKey:

aiModerationApiKey
""""""""""""""""""
.. container:: table-row

   Property
      aiModerationApiKey
   Data type
      string
   Description
      Your OpenAI API key for accessing the moderation service. This key is required when AI moderation is enabled. Keep this key secure and never commit it to version control.

Configuration Example
---------------------

To enable AI moderation in your TypoScript configuration:

.. code-block:: typoscript

   plugin.tx_pwcomments.settings.moderateNewComments = 1
   plugin.tx_pwcomments.settings.enableAiModeration = 1
   plugin.tx_pwcomments.settings.aiModerationProvider = openai
   plugin.tx_pwcomments.settings.aiModerationThreshold = 0.7
   plugin.tx_pwcomments.settings.aiModerationApiKey = your-openai-api-key-here

.. important::
   Store your OpenAI API key securely using TYPO3's environment variables or configuration files that are not tracked in version control.

Backend Integration
-------------------

When AI moderation is enabled, the comment records in the TYPO3 backend will show additional fields:

- **AI Moderation Status**: Whether the comment was flagged by the AI system
- **AI Moderation Reason**: Detailed explanation of why the content was flagged
- **AI Moderation Confidence**: The confidence score of the moderation decision
- **AI Moderation Control**: Manual override controls for administrators

Content Categories
------------------

OpenAI's moderation API detects the following categories of potentially harmful content:

- **hate**: Content expressing hate or promoting hatred
- **hate/threatening**: Hate speech that includes threats or incites violence
- **harassment**: Content intended to harass, bully, or intimidate
- **harassment/threatening**: Harassment that includes threats
- **self-harm**: Content promoting self-harm or suicide
- **self-harm/intent**: Content with explicit intent to harm oneself
- **self-harm/instructions**: Content providing instructions for self-harm
- **sexual**: Sexual content (may not be appropriate for all audiences)
- **sexual/minors**: Sexual content involving minors
- **violence**: Content depicting or promoting violence
- **violence/graphic**: Graphic violent content

Security Considerations
-----------------------

- API keys should be stored securely and rotated regularly
- Consider implementing rate limiting to prevent API abuse
- Monitor your API usage even though the moderation endpoint is free

Error Handling
--------------

The system includes comprehensive error handling:

- **API Key Missing**: Comments are processed normally with a warning logged
- **API Failures**: Detailed error logging with comment context
- **Rate Limiting**: Graceful handling of API rate limits
- **Network Issues**: Fallback to manual moderation when the service is unavailable

Troubleshooting
---------------

**AI moderation not working:**

1. Verify your OpenAI API key is correctly configured
2. Check that ``enableAiModeration`` is set to ``1``
3. Review the TYPO3 system log for any error messages
4. Ensure your server can make outbound HTTPS requests to OpenAI's API

**Too many false positives:**

- Increase the ``aiModerationThreshold`` value (e.g., from 0.7 to 0.8)
- Review the specific categories being flagged in the backend

**Comments not being flagged:**

- Decrease the ``aiModerationThreshold`` value (e.g., from 0.7 to 0.5)
- Verify the API is responding correctly by checking the system logs
