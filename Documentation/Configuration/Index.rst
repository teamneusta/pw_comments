.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt

.. _configuration:

Configuration
=============

First installation
------------------

After you've successfully installed pw_comments and added the static setup to your TYPO3 template you can simply
add the following TypoScript lines to your main column output in order to output the comments:

.. code-block:: typoscript

   lib.content = COA
   lib.content {
      10 < styles.content.get

      # List comments
      20 < lib.pwCommentsIndex

      # Write new comment
      30 < lib.pwCommentsNew
   }

This will display the comments on every page. You can use any conditions you want
(for example: [globalVar = TSFE:page|layout = 1]) to define the visibility of comments for specified pages
(since you probably wouldn't want comments to appear on all pages).

Settings
--------

pw_comments includes the following settings which make it highly customizable:

:typoscript:`plugin.tx_pwcomments.settings.`

.. toctree::
   :maxdepth: 1

   Anchors/Index
   EntryUids/Index
   FieldReplacements/Index
   Gravatar/Index
   Mails/Index
   Moderation/Index
   RelativeDate/Index
   Replying/Index
   Rights/Index
   Sortings/Index
   SpamProtection/Index
   Voting/Index

TypoScript in settings
----------------------
You may use TypoScript in the settings. For example:

.. code-block:: typoscript

   plugin.tx_pwcomments.settings.successfulAnchor = TEXT
   plugin.tx_pwcomments.settings.successfulAnchor {
      data = page:title
      wrap = #thanksForYourCommentOnThePage|
   }

Flexibility with conditions
---------------------------
You can define the settings even further using conditions. The default setup, which is delivered in the static setup,
contains different settings for users who are logged in:

.. code-block:: typoscript

   [loginUser = *]
      plugin.tx_pwcomments {
         settings {
            moderateNewComments = 0
            secondsBetweenTwoComments = 60

            replaceUsernameWith = COA_INT
            replaceUsernameWith {
               10 = TEXT
               10.data = LLL:EXT:pw_comments/Resources/Private/Language/locallang.xml:tx_pwcomments.newComment.loggedInAs

               20 = TEXT
               20.data = TSFE:fe_user|user|username
               20.noTrimWrap = | <b>|</b>|

               stdWrap.wrap = <div class="loggedInAs">|</div>
            }

            replaceMailWith = TEXT
            replaceMailWith.stdWrap.char = 0
         }
      }
   [global]

This will disable moderation for new comments, reduce the minimal number of seconds between two comments and replace
the input field for name and e-mail with the string: "You are logged in as: <b>Blah</b>" for logged in users.

How to use pw_comments with other extensions
--------------------------------------------
Since version 1.1 it is possible to use pw_comments not only with pages but also with tt_news entries or any other
extension - thanks to the new entryUid.

You can enable or disable the usage of entryUid by setting TypoScript conditions. If you do so pw_comments will search
for comments which are located on the current page (pid). For example, if you use tt_news the pid for all news is the
same, because the pid only defines the page which contains the detail-view-plugin and not the news entry itself.

If you enable the entryUid you can define its content yourself. In this example you could fill in the news-entry-uid
of current news.

In TypoScript this could look like this:

.. code-block:: typoscript

   [globalVar = GP:tx_ttnews|tt_news > 0]
      # Enable the usage of entryUid and define entryUid
      plugin.tx_pwcomments.settings {
         useEntryUid = 1
         entryUid = TEXT
         entryUid.data = GP:tx_ttnews|tt_news
      }

      # And add comments if not done yet
      lib.content.80 < lib.pwCommentsIndex
      lib.content.85 < lib.pwCommentsNew
   [global]

First you check the condition "Am I currently on a news detail view?". If this is the case, the GET-parameter
tx_ttnews[tt_news] exists and is greater than zero.

Then you tell pw_comments to use the entryUid and fill it with this GET-parameter value, which is basicly the uid
of the news_entry.

Now pw_comments respects the uid of the news itself and you can add several separate comments to all published
news entries.

Of course you can also use this feature with any other extension - just replace the GET-parameter.

It is not possible to use more than one instance of pw_comments per page!

Libraries for further usage
---------------------------
The static setup of pw_comments contains some libs to make your work more comfortable:

lib.pwCommentsIndex
^^^^^^^^^^^^^^^^^^^
This displays the comments with the Fluid template engine.

lib.pwCommentsNew
^^^^^^^^^^^^^^^^^
This displays the form for posting comments. You can disclaim this form if you want to make the comments read only.

lib.pwCommentsGetCount
^^^^^^^^^^^^^^^^^^^^^^
Returns the number of comments on the current page. You can use this lib with the f:cObject viewhelper in Fluid as well.

.. warning:: If you have the option useEntryUid enabled, the amount of comments will just respect the comments per
            page, not per eg. news entry. You have to modify the typoscript for this functionality a little bit. Just
            add the following to your own typoscript:

   .. code-block:: typoscript

      lib.pwCommentsGetCount {
         10.select.andWhere = entry_uid=###NEWSUID###
         10.select.markers.NEWSUID.data = GP:tx_ttnews|tt_news
      }

lib.pwCommentsGetCountWithLabel
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
Similar to lib.pwCommentsGetCount, but adds the word 'comment/s' behind the number of comments.
i.e: "0 comments", "1 comment", "2 comments"

CSS default style
-----------------

The static setup provides some basic CSS styles. If you want to disable them simply paste this into your TypoScript:

.. code-block:: typoscript

   plugin.tx_pwcomments._CSS_DEFAULT_STYLE >

Author or authorName?
---------------------
The author of a comment is saved in two diffent ways: If an unregistred user writes a comment the fields authorName
and authorMail are filled. If the user is registered the model of the user is linked to the comment in the field author.

authorIdent
-----------
Each comment author gets an authorIdent, stored in comments and votes. If user is logged in, it contains the uid of
the frontend user. Otherwise it contains a randomly generated string. For not logged in users, this string will be
stored as cookie with key tx_pwcomments_ahash.

Overwrite translations
----------------------
If you want to change translations, like the upvote/downvote label, just use typoscript:

.. code-block:: typoscript

   plugin.tx_pwcomments._LOCAL_LANG.default.tx_pwcomments.votes.upvote = :)
   plugin.tx_pwcomments._LOCAL_LANG.default.tx_pwcomments.votes.downvote = :(

   plugin.tx_pwcomments._LOCAL_LANG.de.tx_pwcomments.votes.upvote = :)
   plugin.tx_pwcomments._LOCAL_LANG.de.tx_pwcomments.votes.downvote = :(

You can also empty labels, if you want to use a background image (via CSS) instead.
