.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt

.. _moderation:

Terms accepted
==============

===================================== ========
Property                               Type
===================================== ========
requireAcceptedTerms_                  boolean
termsTypolinkParameter_                string
===================================== ========

.. _requireAcceptedTerms:

requireAcceptedTerms
"""""""""""""""""""
.. container:: table-row

   Property
      requireAcceptedTerms
   Data type
      boolean
   Default
      1
   Description
      If this option is enabled, a "accept terms" checkbox is displayed which is required to post new comments.

.. _termsTypolinkParameter:

termsTypolinkParameter
"""""""""""""""""""""""
.. container:: table-row

   Property
      termsTypolinkParameter
   Data type
      string
   Default
      t3://page?uid=1
   Description
      A typolink parameter string, to define link to terms in checkbox label. You can also link to files
      or external urls.
