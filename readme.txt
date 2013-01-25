=== HeadJS Loader ===
Contributors: durin
Donate link: http://chuckmac.info/
Tags: javascript, js, css
Requires at least: 3.0
Tested up to: 3.5.1
Stable tag: trunk

A WordPress plugin to load your Javascript files via Head JS.  

== Description ==

This plugin reformats your page to utilize <a href="http://headjs.com" target="_BLANK" title="HeadJS">Head JS</a> in your WordPress site.

**Caution: this plugin can cause major issues with the javascript on your site if not implemented properly.  Please be sure to test on a development server first**

It strips out all your old javascript declarations and puts them into head.js calls so that they are loaded in parallel (see the Head JS website for more details).

Optionally you can wrap all your inline javascript with head.ready calls.

For example, this:

<pre><code>
<script type='text/javascript' src='http://yoururl.com/wp-includes/js/prototype.js?ver=1.6.1'></script> 
<script type='text/javascript' src='http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js?ver=3.0.4'></script> 
<script type='text/javascript' src='http://yoururl.com/wp-includes/js/scriptaculous/wp-scriptaculous.js?ver=1.8.3'></script> 
<script type='text/javascript' src='http://yoururl.com/wp-includes/js/scriptaculous/builder.js?ver=1.8.3'></script> 
<script type='text/javascript' src='http://yoururl.com/wp-includes/js/scriptaculous/effects.js?ver=1.8.3'></script> 
<script type='text/javascript' src='http://yoururl.com/wp-includes/js/scriptaculous/dragdrop.js?ver=1.8.3'></script> 
<script type='text/javascript' src='http://yoururl.com/wp-includes/js/scriptaculous/slider.js?ver=1.8.3'></script> 
<script type='text/javascript' src='http://yoururl.com/wp-includes/js/scriptaculous/controls.js?ver=1.8.3'></script> 
</code></pre>

Becomes:

<pre><code>
<script type="text/javascript" src="http://yoururl.com/wp-content/plugins/headjs-loader/js/head.min.js"></script> 
<script> 
head.js("http://yoururl.com/wp-includes/js/prototype.js?ver=1.6.1",
    "http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js?ver=3.0.4",
    "http://yoururl.com/wp-includes/js/scriptaculous/wp-scriptaculous.js?ver=1.8.3",
    "http://yoururl.com/wp-includes/js/scriptaculous/builder.js?ver=1.8.3",
    "http://yoururl.com/wp-includes/js/scriptaculous/effects.js?ver=1.8.3",
    "http://yoururl.com/wp-includes/js/scriptaculous/dragdrop.js?ver=1.8.3",
    "http://yoururl.com/wp-includes/js/scriptaculous/slider.js?ver=1.8.3",
    "http://yoururl.com/wp-includes/js/scriptaculous/controls.js?ver=1.8.3"
);
</script> 
</code></pre>

Feel free to contribue to the project on <a href="http://github.com/ChuckMac/wp-headjs-loader">GitHub</a>!

== Installation ==

1. Upload `headjs-loader` directory to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Screenshots ==

== Frequently Asked Questions ==

= No questions =

No answers.

== Upgrade Notice ==

= 0.2 =
Major rework!
* Updated to head.js 0.99
* Added admin options page so you can chose which version to load
* Allow inline javascript to be wrapped in head.ready calls

= 0.1.1 =
Fixed bug that caused apache erorr messages if no javascript was declared.

== Changelog ==

= 0.1.1 =
* Fixed bug that caused apache erorr messages if no javascript was declared.

= 0.1 =
* Initial release.
