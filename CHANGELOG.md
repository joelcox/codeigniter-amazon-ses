Changelog
=========
This log includes all changes to the public API of this library. Check the project's commit history for a more detailed look and for changes that impact the internals of this library.

v0.3.1
------
* Fixed a bug related to loading the cURL library.
* The 'reply-to' can now be correctly loaded from the configuration file.
* The destroy() method was removed from the documentation.

v0.3
----
* Added support for a vanity 'from' name. This can be set using the second parameter in the 'from' method or setting the 'amazon_ses_from_name' config parameter.
* Added an extra check to overcome conflicting certificates. Thanks Stephen Frank.