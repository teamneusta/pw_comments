.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt

.. _anchors:

Anchors
=======

===================================== ========
Property                               Type
===================================== ========
commentAnchorPrefix_                   string
showCommentAnchor_                     string
writeCommentAnchor_                    string
successfulAnchor_                      string
customMessagesAnchor_                  string
===================================== ========

.. _commentAnchorPrefix:

commentAnchorPrefix
"""""""""""""""""""
.. container:: table-row

   Property
      commentAnchorPrefix
   Data type
      string
   Default
      comment
   Description
      Every comment gets an own anchor which contains the uid of the comment. In the default case the anchor will
      look something like that: #comment1337.

.. _showCommentAnchor:

showCommentAnchor
"""""""""""""""""
.. container:: table-row

   Property
      showCommentAnchor
   Data type
      string
   Default
      comments
   Description
      This anchor will appear in front of the comment numbering.

.. _writeCommentAnchor:

writeCommentAnchor
""""""""""""""""""
.. container:: table-row

   Property
      writeCommentAnchor
   Data type
      string
   Default
      writeComment
   Description
      This anchor will appear in front of the comment form.

.. _successfulAnchor:

successfulAnchor
""""""""""""""""
.. container:: table-row

   Property
      successfulAnchor
   Data type
      string
   Default
      thanksForYourComment
   Description
      This is the anchor of the flashMessage which notifies the user that his comment has been submitted successfully.

.. _customMessagesAnchor:

customMessagesAnchor
""""""""""""""""""""
.. container:: table-row

   Property
      customMessagesAnchor
   Data type
      string
   Default
      customMessages
   Description
      This anchor is part of index action and marks the flashMessage container to show customMessages, like
      "You can't vote for your own comment."
