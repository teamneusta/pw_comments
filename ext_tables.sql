#
# Table structure for table 'tx_pwcomments_domain_model_comment'
#
CREATE TABLE tx_pwcomments_domain_model_comment (
	entry_uid int(11) unsigned DEFAULT '0' NOT NULL,
	parent_comment int(11) unsigned DEFAULT '0' NOT NULL,
	orig_pid int(11) unsigned DEFAULT '0' NOT NULL,

	author int(11) unsigned DEFAULT '0' NOT NULL,
	author_name tinytext,
	author_mail tinytext,
	author_ident tinytext,
	message text,
	votes int(11) unsigned DEFAULT '0' NOT NULL,
	terms_accepted int(11) unsigned DEFAULT '0' NOT NULL,
);

#
# Table structure for table 'tx_pwcomments_domain_model_vote'
#
CREATE TABLE tx_pwcomments_domain_model_vote (
	orig_pid int(11) unsigned DEFAULT '0' NOT NULL,
	type int(11) unsigned DEFAULT '1' NOT NULL,
	author int(11) unsigned DEFAULT '0' NOT NULL,
	author_ident tinytext,
	comment int(11) unsigned DEFAULT '0' NOT NULL,
);
