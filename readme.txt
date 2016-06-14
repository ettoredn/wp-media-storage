=== Plugin Name ===
Contributors: ettoredn
Donate link: http://example.com/
Tags: CDN, Cloud, Media Library, Storage, Uploads, Object Storage, OpenStack, Swift, Amazon, Amazon S3, S3
Requires at least: 4.4
Tested up to: 4.5.2
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Directly access and store Media Library files on cloud Object Stores.

== Description ==

### Media Library
This plugin enables WordPress to *directly* store and retrieve media files from cloud object stores such as OpenStack Swift, Amazon S3 and Google Cloud Storage. 

### Requirements
The plugin has been tested only with the dirty cheap [OVH Public Cloud Object Storage](https://www.ovh.ie/cloud/storage/object-storage.xml) which is built on standard OpenStack.

- OpenStack's Identity API v2.0
- PHP >= 7.0
- monkey patching /wp-admin/files.php (because WP should be rewritten from scratch)

### Limitations
The plugin is stil in early development and as such only supports a limited set of features (based on the author's needs). Contributions are welcome on [GitHub](https://github.com/ettoredn/wp-media-storage).
- OpenStack's Swift is the only object store
- [wp-cli](https://wp-cli.org/) command to upload existing is still work in progress
- PHP <= 5.6.x not supported as the author likes to be on the bleeding edge
- you may experience very slower media uploads depending on the perfomance of your object store (available bandwidth and file size)

### Roadmap
- Support OpenStack's Identity v3.x
- wp-cli command to upload existing media
- Amazon S3 support
- Google Storage Support

== Installation ==

0. Do not use this plugin if you don't know what you are doing or why you need it.
1. Install via the WordPress dashboard or visit [GitHub](https://github.com/ettoredn/wp-media-storage) for the [Composer](https://getcomposer.org/) version.
2. Go to 'Settings' > 'Media Storage'
2. Select 'OpenStack (Swift)'
2. Fill OpenStack credentials undert 'Settings' > 'Media Storage'
3. Set the public URL pointing to the root of your Swift container. It will be used for new posts and pages.
4. Check credentials by clicking on 'Test Settings'
5. 'Save Changes'

== Frequently Asked Questions ==

= Does the plugin acccess files directly from the object store?

You bet.

= Why after activating the pluging my uploads are slower? =

Because there is a lot of overhead involved in directly accessing the data on the object store versus local file system.
However, performance should mostly be bound to the network/storage speed of your object store.

== Screenshots ==

1. Media Storage settings

== Changelog ==

= 0.1 =
* Support OpenStack's Swift

== Upgrade Notice ==

= 0.1 =
None
