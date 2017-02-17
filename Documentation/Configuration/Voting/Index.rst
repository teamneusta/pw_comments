.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt

.. _voting:

Voting
======

===================================== ========
Property                               Type
===================================== ========
enableCommentVotes_                    boolean
enableReplyVotes_                      boolean
enableVoting_                          boolean
hideVoteButtons_                       boolean
ignoreVotingForOwnComments_            boolean
===================================== ========

.. _enableCommentVotes:

enableCommentVotes
""""""""""""""""""
.. container:: table-row

   Property
      enableCommentVotes
   Data type
      boolean
   Default
      1
   Description
      Enables or disables the voting controls for comments.

.. _enableReplyVotes:

enableReplyVotes
""""""""""""""""
.. container:: table-row

   Property
      enableReplyVotes
   Data type
      boolean
   Default
      1
   Description
      Enables or disables the voting controls for replies.

.. _enableVoting:

enableVoting
""""""""""""
.. container:: table-row

   Property
      enableVoting
   Data type
      boolean
   Default
      1
   Description
      If disabled votes are visible but users may not vote for comments/replies. This is useful if you want to
      restrict possibility to vote for eg. logged in users but show the votings.

.. _hideVoteButtons:

hideVoteButtons
"""""""""""""""
.. container:: table-row

   Property
      hideVoteButtons
   Data type
      boolean
   Default
      0
   Description
      If enabled the voting buttons are not visible. Caution: The voting functionality itself is still enabled.

.. _ignoreVotingForOwnComments:

ignoreVotingForOwnComments
""""""""""""""""""""""""""
.. container:: table-row

   Property
      ignoreVotingForOwnComments
   Data type
      boolean
   Default
      1
   Description
      If enabled users are not allowed to vote for their own comments/replies.
